<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        // Reject unauthenticated users and non-admin accounts.
        if (! $request->user()?->is_admin) {
            return response()->json([
                'message' => 'Access denied. Admin privileges required.',
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
