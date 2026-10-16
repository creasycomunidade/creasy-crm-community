<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class CronSetupController extends Controller
{
    /**
     * Cache key written every minute by the scheduler heartbeat (see routes/console.php).
     * Reading it back lets the admin verify their cron entry is actually firing.
     */
    public const HEARTBEAT_KEY = 'admin:scheduler_last_run';

    public function index(): Response
    {
        return Inertia::render('Admin/CronSetup/Index', [
            'basePath'        => base_path(),
            'phpBinary'       => PHP_BINARY,
            'queueConnection' => (string) config('queue.default'),
            'tasks'           => $this->scheduledTasks(),
            'schedulerLastRun' => $this->heartbeat()?->toIso8601String(),
            'schedulerStatus'  => $this->status($this->heartbeat()),
        ]);
    }

    /**
     * The tasks currently registered in routes/console.php, so the guide always
     * reflects what the app actually runs rather than a hard-coded list.
     *
     * @return array<int, array{description: string, expression: string}>
     */
    private function scheduledTasks(): array
    {
        $tasks = [];

        try {
            foreach (app(Schedule::class)->events() as $event) {
                $tasks[] = [
                    'description' => $event->description ?: $event->getSummaryForDisplay(),
                    'expression'  => $event->expression,
                ];
            }
        } catch (\Throwable) {
            // Schedule could not be resolved — fall back to an empty list.
        }

        return $tasks;
    }

    private function heartbeat(): ?Carbon
    {
        $raw = Cache::get(self::HEARTBEAT_KEY);

        if (! $raw) {
            return null;
        }

        try {
            return Carbon::parse($raw);
        } catch (\Throwable) {
            return null;
        }
    }

    private function status(?Carbon $lastRun): string
    {
        if (! $lastRun) {
            return 'inactive';
        }

        $secondsAgo = $lastRun->diffInSeconds(now());

        return match (true) {
            $secondsAgo <= 120  => 'active',
            $secondsAgo <= 3600 => 'stale',
            default             => 'inactive',
        };
    }
}
