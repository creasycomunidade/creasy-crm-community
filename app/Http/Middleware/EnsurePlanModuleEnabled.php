<?php

namespace App\Http\Middleware;

use App\Support\CommunityEdition;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlanModuleEnabled
{
    public function handle(Request $request, Closure $next, string $module): Response
    {
        abort_unless(CommunityEdition::allows($module), 404);
        return $next($request);
    }
}
