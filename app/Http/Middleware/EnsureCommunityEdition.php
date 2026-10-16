<?php

namespace App\Http\Middleware;

use App\Support\CommunityEdition;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCommunityEdition
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_if(CommunityEdition::blocksPath($request->path()), 404);
        return $next($request);
    }
}
