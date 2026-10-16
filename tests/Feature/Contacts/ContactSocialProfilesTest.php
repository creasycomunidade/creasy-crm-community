<?php

namespace Tests\Feature\Contacts;

use App\Models\User;
use App\Models\Workspace;
use App\Modules\Shared\Models\Contact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactSocialProfilesTest extends TestCase
{
    use RefreshDatabase;

    public function test_contact_can_be_created_with_the_complete_profile_from_the_add_modal(): void
    {
        [$user, $workspace] = $this->userWithWorkspace();

        $this->actingAs($user)->post(route('client.contacts.store'), [
            'first_name' => 'Marina',
            'last_name' => 'Silva',
            'phone_e164' => '+5547999999999',
            'email' => 'marina@example.com',
            'country' => 'BR',
            'language' => 'pt-BR',
            'website_url' => 'https://marina.example.com',
            'social_profiles' => [
                'instagram' => '@marina',
                'telegram' => '@marina_silva',
                'facebook' => 'https://facebook.com/marina',
                'linkedin' => 'https://linkedin.com/in/marina',
                'tiktok' => '@marina',
                'x' => '@marina',
                'youtube' => 'https://youtube.com/@marina',
            ],
            'contact_links' => [
                ['label' => 'Catálogo', 'url' => 'https://marina.example.com/catalogo'],
            ],
            'opt_in_whatsapp' => true,
            'opt_in_sms' => true,
            'opt_in_email' => true,
            'segment_ids' => [],
        ])->assertSessionHasNoErrors();

        $contact = Contact::query()
            ->where('workspace_id', $workspace->id)
            ->where('email', 'marina@example.com')
            ->firstOrFail();

        $this->assertSame('BR', $contact->country);
        $this->assertSame('pt-BR', $contact->language);
        $this->assertSame('https://marina.example.com', $contact->website_url);
        $this->assertSame('@marina', $contact->social_profiles['instagram']);
        $this->assertSame('@marina_silva', $contact->social_profiles['telegram']);
        $this->assertSame('Catálogo', $contact->contact_links[0]['label']);
    }

    public function test_contact_social_profiles_website_and_links_can_be_updated(): void
    {
        [$user, $workspace] = $this->userWithWorkspace();
        $contact = Contact::factory()->create(['workspace_id' => $workspace->id]);

        $this->actingAs($user)->put(route('client.contacts.update', $contact), [
            'first_name' => 'Ana',
            'last_name' => 'Letícia',
            'email' => 'ana@example.com',
            'website_url' => 'https://ana.example.com',
            'country' => 'BR',
            'language' => 'pt-BR',
            'social_profiles' => [
                'instagram' => '@ana.leticia',
                'telegram' => '@analeticia',
                'facebook' => 'https://facebook.com/ana.leticia',
                'linkedin' => 'https://linkedin.com/in/ana-leticia',
                'tiktok' => '@ana.leticia',
                'x' => '@analeticia',
                'youtube' => 'https://youtube.com/@analeticia',
            ],
            'contact_links' => [
                ['label' => 'Catálogo', 'url' => 'https://ana.example.com/catalogo'],
                ['label' => 'Agendamento', 'url' => 'https://ana.example.com/agendar'],
            ],
            'opt_in_whatsapp' => true,
            'opt_in_sms' => false,
            'opt_in_email' => true,
            'segment_ids' => [],
        ])->assertSessionHasNoErrors();

        $contact->refresh();
        $this->assertSame('https://ana.example.com', $contact->website_url);
        $this->assertSame('@ana.leticia', $contact->social_profiles['instagram']);
        $this->assertSame('@analeticia', $contact->social_profiles['telegram']);
        $this->assertSame('Catálogo', $contact->contact_links[0]['label']);
        $this->assertCount(2, $contact->contact_links);
    }

    public function test_unsafe_links_are_rejected_and_another_company_cannot_update_the_contact(): void
    {
        [$owner, $workspaceA] = $this->userWithWorkspace();
        [$other, $workspaceB] = $this->userWithWorkspace();
        $contact = Contact::factory()->create(['workspace_id' => $workspaceA->id]);

        $this->actingAs($owner)->put(route('client.contacts.update', $contact), [
            'website_url' => 'javascript:alert(1)',
            'social_profiles' => ['facebook' => 'javascript:alert(1)'],
            'contact_links' => [['label' => 'Perigoso', 'url' => 'javascript:alert(1)']],
        ])->assertSessionHasErrors(['website_url', 'social_profiles.facebook', 'contact_links.0.url']);

        $this->actingAs($other)->put(route('client.contacts.update', $contact), [
            'website_url' => 'https://empresa-b.example.com',
        ])->assertForbidden();

        $contact->refresh();
        $this->assertNull($contact->website_url);
        $this->assertSame($workspaceA->id, $contact->workspace_id);
        $this->assertNotSame($workspaceB->id, $contact->workspace_id);
    }

    private function userWithWorkspace(): array
    {
        $user = User::factory()->create(['role' => 'client', 'email_verified_at' => now()]);
        $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
        $user->update(['workspace_id' => $workspace->id]);

        return [$user->refresh(), $workspace];
    }
}
