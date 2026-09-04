<?php

namespace App\Http\Middleware;

use App\Services\AuditLogger;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Usage: ->middleware('permission:posts.edit') or 'permission:a|b' (any of). */
class CheckPermission
{
    public function handle(Request $request, Closure $next, string $permissions): Response
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->route('admin.login');
        }
        $needed = explode('|', $permissions);
        foreach ($needed as $p) {
            if ($user->hasPermission($p)) {
                return $next($request);
            }
        }
        app(AuditLogger::class)->security('unauthorized', 'warning', ['permission' => $permissions]);
        abort(403, 'You do not have permission to perform this action.');
    }
}
