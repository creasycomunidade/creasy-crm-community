<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Models\SmtpConfiguration;
use App\Services\Mail\MailService;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_verification_screen_can_be_rendered(): void
    {
        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user)->get('/verify-email');

        $response->assertStatus(200);
    }

    public function test_email_can_be_verified(): void
    {
        $user = User::factory()->unverified()->create();

        Event::fake();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        $response = $this->actingAs($user)->get($verificationUrl);

        Event::assertDispatched(Verified::class);
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $response->assertRedirect(route('client.dashboard', absolute: false).'?verified=1');
    }

    public function test_email_is_not_verified_with_invalid_hash(): void
    {
        $user = User::factory()->unverified()->create();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1('wrong-email')]
        );

        $this->actingAs($user)->get($verificationUrl);

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_resend_reports_a_real_smtp_delivery_failure(): void
    {
        $user = User::factory()->unverified()->create();
        SmtpConfiguration::create([
            'host' => 'smtp.example.com', 'port' => 465, 'username' => 'mailer',
            'password' => 'secret', 'encryption' => 'ssl',
            'from_email' => 'naoresponda@example.com', 'from_name' => 'Respondesk',
            'is_active' => true,
        ]);
        $this->mock(MailService::class)
            ->shouldReceive('sendWithTemplate')
            ->once()
            ->with('email_verification', $user->email, \Mockery::type('array'))
            ->andReturnFalse();

        $this->actingAs($user)
            ->post('/email/verification-notification')
            ->assertSessionHasErrors('email');
    }
}
