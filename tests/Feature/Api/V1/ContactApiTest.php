<?php

namespace Tests\Feature\Api\V1;

use App\Modules\Shared\Models\Contact;
use App\Support\ApiAbilities;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactApiTest extends TestCase
{
    use RefreshDatabase;

    // ── Auth & scope guards ───────────────────────────────────────────────────

    public function test_unauthenticated_returns_401(): void
    {
        $this->getJson('/api/v1/contacts')->assertStatus(401);
    }

    public function test_wrong_scope_returns_403(): void
    {
        ['user' => $user] = $this->createWorkspaceContext();
        $token = $user->createToken('test', [ApiAbilities::CAMPAIGNS_READ])->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/contacts')->assertStatus(403);
    }

    // ── List ──────────────────────────────────────────────────────────────────

    public function test_list_contacts_returns_200(): void
    {
        ['user' => $user, 'workspace' => $workspace] = $this->createWorkspaceContext();
        $token = $user->createToken('t', [ApiAbilities::CONTACTS_READ])->plainTextToken;

        Contact::factory()->count(3)->create(['workspace_id' => $workspace->id]);

        $this->withToken($token)
            ->getJson('/api/v1/contacts')
            ->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_list_contacts_search_filter(): void
    {
        ['user' => $user, 'workspace' => $workspace] = $this->createWorkspaceContext();
        $token = $user->createToken('t', ['*'])->plainTextToken;

        Contact::factory()->create(['workspace_id' => $workspace->id, 'first_name' => 'Unique']);
        Contact::factory()->create(['workspace_id' => $workspace->id, 'first_name' => 'Other']);

        $res = $this->withToken($token)
            ->getJson('/api/v1/contacts?search=Unique')
            ->assertOk();

        $this->assertCount(1, $res->json('data'));
    }

    // ── Create ────────────────────────────────────────────────────────────────

    public function test_create_contact_returns_201(): void
    {
        ['user' => $user, 'workspace' => $workspace] = $this->createWorkspaceContext();
        $token = $user->createToken('t', [ApiAbilities::CONTACTS_WRITE])->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/contacts', [
                'phone_e164' => '+8801700000001',
                'first_name' => 'Rahim',
                'opt_in_whatsapp' => true,
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.first_name', 'Rahim');

        $this->assertDatabaseHas('contacts', ['phone_e164' => '+8801700000001', 'workspace_id' => $workspace->id]);
    }

    public function test_create_contact_invalid_phone_returns_422(): void
    {
        ['user' => $user] = $this->createWorkspaceContext();
        $token = $user->createToken('t', [ApiAbilities::CONTACTS_WRITE])->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/contacts', ['phone_e164' => 'not-a-phone'])
            ->assertStatus(422);
    }

    // ── Show ─────────────────────────────────────────────────────────────────

    public function test_show_contact_returns_200(): void
    {
        ['user' => $user, 'workspace' => $workspace] = $this->createWorkspaceContext();
        $token = $user->createToken('t', ['*'])->plainTextToken;
        $contact = Contact::factory()->create(['workspace_id' => $workspace->id]);

        $this->withToken($token)
            ->getJson("/api/v1/contacts/{$contact->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $contact->id);
    }

    public function test_show_contact_from_other_workspace_returns_404(): void
    {
        ['user' => $user] = $this->createWorkspaceContext();
        ['workspace' => $otherWs] = $this->createWorkspaceContext();
        $token = $user->createToken('t', ['*'])->plainTextToken;
        $contact = Contact::factory()->create(['workspace_id' => $otherWs->id]);

        $this->withToken($token)
            ->getJson("/api/v1/contacts/{$contact->id}")
            ->assertStatus(404);
    }

    // ── Update ────────────────────────────────────────────────────────────────

    public function test_update_contact_returns_200(): void
    {
        ['user' => $user, 'workspace' => $workspace] = $this->createWorkspaceContext();
        $token = $user->createToken('t', ['*'])->plainTextToken;
        $contact = Contact::factory()->create(['workspace_id' => $workspace->id]);

        $this->withToken($token)
            ->patchJson("/api/v1/contacts/{$contact->id}", ['first_name' => 'UpdatedName'])
            ->assertOk()
            ->assertJsonPath('data.first_name', 'UpdatedName');
    }

    // ── Delete ────────────────────────────────────────────────────────────────

    public function test_delete_contact_returns_200(): void
    {
        ['user' => $user, 'workspace' => $workspace] = $this->createWorkspaceContext();
        $token = $user->createToken('t', ['*'])->plainTextToken;
        $contact = Contact::factory()->create(['workspace_id' => $workspace->id]);

        $this->withToken($token)
            ->deleteJson("/api/v1/contacts/{$contact->id}")
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertSoftDeleted('contacts', ['id' => $contact->id]);
    }
}
