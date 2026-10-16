<?php

namespace App\Modules\Broadcasting;

use Illuminate\Support\ServiceProvider;

class BroadcastingServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/routes/web.php');
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
    }
}
