<?php

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Channel authorisation callbacks are registered in
| App\Providers\BroadcastChannelsServiceProvider via an app->booted()
| callback.  This ensures they are registered on the correct broadcaster
| driver AFTER PusherSettingsServiceProvider has (optionally) switched the
| default from 'reverb' to 'pusher' using credentials stored in the DB.
|
| Do NOT add Broadcast::channel() calls here — they run during early boot
| before the driver switch and would land on the wrong broadcaster instance,
| causing 403 AccessDeniedHttpException for every private channel.
|
*/
