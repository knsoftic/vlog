<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JobRun extends Model
{
    public $timestamps = false;

    protected $fillable = ['name', 'status', 'started_at', 'finished_at', 'message'];

    protected $casts = ['started_at' => 'datetime', 'finished_at' => 'datetime'];

    public static function start(string $name): self
    {
        return static::create(['name' => $name, 'status' => 'running', 'started_at' => now()]);
    }

    public function finish(string $status = 'success', ?string $message = null): void
    {
        $this->update(['status' => $status, 'finished_at' => now(), 'message' => $message ? mb_substr($message, 0, 5000) : null]);
    }

    /** Wrap a callable with run tracking. */
    public static function track(string $name, callable $fn)
    {
        $run = static::start($name);
        try {
            $result = $fn();
            $run->finish('success', is_string($result) ? $result : null);
            return $result;
        } catch (\Throwable $e) {
            $run->finish('failed', $e->getMessage());
            SystemLog::record('job', "Job {$name} failed: ".$e->getMessage(), ['exception' => get_class($e)], 'error');
            throw $e;
        }
    }
}
