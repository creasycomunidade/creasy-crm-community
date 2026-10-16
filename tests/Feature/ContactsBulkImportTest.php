<?php

namespace Tests\Feature;

use App\Modules\Shared\Models\Contact;
use App\Modules\Shared\Models\ContactTag;
use App\Modules\Shared\Models\Segment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactsBulkImportTest extends TestCase
{
    use RefreshDatabase;

    private function inertiaHeaders(): array
    {
        $version = file_exists(public_path('build/manifest.json'))
            ? hash_file('xxh128', public_path('build/manifest.json'))
            : '';

        return ['X-Inertia' => 'true', 'X-Inertia-Version' => $version];
    }

    public function test_bulk_import_creates_contact_with_tag_and_static_segment(): void
    {
        ['user' => $user, 'workspace' => $workspace] = $this->createWorkspaceContext();
        $tag = ContactTag::create(['workspace_id' => $workspace->id, 'name' => 'VIP', 'color' => '#6366f1']);
        $segment = Segment::create([
            'workspace_id' => $workspace->id,
            'name' => 'Newsletter',
            'type' => 'static',
            'contact_count' => 0,
        ]);

        $this->actingAs($user)
            ->from(route('client.contacts.bulk-import'))
            ->withHeaders($this->inertiaHeaders())
            ->post(route('client.contacts.bulk-store'), [
                'rows' => [
                    [
                        'name' => 'Ada Lovelace',
                        'phone_e164' => '+447700900123',
                        'tag_id' => $tag->id,
                        'segment_id' => $segment->id,
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('component', 'Contacts/BulkImport');

        $this->assertDatabaseHas('contacts', [
            'workspace_id' => $workspace->id,
            'phone_e164' => '+447700900123',
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
        ]);

        $contact = Contact::where('workspace_id', $workspace->id)->where('phone_e164', '+447700900123')->first();
        $this->assertNotNull($contact);
        $this->assertTrue($contact->tags->contains('id', $tag->id));
        $this->assertTrue($contact->segments->contains('id', $segment->id));
        $segment->refresh();
        $this->assertSame(1, (int) $segment->contact_count);
    }

    public function test_bulk_import_rejects_dynamic_segment_id(): void
    {
        ['user' => $user, 'workspace' => $workspace] = $this->createWorkspaceContext();
        $segment = Segment::create([
            'workspace_id' => $workspace->id,
            'name' => 'Dynamic',
            'type' => 'dynamic',
            'rules_json' => ['combinator' => 'AND', 'conditions' => []],
            'contact_count' => 0,
        ]);

        $this->actingAs($user)
            ->from(route('client.contacts.bulk-import'))
            ->withHeaders($this->inertiaHeaders())
            ->post(route('client.contacts.bulk-store'), [
                'rows' => [
                    [
                        'name' => 'Test',
                        'phone_e164' => '+12025550199',
                        'segment_id' => $segment->id,
                    ],
                ],
            ])
            ->assertInvalid(['rows.0.segment_id']);
    }

    public function test_bulk_import_requires_at_least_one_phone_row(): void
    {
        ['user' => $user] = $this->createWorkspaceContext();

        $this->actingAs($user)
            ->from(route('client.contacts.bulk-import'))
            ->withHeaders($this->inertiaHeaders())
            ->post(route('client.contacts.bulk-store'), [
                'rows' => [
                    ['name' => 'Only Name', 'phone_e164' => null],
                ],
            ])
            ->assertInvalid(['rows']);
    }
}
