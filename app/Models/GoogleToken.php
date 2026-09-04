<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class GoogleToken extends Model
{
    protected $fillable = [
        'service', 'access_token', 'refresh_token', 'expires_at', 'scopes', 'account_id', 'account_label',
        'connected_by', 'connected_at', 'last_synced_at', 'last_status', 'last_error',
    ];

    protected $casts = ['expires_at' => 'datetime', 'connected_at' => 'datetime', 'last_synced_at' => 'datetime'];

    protected $hidden = ['access_token', 'refresh_token'];

    // Tokens are always stored encrypted at rest.
    public function setAccessTokenAttribute(?string $v): void
    {
        $this->attributes['access_token'] = $v ? Crypt::encryptString($v) : null;
    }

    public function getAccessTokenAttribute(?string $v): ?string
    {
        try {
            return $v ? Crypt::decryptString($v) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public function setRefreshTokenAttribute(?string $v): void
    {
        $this->attributes['refresh_token'] = $v ? Crypt::encryptString($v) : null;
    }

    public function getRefreshTokenAttribute(?string $v): ?string
    {
        try {
            return $v ? Crypt::decryptString($v) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public function isConnected(): bool
    {
        return (bool) $this->refresh_token;
    }

    public function isExpired(): bool
    {
        return ! $this->expires_at || $this->expires_at->subMinute()->isPast();
    }
}
