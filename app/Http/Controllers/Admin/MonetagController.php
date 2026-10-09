<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdServing;
use App\Services\AuditLogger;
use App\Services\Monetag;
use App\Services\SettingsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class MonetagController extends Controller
{
    public function __construct(protected SettingsService $settings, protected AuditLogger $audit)
    {
    }

    public function index(AdServing $serving)
    {
        $swFile = is_file(public_path('sw.js')) ? (string) @file_get_contents(public_path('sw.js')) : null;
        return view('admin.monetization.monetag', [
            'serving' => $serving,
            'formats' => Monetag::FORMATS,
            'swFileOk' => $swFile !== null && trim($swFile) === trim((string) setting('monetag.sw_js')),
        ]);
    }

    public function update(Request $request)
    {
        $rules = [
            'verify_meta' => ['nullable', 'string', 'max:200'],
            'sw_js' => 'nullable|string|max:'.Monetag::MAX_SW_LENGTH,
            'directlink_url' => ['nullable', 'url', 'max:1000', 'regex:~^https://~i'],
            'directlink_label' => 'nullable|string|max:60',
        ];
        foreach (array_keys(Monetag::FORMATS) as $f) {
            $rules["{$f}_code"] = 'nullable|string|max:'.Monetag::MAX_TAG_LENGTH;
        }
        $data = $request->validate($rules, ['directlink_url.regex' => 'Direct Link must be an https:// URL.']);

        // Verification: accept either the bare content value or the whole <meta name="monetag" content="…"> tag.
        $verify = trim((string) ($data['verify_meta'] ?? ''));
        if ($verify !== '' && preg_match('~content\s*=\s*["\']([^"\']+)["\']~i', $verify, $m)) {
            $verify = $m[1];
        }
        if ($verify !== '' && ! preg_match('~^[A-Za-z0-9_\-.:]{4,120}$~', $verify)) {
            return back()->withErrors(['verify_meta' => 'Paste the Monetag verification meta tag, or only its content="…" value.'])->withInput();
        }

        foreach (array_keys(Monetag::FORMATS) as $f) {
            $data["{$f}_code"] = Monetag::normalize($data["{$f}_code"] ?? null);
            if ($problems = Monetag::tagProblems($data["{$f}_code"])) {
                return back()->withErrors(["{$f}_code" => Monetag::FORMATS[$f][0].': '.implode(' ', $problems)])->withInput();
            }
        }
        $sw = trim(str_replace("\r\n", "\n", (string) ($data['sw_js'] ?? '')));
        if ($problems = Monetag::serviceWorkerProblems($sw)) {
            return back()->withErrors(['sw_js' => implode(' ', $problems)])->withInput();
        }
        $label = trim((string) ($data['directlink_label'] ?? '')) ?: 'Sponsored offer';
        if (preg_match('/download|play|watch|click|continue|next|free|virus|update/i', $label)) {
            return back()->withErrors(['directlink_label' => 'Use a neutral label such as "Sponsored offer". Labels that look like Download / Play / Next buttons are deceptive.'])->withInput();
        }

        $pairs = [
            'monetag.enabled' => $request->boolean('enabled'),
            'monetag.require_consent' => $request->boolean('require_consent'),
            'monetag.verify_meta' => $verify,
            'monetag.sw_js' => $sw,
            'monetag.directlink_enabled' => $request->boolean('directlink_enabled') && trim((string) ($data['directlink_url'] ?? '')) !== '',
            'monetag.directlink_url' => trim((string) ($data['directlink_url'] ?? '')),
            'monetag.directlink_label' => $label,
        ];
        foreach (array_keys(Monetag::FORMATS) as $f) {
            $pairs["monetag.{$f}_code"] = $data["{$f}_code"];
            $pairs["monetag.{$f}_enabled"] = $request->boolean("{$f}_enabled") && $data["{$f}_code"] !== '';
        }

        $before = [];
        foreach ($pairs as $k => $v) {
            $before[$k] = setting($k);
        }
        $this->settings->setMany($pairs, 'monetag');
        Cache::forget('site.nav');
        $this->audit->log('adsense_changed', 'monetization', null, 'Monetag settings updated', $before, $pairs);

        $warnings = [];
        if ($err = Monetag::writeServiceWorker($sw)) {
            $warnings[] = $err;
        }
        if ($pairs['monetag.push_enabled'] && $sw === '') {
            $warnings[] = 'Push Notifications are on but sw.js is empty. Paste the sw.js file from Monetag, otherwise push will not work.';
        }
        if ($pairs['monetag.multitag_enabled'] && collect(['onclick', 'inpage', 'push', 'vignette'])->contains(fn ($f) => $pairs["monetag.{$f}_enabled"])) {
            $warnings[] = 'MultiTag already includes OnClick, Push, In-Page Push and Vignette. Running single formats as well shows the same ads twice.';
        }
        $popunders = setting_bool('adsterra.enabled') && setting_bool('adsterra.popunder_enabled');
        if ($pairs['monetag.enabled'] && $popunders && ($pairs['monetag.onclick_enabled'] || $pairs['monetag.multitag_enabled'])) {
            $warnings[] = 'Adsterra Popunder is also on. Two popunder networks open two ad tabs per click, which annoys visitors and lowers revenue per click.';
        }
        if ($pairs['monetag.enabled'] && setting_bool('adsense.enabled')) {
            $warnings[] = 'AdSense is enabled, so Monetag stays OFF on the site. It starts automatically when AdSense is disabled.';
        }

        $redirect = back()->with('success', 'Monetag settings saved.');
        return $warnings ? $redirect->with('warning', implode(' ', $warnings)) : $redirect;
    }
}
