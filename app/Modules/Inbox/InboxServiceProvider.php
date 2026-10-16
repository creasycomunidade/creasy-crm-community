<?php

namespace App\Modules\Inbox;

use App\Modules\Inbox\Services\InstagramDriver;
use App\Modules\Inbox\Services\MessengerDriver;
use App\Modules\Inbox\Services\WebchatDriver;
use App\Modules\Shared\Services\ChannelManager;
use Illuminate\Support\ServiceProvider;

class InboxServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/routes/web.php');
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');

        // Register Messenger / Instagram drivers so the inbox can dispatch
        // outbound replies via ChannelManager::driver(...) without errors.
        $manager = $this->app->make(ChannelManager::class);
        $manager->register('messenger', MessengerDriver::class);
        $manager->register('instagram', InstagramDriver::class);
        $manager->register('webchat', WebchatDriver::class);
    }
}
