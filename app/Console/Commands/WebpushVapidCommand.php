<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;

class WebpushVapidCommand extends Command
{
    protected $signature = 'webpush:vapid';

    protected $description = 'Generate a VAPID key pair for Web Push notifications';

    public function handle(): int
    {
        try {
            $keys = VAPID::createVapidKeys();
        } catch (\Throwable $e) {
            $this->error('Failed to generate VAPID keys: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('');
        $this->info('VAPID key pair generated. Add these to your .env, then run `php artisan config:clear` and rebuild assets (`npm run build`):');
        $this->line('');
        $this->line('  <fg=cyan>VAPID_PUBLIC_KEY</>='.$keys['publicKey']);
        $this->line('  <fg=cyan>VAPID_PRIVATE_KEY</>='.$keys['privateKey']);
        $this->line('  <fg=cyan>VITE_VAPID_PUBLIC_KEY</>='.$keys['publicKey'].'  <fg=yellow># same as public key; the frontend reads this one</>');
        $this->line('');
        $this->warn('Keep the private key secret. Regenerating keys invalidates existing browser subscriptions (users must re-enable notifications).');

        return self::SUCCESS;
    }
}
