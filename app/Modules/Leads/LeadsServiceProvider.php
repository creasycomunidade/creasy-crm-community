<?php

namespace App\Modules\Leads;

use Illuminate\Support\ServiceProvider;

class LeadsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/routes/web.php');
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
    }
}
