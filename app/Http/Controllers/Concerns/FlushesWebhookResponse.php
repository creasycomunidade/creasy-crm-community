<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * Sends a 200 JSON response immediately (PHP-FPM fastcgi_finish_request) so
 * Meta does not retry while the queued webhook job is dispatched.
 *
 * On non-FPM servers the response is returned normally after dispatch,
 * so jobs MUST use an async queue driver (redis/database) in production —
 * never QUEUE_CONNECTION=sync.
 */
trait FlushesWebhookResponse
{
    protected function flushWebhookOkThen(callable $afterResponse): JsonResponse
    {
        if (function_exists('fastcgi_finish_request')) {
            response()->json(['status' => 'ok'])->send();
            fastcgi_finish_request();

            try {
                $afterResponse();
            } catch (\Throwable $e) {
                Log::error('Webhook post-flush dispatch failed', [
                    'error' => $e->getMessage(),
                    'file'  => $e->getFile(),
                    'line'  => $e->getLine(),
                ]);
            }

            // Response already flushed; this return value is never used by PHP-FPM.
            return response()->json(['status' => 'ok']);
        }

        // Non-FPM path: dispatch the job (must be async) then return.
        $afterResponse();

        return response()->json(['status' => 'ok']);
    }
}
