<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Only active users with a role may enter the admin panel. */
class AdminAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->guest(route('admin.login'));
        }
        if (! $user->is_active || ! $user->role) {
            auth()->logout();
            $request->session()->invalidate();
            return redirect()->route('admin.login')->withErrors(['email' => 'Your account is inactive.']);
        }
        return $next($request);
    }
}
