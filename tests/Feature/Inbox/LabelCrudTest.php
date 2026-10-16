<?php

namespace Tests\Feature\Inbox;

use App\Modules\Inbox\Models\InboxLabel;
use App\Modules\Shared\Models\ChannelAccount;
use App\Modules\Shared\Models\Contact;
use App\Modules\Shared\Models\Conversation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LabelCrudTest extends TestCase
{
    use RefreshDatabase;

    private array $ctx;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ctx = $this->createWorkspaceContext();
    }

    public function test_can_create_label(): void
    {
        $user = $this->ctx['user'];

        $response = $this->actingAs($user)->post(route('client.inbox.labels.store'), [
            'name' => 'Urgent',
            'color' => '#ef4444',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('inbox_labels', [
            'name' => 'Urgent',
            'workspace_id' => $this->ctx['workspace']->id,
        ]);
    }

    public function test_label_name_must_be_unique_per_workspace(): void
    {
        $user = $this->ctx['user'];
        $workspace = $this->ctx['workspace'];

        InboxLabel::create(['workspace_id' => $workspace->id, 'name' => 'VIP', 'color' => '#6366f1']);

        $response = $this->actingAs($user)->post(route('client.inbox.labels.store'), [
            'name' => 'VIP',
            'color' => '#6366f1',
        ]);

        $response->assertSessionHasErrors('name');
    }

    public function test_can_delete_label(): void
    {
        $user = $this->ctx['user'];
        $workspace = $this->ctx['workspace'];

        $label = InboxLabel::create(['workspace_id' => $workspace->id, 'name' => 'Temp', 'color' => '#000']);

        $response = $this->actingAs($user)->delete(route('client.inbox.labels.destroy', $label->id));
        $response->assertRedirect();
        $this->assertDatabaseMissing('inbox_labels', ['id' => $label->id]);
    }

    public function test_can_attach_label_to_conversation(): void
    {
        $user = $this->ctx['user'];
        $workspace = $this->ctx['workspace'];

        $label = InboxLabel::create(['workspace_id' => $workspace->id, 'name' => 'Hot', 'color' => '#f00']);
        $contact = Contact::factory()->create(['workspace_id' => $workspace->id]);
        $account = ChannelAccount::create([
            'workspace_id' => $workspace->id, 'channel' => 'whatsapp', 'provider' => 'meta',
            'display_name' => 'WA', 'status' => 'active',
        ]);
        $conv = Conversation::create([
            'workspace_id' => $workspace->id,
            'channel_account_id' => $account->id,
            'contact_id' => $contact->id,
            'status' => 'open',
        ]);

        $response = $this->actingAs($user)->postJson(route('client.inbox.labels.attach', $conv->uuid), [
            'label_id' => $label->id,
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('inbox_label_conversation', [
            'conversation_id' => $conv->id,
            'label_id' => $label->id,
        ]);
    }

    public function test_can_detach_label_from_conversation(): void
    {
        $user = $this->ctx['user'];
        $workspace = $this->ctx['workspace'];

        $label = InboxLabel::create(['workspace_id' => $workspace->id, 'name' => 'Cold', 'color' => '#00f']);
        $contact = Contact::factory()->create(['workspace_id' => $workspace->id]);
        $account = ChannelAccount::create([
            'workspace_id' => $workspace->id, 'channel' => 'whatsapp', 'provider' => 'meta',
            'display_name' => 'WA2', 'status' => 'active',
        ]);
        $conv = Conversation::create([
            'workspace_id' => $workspace->id,
            'channel_account_id' => $account->id,
            'contact_id' => $contact->id,
            'status' => 'open',
        ]);

        // First attach
        $conv->labels()->attach($label->id);

        $response = $this->actingAs($user)->deleteJson(route('client.inbox.labels.detach', [$conv->uuid, $label->id]));
        $response->assertOk();
        $this->assertDatabaseMissing('inbox_label_conversation', [
            'conversation_id' => $conv->id,
            'label_id' => $label->id,
        ]);
    }

    public function test_cross_workspace_attach_forbidden(): void
    {
        $user = $this->ctx['user'];
        $other = $this->createWorkspaceContext();

        $label = InboxLabel::create(['workspace_id' => $other['workspace']->id, 'name' => 'Secret', 'color' => '#000']);
        $contact = Contact::factory()->create(['workspace_id' => $other['workspace']->id]);
        $account = ChannelAccount::create([
            'workspace_id' => $other['workspace']->id, 'channel' => 'whatsapp', 'provider' => 'meta',
            'display_name' => 'WA3', 'status' => 'active',
        ]);
        $conv = Conversation::create([
            'workspace_id' => $other['workspace']->id,
            'channel_account_id' => $account->id,
            'contact_id' => $contact->id,
            'status' => 'open',
        ]);

        $response = $this->actingAs($user)->postJson(route('client.inbox.labels.attach', $conv->uuid), [
            'label_id' => $label->id,
        ]);

        $response->assertForbidden();
    }
}
