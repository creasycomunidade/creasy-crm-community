<?php

use App\Http\Controllers\Admin\CronSetupController;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

Schedule::call(function () {
    Cache::put(CronSetupController::HEARTBEAT_KEY, now()->toIso8601String(), now()->addDay());
})->everyMinute()->name('scheduler-heartbeat');

\Illuminate\Support\Facades\Artisan::command('messages:dispatch-scheduled', function () {
    app(\App\Modules\Inbox\Jobs\DispatchScheduledMessagesJob::class)->handleSynchronously();
});
Schedule::command('messages:dispatch-scheduled')->everyMinute()->name('dispatch-scheduled-conversation-messages')->withoutOverlapping();
Schedule::job(new \App\Modules\Appointments\Jobs\SendAppointmentRemindersJob)->everyMinute()->name('send-appointment-reminders')->withoutOverlapping();

Schedule::command('queue:work database --queue=whatsapp,notifications,automation,automations,ai,default --stop-when-empty --max-time=50 --sleep=1 --tries=3')
    ->everyMinute()->name('community-queues')->withoutOverlapping(3);
