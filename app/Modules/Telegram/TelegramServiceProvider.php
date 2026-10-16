<?php

namespace App\Modules\Telegram;

use App\Modules\Shared\Services\ChannelManager;
use App\Modules\Telegram\Services\TelegramDriver;
use Illuminate\Support\ServiceProvider;

class TelegramServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/routes/web.php');

        $this->app->make(ChannelManager::class)
            ->register('telegram', TelegramDriver::class);
    }
}
