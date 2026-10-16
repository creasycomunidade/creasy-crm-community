<?php

namespace App\Providers;

use App\Models\SystemSetting;
use Illuminate\Support\ServiceProvider;

class PusherSettingsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->app->booted(function () {
            try {
                $key     = SystemSetting::get('pusher_app_key');
                $secret  = SystemSetting::get('pusher_app_secret');
                $appId   = SystemSetting::get('pusher_app_id');
                $cluster = SystemSetting::get('pusher_app_cluster');
                $enabled = SystemSetting::get('pusher_enabled', 'false');

                if ($enabled === 'true' && $key && $secret && $appId) {
                    config([
                        'broadcasting.default' => 'pusher',
                        'broadcasting.connections.pusher.key' => $key,
                        'broadcasting.connections.pusher.secret' => $secret,
                        'broadcasting.connections.pusher.app_id' => $appId,
                        'broadcasting.connections.pusher.options.cluster' => $cluster ?: 'mt1',
                        'broadcasting.connections.pusher.options.host' => 'api-'.($cluster ?: 'mt1').'.pusher.com',
                    ]);
                }
            } catch (\Throwable) {
                // DB not ready during migrations — skip silently
            }
        });
    }
}
