<?php

namespace App\Services;

use App\Models\GoogleToken;
use Google\Client as GoogleClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Secure Google OAuth 2.0 handling for AdSense Management API and Search Console API.
 * Client secret lives in settings (encrypted) or .env; refresh tokens are encrypted at rest.
 * Nothing here is ever exposed to the public frontend.
 */
class GoogleOAuthService
{
    public const SCOPES = [
        'adsense' => ['https://www.googleapis.com/auth/adsense.readonly'],
        'search_console' => ['https://www.googleapis.com/auth/webmasters.readonly'],
    ];

    public function isConfigured(): bool
    {
        return (bool) ($this->clientId() && $this->clientSecret());
    }

    public function clientId(): ?string
    {
        return setting('google.client_id') ?: config('services.google.client_id');
    }

    protected function clientSecret(): ?string
    {
        return setting('google.client_secret') ?: config('services.google.client_secret');
    }

    public function redirectUri(): string
    {
        return route('admin.google.callback');
    }

    public function makeClient(?string $service = null): GoogleClient
    {
        $client = new GoogleClient;
        $client->setApplicationName(config('app.name'));
        $client->setClientId((string) $this->clientId());
        $client->setClientSecret((string) $this->clientSecret());
        $client->setRedirectUri($this->redirectUri());
        $client->setAccessType('offline');
        $client->setPrompt('consent');
        $client->setIncludeGrantedScopes(true);
        if ($service) {
            $client->setScopes(self::SCOPES[$service]);
        }
        return $client;
    }

    public function authUrl(string $service): string
    {
        $client = $this->makeClient($service);
        $state = Str::random(40);
        Cache::put('google_oauth_state:'.$state, ['service' => $service, 'user' => auth()->id()], 600);
        $client->setState($state);
        return $client->createAuthUrl();
    }

    /** Exchange the code, store encrypted tokens. Returns the service name. */
    public function handleCallback(string $code, string $state): string
    {
        $meta = Cache::pull('google_oauth_state:'.$state);
        if (! $meta || ! isset($meta['service'])) {
            throw new \RuntimeException('Invalid or expired OAuth state. Please try connecting again.');
        }
        $service = $meta['service'];
        $client = $this->makeClient($service);
        $token = $client->fetchAccessTokenWithAuthCode($code);
        if (isset($token['error'])) {
            throw new \RuntimeException('Google OAuth error: '.($token['error_description'] ?? $token['error']));
        }
        $existing = GoogleToken::firstOrNew(['service' => $service]);
        $existing->access_token = $token['access_token'] ?? null;
        if (! empty($token['refresh_token'])) {
            $existing->refresh_token = $token['refresh_token'];
        }
        $existing->expires_at = now()->addSeconds((int) ($token['expires_in'] ?? 3600));
        $existing->scopes = $token['scope'] ?? implode(' ', self::SCOPES[$service]);
        $existing->connected_by = auth()->id();
        $existing->connected_at = now();
        $existing->last_status = 'ok';
        $existing->last_error = null;
        $existing->save();
        if (! $existing->refresh_token) {
            throw new \RuntimeException('Google did not return a refresh token. Remove the app from your Google account permissions and connect again.');
        }
        return $service;
    }

    /** Authorised client with a fresh access token, or null when not connected. */
    public function authorizedClient(string $service): ?GoogleClient
    {
        $token = GoogleToken::where('service', $service)->first();
        if (! $token || ! $token->isConnected() || ! $this->isConfigured()) {
            return null;
        }
        $client = $this->makeClient($service);
        $client->setAccessToken([
            'access_token' => $token->access_token ?? '',
            'refresh_token' => $token->refresh_token,
            'expires_in' => max(0, $token->expires_at ? now()->diffInSeconds($token->expires_at, false) : 0),
            'created' => time(),
        ]);
        if ($token->isExpired() || $client->isAccessTokenExpired()) {
            $new = $client->fetchAccessTokenWithRefreshToken($token->refresh_token);
            if (isset($new['error'])) {
                $token->update(['last_status' => 'failed', 'last_error' => 'Token refresh failed: '.($new['error_description'] ?? $new['error'])]);
                throw new \RuntimeException('Google token refresh failed: '.($new['error_description'] ?? $new['error']));
            }
            $token->access_token = $new['access_token'];
            $token->expires_at = now()->addSeconds((int) ($new['expires_in'] ?? 3600));
            if (! empty($new['refresh_token'])) {
                $token->refresh_token = $new['refresh_token'];
            }
            $token->save();
        }
        return $client;
    }

    public function disconnect(string $service): void
    {
        $token = GoogleToken::where('service', $service)->first();
        if ($token) {
            try {
                $client = $this->makeClient($service);
                if ($token->refresh_token) {
                    $client->revokeToken($token->refresh_token);
                }
            } catch (\Throwable) {
            }
            $token->delete();
        }
    }
}
