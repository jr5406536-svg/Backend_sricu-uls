<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! $user->is_active) {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        if ($user->isAdmin() || $user->hasAnyRole($roles)) {
            return $next($request);
        }

        return response()->json(['message' => 'No autorizado.'], 403);
    }
}
