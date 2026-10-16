<?php

namespace Database\Seeders;

use App\Models\SmtpConfiguration;
use Illuminate\Database\Seeder;

class SmtpConfigurationSeeder extends Seeder
{
    public function run(): void
    {
        // SMTP is normally configured by the administrator from the admin panel.
        // Only seed a default SMTP connection when credentials are supplied via
        // the environment, so a fresh install ships without any baked-in mail
        // account.
        $host = env('SEED_SMTP_HOST');
        $username = env('SEED_SMTP_USERNAME');
        $password = env('SEED_SMTP_PASSWORD');

        if (! $host || ! $username || ! $password) {
            return;
        }

        SmtpConfiguration::firstOrCreate(
            ['host' => $host, 'username' => $username],
            [
                'port' => (int) env('SEED_SMTP_PORT', 587),
                'password' => $password,
                'encryption' => env('SEED_SMTP_ENCRYPTION', 'tls'),
                'from_email' => env('SEED_SMTP_FROM_EMAIL', 'noreply@example.com'),
                'from_name' => env('SEED_SMTP_FROM_NAME', env('APP_NAME', 'CREASY CRM')),
                'is_active' => true,
            ]
        );
    }
}
