<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceLimit
{
    public function handle(Request $request, Closure $next, string $limitKey, string $countKey = ''): Response
    {
        return $next($request);
    }
}
