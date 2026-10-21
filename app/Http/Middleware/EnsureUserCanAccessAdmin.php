<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates the custom admin panel: the user must be authenticated, have an
 * "active" status, and hold one of the admin roles (User::canAccessAdminPanel()).
 */
class EnsureUserCanAccessAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->canAccessAdminPanel()) {
            abort(403);
        }

        return $next($request);
    }
}
