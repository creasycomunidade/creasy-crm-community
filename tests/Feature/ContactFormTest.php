<?php

namespace Tests\Feature;

use App\Models\ContactMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_contact_page_is_accessible(): void
    {
        $this->get('/contact')->assertOk();
    }

    public function test_guest_can_submit_contact_form(): void
    {
        $this->post('/contact', [
            'name'    => 'John Doe',
            'email'   => 'john@example.com',
            'subject' => 'Hello',
            'message' => 'This is a test message.',
        ])->assertRedirect();

        $this->assertDatabaseHas('contact_messages', [
            'email'   => 'john@example.com',
            'subject' => 'Hello',
        ]);
    }

    public function test_contact_form_validates_required_fields(): void
    {
        $this->post('/contact', [])
             ->assertSessionHasErrors(['name', 'email', 'message']);
    }

    public function test_contact_form_validates_email(): void
    {
        $this->post('/contact', [
            'name'    => 'Test',
            'email'   => 'not-an-email',
            'message' => 'Test message',
        ])->assertSessionHasErrors(['email']);
    }
}
