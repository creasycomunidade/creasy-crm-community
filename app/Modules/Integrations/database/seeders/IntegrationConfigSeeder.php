<?php

namespace App\Modules\Integrations\Database\Seeders;

use App\Modules\Integrations\Models\IntegrationConfig;
use Illuminate\Database\Seeder;

class IntegrationConfigSeeder extends Seeder
{
    public function run(): void
    {
        foreach (IntegrationConfig::PROVIDERS as $provider) {
            IntegrationConfig::firstOrCreate(
                ['provider' => $provider, 'mode' => 'live'],
                [
                    'label' => IntegrationConfig::LABELS[$provider] ?? $provider,
                    // Enable local storage by default so the app has a working disk
                    'enabled' => $provider === 'storage_local',
                    'credentials' => [],
                ]
            );
        }
    }
}
