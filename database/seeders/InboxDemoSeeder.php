<?php

namespace Database\Seeders;

use App\Modules\Shared\Models\ChannelAccount;
use App\Modules\Whatsapp\Models\WhatsappBusinessAccount;
use App\Modules\Whatsapp\Models\WhatsappPhoneNumber;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class InboxDemoSeeder extends Seeder
{
    public function run(): void
    {
        $workspaceId = 1;

        $this->seedWhatsapp($workspaceId);
        $this->seedInstagram($workspaceId);
        $this->seedMessenger($workspaceId);
    }

    private function seedWhatsapp(int $workspaceId): void
    {
        $waba = WhatsappBusinessAccount::firstOrCreate(
            ['workspace_id' => $workspaceId, 'waba_id' => 'DEMO_WABA_123456789'],
            [
                'credentials' => [
                    'system_user_token' => 'DEMO_WA_TOKEN_placeholder',
                    'app_secret_override' => null,
                ],
                'webhook_verify_token' => 'demo-whatsapp-verify-token-abc123',
                'status' => 'active',
                'meta_json' => ['note' => 'Demo account — no real API calls'],
            ]
        );

        WhatsappPhoneNumber::firstOrCreate(
            ['phone_number_id' => 'DEMO_PHONE_ID_111222333'],
            [
                'waba_id_fk' => $waba->id,
                'display_phone' => '+1 555-DEMO-WA',
                'verified_name' => 'Acme Demo WA',
                'quality_rating' => 'GREEN',
                'messaging_limit_tier' => 'TIER_1K',
                'code_verification_status' => 'VERIFIED',
            ]
        );

        ChannelAccount::firstOrCreate(
            ['workspace_id' => $workspaceId, 'channel' => 'whatsapp', 'phone_number_id' => 'DEMO_PHONE_ID_111222333'],
            [
                'provider' => 'meta',
                'display_name' => 'Acme WA Demo',
                'business_account_id' => 'DEMO_WABA_123456789',
                'credentials' => ['system_user_token' => 'DEMO_WA_TOKEN_placeholder'],
                'status' => 'active',
                'meta_json' => ['phone_number_id' => 'DEMO_PHONE_ID_111222333'],
            ]
        );

        $this->command->info('WhatsApp demo account seeded — phone_number_id: DEMO_PHONE_ID_111222333');
    }

    private function seedInstagram(int $workspaceId): void
    {
        $existing = ChannelAccount::where('workspace_id', $workspaceId)
            ->where('channel', 'instagram')
            ->whereJsonContains('meta_json->instagram_page_id', 'DEMO_IG_PAGE_ID_444555666')
            ->first();

        if (! $existing) {
            ChannelAccount::create([
                'workspace_id' => $workspaceId,
                'channel' => 'instagram',
                'provider' => 'meta',
                'display_name' => 'Acme IG Demo',
                'credentials' => [
                    'access_token' => 'DEMO_IG_TOKEN_placeholder',
                    'instagram_account_id' => 'DEMO_IG_ACCOUNT_ID_999888777',
                ],
                'status' => 'active',
                'meta_json' => [
                    'instagram_page_id' => 'DEMO_IG_PAGE_ID_444555666',
                    'instagram_account_id' => 'DEMO_IG_ACCOUNT_ID_999888777',
                ],
            ]);
        }

        $this->command->info('Instagram demo account seeded — instagram_page_id: DEMO_IG_PAGE_ID_444555666');
    }

    private function seedMessenger(int $workspaceId): void
    {
        $existing = ChannelAccount::where('workspace_id', $workspaceId)
            ->where('channel', 'messenger')
            ->whereJsonContains('meta_json->page_id', 'DEMO_MESSENGER_PAGE_ID_777888999')
            ->first();

        if (! $existing) {
            ChannelAccount::create([
                'workspace_id' => $workspaceId,
                'channel' => 'messenger',
                'provider' => 'meta',
                'display_name' => 'Acme Messenger Demo',
                'credentials' => [
                    'page_access_token' => 'DEMO_MESSENGER_TOKEN_placeholder',
                ],
                'status' => 'active',
                'meta_json' => [
                    'page_id' => 'DEMO_MESSENGER_PAGE_ID_777888999',
                ],
            ]);
        }

        $this->command->info('Messenger demo account seeded — page_id: DEMO_MESSENGER_PAGE_ID_777888999');
    }
}
