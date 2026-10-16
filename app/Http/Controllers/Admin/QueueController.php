<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class QueueController extends Controller
{
    public function index(Request $request)
    {
        $tab = $request->get('tab', 'failed');

        $failedJobs = collect();
        $batchRows  = collect();

        if ($tab === 'failed') {
            try {
                $failedJobs = DB::table('failed_jobs')
                    ->orderByDesc('failed_at')
                    ->paginate(20)
                    ->through(fn ($job) => [
                        'id'         => $job->id,
                        'uuid'       => $job->uuid ?? null,
                        'queue'      => $job->queue,
                        'class'      => $this->extractClass($job->payload),
                        'exception'  => mb_substr($job->exception ?? '', 0, 300),
                        'failed_at'  => $job->failed_at,
                    ]);
            } catch (\Throwable) {
                // Table may not exist yet
            }
        }

        if ($tab === 'batches') {
            try {
                $batchRows = DB::table('job_batches')
                    ->orderByDesc('created_at')
                    ->paginate(20)
                    ->through(fn ($b) => [
                        'id'              => $b->id,
                        'name'            => $b->name,
                        'total_jobs'      => $b->total_jobs,
                        'pending_jobs'    => $b->pending_jobs,
                        'failed_jobs'     => $b->failed_jobs,
                        'failed_job_ids'  => $b->failed_job_ids,
                        'created_at'      => $b->created_at,
                        'cancelled_at'    => $b->cancelled_at,
                        'finished_at'     => $b->finished_at,
                    ]);
            } catch (\Throwable) {
            }
        }

        return Inertia::render('Admin/Queue/Index', [
            'tab'        => $tab,
            'failedJobs' => $failedJobs,
            'batches'    => $batchRows,
        ]);
    }

    public function retryFailed(string $id)
    {
        try {
            \Artisan::call('queue:retry', ['id' => [$id]]);
            return back()->with('success', 'Job queued for retry.');
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function deleteFailed(string $id)
    {
        try {
            DB::table('failed_jobs')->where('id', $id)->delete();
            return back()->with('success', 'Failed job deleted.');
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function retryAll()
    {
        try {
            \Artisan::call('queue:retry', ['id' => ['all']]);
            return back()->with('success', 'All failed jobs queued for retry.');
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function flushFailed()
    {
        try {
            \Artisan::call('queue:flush');
            return back()->with('success', 'All failed jobs deleted.');
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    private function extractClass(string $payload): string
    {
        try {
            $decoded = json_decode($payload, true);
            return $decoded['displayName'] ?? $decoded['job'] ?? 'Unknown';
        } catch (\Throwable) {
            return 'Unknown';
        }
    }
}
