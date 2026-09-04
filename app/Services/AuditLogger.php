<?php

namespace App\Services;

use App\Models\AdminLog;
use App\Models\SecurityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class AuditLogger
{
    /** Attribute names that must never be written to logs. */
    public const REDACT = [
        'password', 'password_confirmation', 'remember_token', 'access_token', 'refresh_token', 'client_secret',
        'google.client_secret', 'mail.password', 'smtp.password', 'api_key', 'secret', 'token', '_token',
    ];

    public function log(string $action, ?string $module = null, ?Model $model = null, ?string $description = null, ?array $before = null, ?array $after = null): void
    {
        try {
            $req = request();
            $user = Auth::user();
            $ua = new UserAgentParser($req->userAgent() ?? '');
            AdminLog::create([
                'user_id' => $user?->id,
                'user_name' => $user?->name,
                'action' => $action,
                'module' => $module,
                'model_type' => $model ? get_class($model) : null,
                'model_id' => $model?->getKey(),
                'description' => $description ? mb_substr($description, 0, 500) : null,
                'before' => $before !== null ? $this->redact($before) : null,
                'after' => $after !== null ? $this->redact($after) : null,
                'ip' => $this->ipForStorage($req->ip()),
                'user_agent' => mb_substr((string) $req->userAgent(), 0, 500),
                'device' => $ua->browser().' / '.$ua->os().' / '.$ua->deviceType(),
                'created_at' => now(),
            ]);
        } catch (\Throwable) {
            // logging must never break the request
        }
    }

    /** Log a model update with only changed attributes (before/after). */
    public function logModelChange(string $action, string $module, Model $model, array $original, string $description = ''): void
    {
        $changes = [];
        $before = [];
        foreach ($model->getChanges() as $k => $v) {
            if (in_array($k, ['updated_at'], true)) {
                continue;
            }
            $before[$k] = $original[$k] ?? null;
            $changes[$k] = $v;
        }
        $this->log($action, $module, $model, $description, $before, $changes);
    }

    public function security(string $type, string $severity = 'info', array $details = [], ?string $email = null, ?int $userId = null): void
    {
        try {
            $req = request();
            SecurityLog::create([
                'type' => $type,
                'severity' => $severity,
                'user_id' => $userId ?? Auth::id(),
                'email' => $email,
                'ip' => $this->ipForStorage($req->ip()),
                'user_agent' => mb_substr((string) $req->userAgent(), 0, 500),
                'path' => mb_substr($req->path(), 0, 500),
                'details' => $this->redact($details),
                'created_at' => now(),
            ]);
        } catch (\Throwable) {
        }
    }

    public function redact(array $data): array
    {
        foreach ($data as $k => $v) {
            $lk = strtolower((string) $k);
            foreach (self::REDACT as $r) {
                if ($lk === $r || str_contains($lk, 'password') || str_contains($lk, 'secret') || str_ends_with($lk, '_token')) {
                    $data[$k] = '[REDACTED]';
                    continue 2;
                }
            }
            if (is_array($v)) {
                $data[$k] = $this->redact($v);
            } elseif (is_string($v) && mb_strlen($v) > 2000) {
                $data[$k] = mb_substr($v, 0, 2000).'…';
            }
        }
        return $data;
    }

    /**
     * IPs in security/admin logs are kept in full only for the configured security retention window;
     * the retention job anonymises them afterwards.
     */
    public function ipForStorage(?string $ip): ?string
    {
        return $ip ? mb_substr($ip, 0, 64) : null;
    }
}
