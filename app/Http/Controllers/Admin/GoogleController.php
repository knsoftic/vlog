<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use App\Services\GoogleOAuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class GoogleController extends Controller
{
    public function __construct(protected GoogleOAuthService $oauth, protected AuditLogger $audit)
    {
    }

    public function connect(string $service)
    {
        abort_unless(in_array($service, ['adsense', 'search_console'], true), 404);
        if (! $this->oauth->isConfigured()) {
            return redirect()->route('admin.settings.edit', 'google')->withErrors(['google' => 'Enter your Google OAuth Client ID and Secret first.']);
        }
        return redirect()->away($this->oauth->authUrl($service));
    }

    public function callback(Request $request)
    {
        if ($request->filled('error')) {
            return redirect()->route('admin.settings.edit', 'google')->withErrors(['google' => 'Google authorisation was cancelled: '.$request->error]);
        }
        try {
            $service = $this->oauth->handleCallback((string) $request->code, (string) $request->state);
        } catch (\Throwable $e) {
            return redirect()->route('admin.settings.edit', 'google')->withErrors(['google' => $e->getMessage()]);
        }
        $this->audit->log('google_connected', 'settings', null, ucfirst(str_replace('_', ' ', $service)).' connected via OAuth');
        $target = $service === 'adsense' ? route('admin.monetization.dashboard') : route('admin.seo.search-console');
        // Kick off an initial sync (best-effort)
        try {
            Artisan::call('google:sync', ['service' => $service]);
        } catch (\Throwable) {
        }
        return redirect($target)->with('success', 'Google account connected. Initial sync started.');
    }

    public function disconnect(string $service)
    {
        abort_unless(in_array($service, ['adsense', 'search_console'], true), 404);
        $this->oauth->disconnect($service);
        $this->audit->log('google_disconnected', 'settings', null, ucfirst(str_replace('_', ' ', $service)).' disconnected');
        return back()->with('success', 'Disconnected.');
    }

    public function sync(string $service)
    {
        abort_unless(in_array($service, ['adsense', 'search_console'], true), 404);
        Artisan::call('google:sync', ['service' => $service]);
        $out = trim(Artisan::output());
        $this->audit->log('google_sync', 'settings', null, "Manual sync ({$service}): ".$out);
        return back()->with(str_contains(strtolower($out), 'fail') ? 'error' : 'success', $out ?: 'Sync finished.');
    }
}
