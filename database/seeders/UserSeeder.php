<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\User;
use App\Modules\Broadcasting\Models\WorkspaceSmtpConfig;
use App\Modules\Shared\Models\Contact;
use App\Modules\Whatsapp\Models\WhatsappBusinessAccount;
use App\Services\ClientWorkspaceService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $email = env('CLIENT_SEED_EMAIL', 'client@example.com');
        $password = env('CLIENT_SEED_PASSWORD') ?: Str::password(16);

        $client = Client::firstOrCreate(
            ['email' => $email],
            [
                'name' => 'Demo Client',
                'status' => Client::STATUS_ACTIVE,
                'base_currency' => 'BRL',
                'currency_symbol' => 'R$',
                'currency_position' => 'before',
            ]
        );

        $userExisted = User::where('email', $email)->exists();

        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => 'Client User',
                'password' => $password,
                'role' => User::ROLE_CLIENT,
                'status' => User::STATUS_ACTIVE,
                'client_id' => $client->id,
                'client_role' => User::CLIENT_ROLE_ADMINISTRATOR,
            ]
        );

        if (! $userExisted && ! env('CLIENT_SEED_PASSWORD')) {
            $this->command?->warn("Demo client created: {$email} / {$password}");
            $this->command?->warn('Save this password now — it will not be shown again. Set CLIENT_SEED_PASSWORD to choose your own.');
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        app(ClientWorkspaceService::class)->syncClientUser($user->fresh());

        $workspaceId = $user->fresh()->workspace_id;
        if (! $workspaceId) {
            return;
        }

        // Optionally seed a workspace SMTP connection from the environment.
        // Ships empty by default — mail is configured from the app UI.
        $smtpHost = env('SEED_SMTP_HOST');
        $smtpUsername = env('SEED_SMTP_USERNAME');
        $smtpPassword = env('SEED_SMTP_PASSWORD');
        if ($smtpHost && $smtpUsername && $smtpPassword) {
            WorkspaceSmtpConfig::firstOrCreate(
                ['workspace_id' => $workspaceId, 'username' => $smtpUsername],
                [
                    'host' => $smtpHost,
                    'port' => (int) env('SEED_SMTP_PORT', 587),
                    'password' => $smtpPassword,
                    'encryption' => env('SEED_SMTP_ENCRYPTION', 'tls'),
                    'from_email' => env('SEED_SMTP_FROM_EMAIL', 'noreply@example.com'),
                    'from_name' => env('SEED_SMTP_FROM_NAME', env('APP_NAME', 'CREASY CRM')),
                    'is_active' => true,
                ]
            );
        }

        $waToken = env('CLIENT_SEED_WHATSAPP_SYSTEM_USER_TOKEN');
        $wabaId = env('CLIENT_SEED_WABA_ID');
        if (is_string($waToken) && $waToken !== '' && is_string($wabaId) && $wabaId !== '') {
            WhatsappBusinessAccount::updateOrCreate(
                ['workspace_id' => $workspaceId],
                [
                    'waba_id' => $wabaId,
                    'credentials' => ['system_user_token' => $waToken],
                    'webhook_verify_token' => Str::random(48),
                    'status' => 'active',
                ]
            );
        }

        Contact::firstOrCreate(
            [
                'workspace_id' => $workspaceId,
                'phone_e164' => '+15555550100',
            ],
            [
                'first_name' => 'Demo',
                'last_name' => 'Contact',
                'opt_in_whatsapp' => true,
                'opt_in_sms' => false,
                'opt_in_email' => false,
                'country' => 'US',
                'source' => 'seed',
            ]
        );
    }
}
