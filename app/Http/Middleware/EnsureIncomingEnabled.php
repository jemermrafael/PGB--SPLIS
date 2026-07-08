<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureIncomingEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('incoming.enabled', true)) {
            abort(404);
        }

        return $next($request);
    }
}
