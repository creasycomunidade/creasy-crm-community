<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * Auto-discovers any app/Modules/{Name}/ModuleServiceProvider.php and boots them.
 *
 * Each module must extend Illuminate\Support\ServiceProvider and may register
 * its own routes, nav items, and migrations.
 *
 * To create a new module use: php artisan saas:make-module {Name}
 */
class ModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $modulesPath = app_path('Modules');
        foreach (glob(database_path('community-schema/*'), GLOB_ONLYDIR) as $schemaPath) {
            if (! in_array(basename($schemaPath), ['Automation', 'Appointments'], true)) {
                $this->loadMigrationsFrom($schemaPath);
            }
        }

        if (! is_dir($modulesPath)) {
            return;
        }

        foreach (glob($modulesPath.'/*', GLOB_ONLYDIR) as $moduleDir) {
            $moduleName = basename($moduleDir);
            if (! in_array($moduleName, ['Shared', 'Inbox', 'Whatsapp', 'Telegram', 'Leads', 'AI', 'Automation', 'Appointments'], true)) {
                $this->loadMigrationsFrom($moduleDir.'/database/migrations');
                continue;
            }

            // Support both {Name}ServiceProvider.php (saas:make-module default) and ModuleServiceProvider.php
            $providerClass = class_exists('App\\Modules\\'.$moduleName.'\\'.$moduleName.'ServiceProvider')
                ? 'App\\Modules\\'.$moduleName.'\\'.$moduleName.'ServiceProvider'
                : 'App\\Modules\\'.$moduleName.'\\ModuleServiceProvider';

            if (class_exists($providerClass)) {
                $this->app->register($providerClass);
            }
        }
    }
}
