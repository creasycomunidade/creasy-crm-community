<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\CommunityRegistration;

class DeliverCommunityRegistration
{
    public function handle(Request $request, Closure $next)
    {
        return $next($request);
    }

    public function terminate(Request $request, $response): void
    {
        if (config('app.installed')) app(CommunityRegistration::class)->deliver();
    }
}
