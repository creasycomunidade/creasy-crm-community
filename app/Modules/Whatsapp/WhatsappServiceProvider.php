<?php

namespace App\Modules\Whatsapp;

use App\Modules\Shared\Services\ChannelManager;
use App\Modules\Whatsapp\Services\WhatsappDriver;
use Illuminate\Support\ServiceProvider;

class WhatsappServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/routes/web.php');
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');

        // Register the WhatsApp channel driver so the inbox can resolve it
        // when sending outbound replies via ChannelManager::driver('whatsapp').
        $this->app->make(ChannelManager::class)
            ->register('whatsapp', WhatsappDriver::class);
    }
}
