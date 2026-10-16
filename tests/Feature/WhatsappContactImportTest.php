<?php

namespace Tests\Feature;

use App\Modules\Integrations\Models\IntegrationConfig;
use App\Modules\Shared\Models\ChannelAccount;
use App\Modules\Shared\Models\Contact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WhatsappContactImportTest extends TestCase
{
    use RefreshDatabase;

    private const BASE_URL = 'https://1.1.1.1';

    public function test_preview_extracts_valid_phone_contacts_and_marks_duplicates(): void
    {
        ['user' => $user, 'workspace' => $workspace] = $this->createWorkspaceContext();
        $account = $this->account($workspace->id);
        Contact::create(['workspace_id' => $workspace->id, 'phone_e164' => '+5511999999999', 'first_name' => 'Existente']);

        Http::fake([self::BASE_URL.'/user/contacts' => Http::response(['data' => [
            ['Jid' => '5511999999999@s.whatsapp.net', 'Found' => true, 'FullName' => 'João Silva'],
            ['Jid' => '5547988887777@s.whatsapp.net', 'Found' => true, 'PushName' => 'Maria'],
            ['Jid' => '120363000000@g.us', 'Found' => true, 'FullName' => 'Grupo'],
            ['Jid' => '12345@lid', 'Found' => true, 'FullName' => 'Sem telefone'],
        ]])]);

        $this->actingAs($user)
            ->postJson(route('client.contacts.whatsapp-import.preview'), ['channel_account_id' => $account->id])
            ->assertOk()
            ->assertJsonCount(2, 'contacts')
            ->assertJsonPath('summary.new', 1)
            ->assertJsonPath('summary.existing', 1)
            ->assertJsonFragment(['phone_e164' => '+5547988887777', 'name' => 'Maria', 'status' => 'new']);
    }

    public function test_import_refetches_selected_contacts_and_does_not_assume_marketing_consent(): void
    {
        ['user' => $user, 'workspace' => $workspace] = $this->createWorkspaceContext();
        $account = $this->account($workspace->id);

        Http::fake([self::BASE_URL.'/user/contacts' => Http::response(['data' => [
            ['Jid' => '5547988887777@s.whatsapp.net', 'Found' => true, 'FullName' => 'Maria Souza'],
        ]])]);

        $this->actingAs($user)
            ->postJson(route('client.contacts.whatsapp-import.store'), [
                'channel_account_id' => $account->id,
                'selected_jids' => ['5547988887777@s.whatsapp.net'],
                'marketing_consent_confirmed' => false,
            ])
            ->assertOk()
            ->assertJsonPath('stats.created', 1);

        $contact = Contact::where('workspace_id', $workspace->id)->firstOrFail();
        $this->assertSame('+5547988887777', $contact->phone_e164);
        $this->assertSame('Maria', $contact->first_name);
        $this->assertSame('Souza', $contact->last_name);
        $this->assertFalse($contact->opt_in_whatsapp);
        $this->assertSame($account->id, $contact->custom_fields['whatsapp_contact_import_account_id']);
        $this->assertSame(['5547988887777@s.whatsapp.net'], $contact->custom_fields['evolution_go_jids']);
    }

    public function test_preview_can_return_only_deduplicated_group_participants(): void
    {
        ['user' => $user, 'workspace' => $workspace] = $this->createWorkspaceContext();
        $account = $this->account($workspace->id);
        Contact::create(['workspace_id' => $workspace->id, 'phone_e164' => '+5511999999999', 'first_name' => 'Existente']);

        Http::fake([self::BASE_URL.'/group/list' => Http::response(['data' => [
            ['JID' => '120363000000000001@g.us', 'Name' => 'Comercial', 'Participants' => [
                ['JID' => '100000000000001@lid', 'PhoneNumber' => '5511999999999@s.whatsapp.net', 'DisplayName' => 'João'],
                ['JID' => '100000000000002@lid', 'PhoneNumber' => '5547988887777@s.whatsapp.net', 'DisplayName' => 'Maria'],
            ]],
            ['JID' => '120363000000000002@g.us', 'Name' => 'Suporte', 'Participants' => [
                ['JID' => '100000000000002@lid', 'PhoneNumber' => '5547988887777@s.whatsapp.net', 'DisplayName' => 'Maria'],
                ['JID' => '100000000000003@lid', 'DisplayName' => 'Sem telefone'],
            ]],
        ]])]);

        $this->actingAs($user)
            ->postJson(route('client.contacts.whatsapp-import.preview'), [
                'channel_account_id' => $account->id,
                'only_groups' => true,
                'group_jids' => ['120363000000000001@g.us', '120363000000000002@g.us'],
            ])
            ->assertOk()
            ->assertJsonCount(2, 'contacts')
            ->assertJsonPath('summary.new', 1)
            ->assertJsonPath('summary.existing', 1)
            ->assertJsonFragment([
                'phone_e164' => '+5547988887777',
                'name' => 'Maria',
                'group_names' => ['Comercial', 'Suporte'],
                'status' => 'new',
            ]);
    }

    public function test_group_participant_import_refetches_groups_and_records_source(): void
    {
        ['user' => $user, 'workspace' => $workspace] = $this->createWorkspaceContext();
        $account = $this->account($workspace->id);

        Http::fake([self::BASE_URL.'/group/list' => Http::response(['data' => [[
            'JID' => '120363000000000003@g.us',
            'Name' => 'Clientes VIP',
            'Participants' => [[
                'JID' => '100000000000002@lid',
                'PhoneNumber' => '5547988887777@s.whatsapp.net',
                'DisplayName' => 'Maria Souza',
            ]],
        ]]])]);

        $this->actingAs($user)
            ->postJson(route('client.contacts.whatsapp-import.store'), [
                'channel_account_id' => $account->id,
                'selected_jids' => ['5547988887777@s.whatsapp.net'],
                'only_groups' => true,
                'group_jids' => ['120363000000000003@g.us'],
            ])
            ->assertOk()
            ->assertJsonPath('stats.created', 1);

        $contact = Contact::where('workspace_id', $workspace->id)->firstOrFail();
        $this->assertSame('whatsapp_group_participant_import', $contact->source);
        $this->assertSame(['Clientes VIP'], $contact->custom_fields['whatsapp_import_group_names']);
        $this->assertFalse($contact->opt_in_whatsapp);
    }

    public function test_groups_endpoint_lists_summaries_without_returning_participants(): void
    {
        ['user' => $user, 'workspace' => $workspace] = $this->createWorkspaceContext();
        $account = $this->account($workspace->id);

        Http::fake([self::BASE_URL.'/group/list' => Http::response(['data' => [
            ['JID' => '120363000000000002@g.us', 'Name' => 'Suporte', 'Participants' => [
                ['PhoneNumber' => '5511999999999@s.whatsapp.net'],
            ]],
            ['JID' => '120363000000000001@g.us', 'Name' => 'Comercial', 'Participants' => []],
            ['JID' => '5511999999999@s.whatsapp.net', 'Name' => 'Não é grupo', 'Participants' => []],
        ]])]);

        $this->actingAs($user)
            ->postJson(route('client.contacts.whatsapp-import.groups'), ['channel_account_id' => $account->id])
            ->assertOk()
            ->assertJsonCount(2, 'groups')
            ->assertJsonPath('groups.0.name', 'Comercial')
            ->assertJsonPath('groups.1.name', 'Suporte')
            ->assertJsonMissingPath('groups.1.participants')
            ->assertJsonPath('groups.1.participant_count', 1);
    }

    public function test_group_preview_requires_an_explicit_group_selection(): void
    {
        ['user' => $user, 'workspace' => $workspace] = $this->createWorkspaceContext();
        $account = $this->account($workspace->id);

        $this->actingAs($user)
            ->postJson(route('client.contacts.whatsapp-import.preview'), [
                'channel_account_id' => $account->id,
                'only_groups' => true,
                'group_jids' => [],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('group_jids');

        Http::assertNothingSent();
    }

    public function test_workspace_cannot_read_another_workspace_account(): void
    {
        ['user' => $user] = $this->createWorkspaceContext();
        ['workspace' => $otherWorkspace] = $this->createWorkspaceContext();
        $foreignAccount = $this->account($otherWorkspace->id);

        $this->actingAs($user)
            ->postJson(route('client.contacts.whatsapp-import.preview'), ['channel_account_id' => $foreignAccount->id])
            ->assertNotFound();

        Http::assertNothingSent();
    }

    private function account(int $workspaceId): ChannelAccount
    {
        IntegrationConfig::firstOrCreate(
            ['provider' => 'evolution_go'],
            [
                'label' => 'Evolution Go',
                'mode' => 'live',
                'enabled' => true,
                'credentials' => [
                    'base_url' => self::BASE_URL,
                    'global_api_key' => 'global-secret',
                ],
            ]
        );

        return ChannelAccount::create([
            'workspace_id' => $workspaceId,
            'channel' => 'whatsapp',
            'provider' => 'evolution_go',
            'display_name' => 'Atendimento',
            'status' => 'active',
            'credentials' => ['instance_token' => 'instance-secret'],
        ]);
    }
}
