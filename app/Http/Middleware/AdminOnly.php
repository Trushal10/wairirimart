<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminOnly
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            abort(401);
        }
        $role = $user->role ?? null;
        if ($role !== 'admin') {
            abort(403, 'Admin access required.');
        }
        return $next($request);
    }
}
