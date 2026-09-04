<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Consent;
use App\Services\GeoService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Stores the visitor's cookie preferences (necessary / analytics / advertising).
 * Cookie format: vh_consent = v1.{analytics}{advertising}{personalization}
 */
class ConsentController extends Controller
{
    public function store(Request $request, GeoService $geo)
    {
        $data = $request->validate([
            'analytics' => 'required|boolean',
            'advertising' => 'required|boolean',
            'personalization' => 'nullable|boolean',
            'method' => 'nullable|in:banner,preferences,tcf',
            'tc_string' => 'nullable|string|max:4000',
        ]);
        $key = $request->cookie('vh_consent_id');
        if (! $key || ! preg_match('/^[A-Za-z0-9]{32,40}$/', $key)) {
            $key = Str::random(40);
        }
        $country = $geo->resolve($request)['country'];
        Consent::updateOrCreate(['consent_key' => $key], [
            'necessary' => true,
            'analytics' => (bool) $data['analytics'],
            'advertising' => (bool) $data['advertising'],
            'personalization' => (bool) ($data['personalization'] ?? $data['advertising']),
            'region' => $country,
            'method' => $data['method'] ?? 'banner',
            'tc_string' => $data['tc_string'] ?? null,
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
        ]);
        $value = 'v1.'.((int) $data['analytics']).((int) $data['advertising']).((int) ($data['personalization'] ?? $data['advertising']));
        $days = max(30, (int) setting('consent.retention_days', 365));
        return response()->json(['ok' => true, 'consent' => $value])
            ->cookie('vh_consent', $value, 60 * 24 * $days, '/', null, $request->isSecure(), false, false, 'Lax')
            ->cookie('vh_consent_id', $key, 60 * 24 * $days, '/', null, $request->isSecure(), true, false, 'Lax');
    }
}
