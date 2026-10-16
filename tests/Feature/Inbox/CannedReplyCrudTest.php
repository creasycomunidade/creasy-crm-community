<?php

namespace Tests\Feature\Inbox;

use App\Modules\Inbox\Models\CannedReply;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CannedReplyCrudTest extends TestCase
{
    use RefreshDatabase;

    private array $ctx;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ctx = $this->createWorkspaceContext();
    }

    public function test_can_list_canned_replies(): void
    {
        $user = $this->ctx['user'];
        $workspace = $this->ctx['workspace'];

        CannedReply::create(['workspace_id' => $workspace->id, 'shortcut' => 'hi', 'body' => 'Hello!']);
        CannedReply::create(['workspace_id' => $workspace->id, 'shortcut' => 'bye', 'body' => 'Goodbye!']);

        // Test via the JSON list endpoint (avoids Vite manifest during page render)
        $response = $this->actingAs($user)->getJson(route('client.inbox.canned-replies.list'));
        $response->assertOk();
        $this->assertCount(2, $response->json());
        $response->assertJsonFragment(['shortcut' => 'hi']);
        $response->assertJsonFragment(['shortcut' => 'bye']);
    }

    public function test_can_create_canned_reply(): void
    {
        $user = $this->ctx['user'];

        $response = $this->actingAs($user)->post(route('client.inbox.canned-replies.store'), [
            'shortcut' => 'greeting',
            'body' => 'Hello {{contact.first_name}}, welcome!',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('inbox_canned_replies', [
            'shortcut' => 'greeting',
            'workspace_id' => $this->ctx['workspace']->id,
        ]);
    }

    public function test_shortcut_must_be_unique_per_workspace(): void
    {
        $user = $this->ctx['user'];
        $workspace = $this->ctx['workspace'];

        CannedReply::create(['workspace_id' => $workspace->id, 'shortcut' => 'hello', 'body' => 'Hi!']);

        $response = $this->actingAs($user)->post(route('client.inbox.canned-replies.store'), [
            'shortcut' => 'hello',
            'body' => 'Duplicate',
        ]);

        $response->assertSessionHasErrors('shortcut');
    }

    public function test_can_update_canned_reply(): void
    {
        $user = $this->ctx['user'];
        $workspace = $this->ctx['workspace'];

        $reply = CannedReply::create(['workspace_id' => $workspace->id, 'shortcut' => 'orig', 'body' => 'Original body']);

        $response = $this->actingAs($user)->put(route('client.inbox.canned-replies.update', $reply->id), [
            'shortcut' => 'orig',
            'body' => 'Updated body',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('inbox_canned_replies', ['id' => $reply->id, 'body' => 'Updated body']);
    }

    public function test_can_delete_canned_reply(): void
    {
        $user = $this->ctx['user'];
        $workspace = $this->ctx['workspace'];

        $reply = CannedReply::create(['workspace_id' => $workspace->id, 'shortcut' => 'del', 'body' => 'To delete']);

        $response = $this->actingAs($user)->delete(route('client.inbox.canned-replies.destroy', $reply->id));
        $response->assertRedirect();
        $this->assertDatabaseMissing('inbox_canned_replies', ['id' => $reply->id]);
    }

    public function test_cannot_update_another_workspaces_reply(): void
    {
        $user = $this->ctx['user'];
        $other = $this->createWorkspaceContext();
        $reply = CannedReply::create([
            'workspace_id' => $other['workspace']->id,
            'shortcut' => 'xss',
            'body' => 'Should not update',
        ]);

        $response = $this->actingAs($user)->put(route('client.inbox.canned-replies.update', $reply->id), [
            'shortcut' => 'xss',
            'body' => 'Hacked',
        ]);

        $response->assertStatus(403);
    }
}
