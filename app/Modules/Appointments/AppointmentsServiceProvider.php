<?php

namespace App\Modules\Appointments;

use Illuminate\Support\ServiceProvider;

class AppointmentsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/routes/web.php');
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
    }
}
