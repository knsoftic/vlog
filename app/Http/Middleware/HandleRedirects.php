<?php

namespace App\Http\Middleware;

use App\Models\Redirect;
use App\Models\SystemLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/** Admin-managed redirects + 404 logging for the system health screen. */
class HandleRedirects
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('GET') && ! $request->is('admin*', 'api/*', 'storage/*', 'build/*')) {
            $path = Redirect::normalizePath($request->path());
            try {
                $map = Cache::remember('redirects.map', 600, fn () => Redirect::where('is_active', true)->get()->keyBy(fn ($r) => Redirect::normalizePath($r->from_path)));
            } catch (\Throwable) {
                $map = collect(); // database not migrated yet
            }
            if (isset($map[$path])) {
                $r = $map[$path];
                Redirect::whereKey($r->id)->increment('hits');
                $to = $r->to_path;
                if ($request->getQueryString() && ! str_contains($to, '?')) {
                    $to .= '?'.$request->getQueryString();
                }
                return redirect($to, in_array((int) $r->status_code, [301, 302, 307, 308], true) ? (int) $r->status_code : 301);
            }
        }

        $response = $next($request);

        if ($response->getStatusCode() === 404 && $request->isMethod('GET') && ! $request->is('storage/*', 'build/*', 'favicon.ico', '*.map', 'wp-*', '*.php')) {
            SystemLog::record('404', 'Page not found', ['method' => 'GET'], 'warning', $request->fullUrl(), $request->headers->get('referer'));
        }
        return $response;
    }
}
