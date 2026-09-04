<?php

use App\Http\Middleware\AdminAccess;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\HandleRedirects;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\TrackPageView;
use App\Http\Middleware\TrafficProtection;
use App\Models\SystemLog;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Global so that admin-managed redirects and 404 logging also work for URLs that match no route.
        $middleware->append([
            SecurityHeaders::class,
            HandleRedirects::class,
        ]);
        $middleware->alias([
            'admin' => AdminAccess::class,
            'permission' => CheckPermission::class,
            'track' => TrackPageView::class,
            'traffic' => TrafficProtection::class,
        ]);
        // The consent cookie is read by the browser-side script, so it must stay plain text (it holds no personal data).
        $middleware->encryptCookies(except: ['vh_consent']);
        $middleware->redirectGuestsTo(fn () => route('admin.login'));
        $middleware->redirectUsersTo(fn () => route('admin.dashboard'));
        $middleware->validateCsrfTokens(except: [
            'api/track/*',
        ]);
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->report(function (\Throwable $e) {
            if ($e instanceof NotFoundHttpException) {
                return;
            }
            $status = $e instanceof HttpExceptionInterface ? $e->getStatusCode() : 500;
            if ($status >= 500) {
                SystemLog::record(
                    $status === 500 ? '500' : 'exception',
                    get_class($e).': '.$e->getMessage(),
                    ['file' => basename($e->getFile()), 'line' => $e->getLine()],
                    'error',
                    request()?->fullUrl(),
                    request()?->headers->get('referer')
                );
            }
        });
    })->create();
