<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ClientAuthMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::guard('customer')->user();

        if (! $user) {
            return redirect()->route('client.login');
        }

        // A customer blocked by admin must not be able to continue with a
        // pre-existing session — log them out and bounce to login with a note.
        if ($user->isBlocked()) {
            Auth::guard('customer')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return redirect()->route('client.login')
                ->with('error', 'Your account has been blocked. Please contact support.');
        }

        return $next($request);
    }
}
