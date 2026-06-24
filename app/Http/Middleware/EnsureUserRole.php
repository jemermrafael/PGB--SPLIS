<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! $user->is_active) {
            abort(403);
        }

        $allowed = collect($roles)->map(fn ($r) => UserRole::from($r));

        if (! $allowed->contains($user->role)) {
            abort(403, 'You do not have permission to access this resource.');
        }

        return $next($request);
    }
}
