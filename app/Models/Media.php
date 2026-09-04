<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Media extends Model
{
    protected $table = 'media';

    protected $fillable = [
        'user_id', 'disk', 'path', 'filename', 'original_name', 'mime', 'extension', 'type', 'size', 'width', 'height',
        'duration', 'alt', 'title', 'variants',
    ];

    protected $casts = ['variants' => 'array'];

    protected $appends = ['url', 'thumb_url'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getUrlAttribute(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }

    public function getThumbUrlAttribute(): string
    {
        $v = $this->variants ?? [];
        if (! empty($v['thumb'])) {
            return Storage::disk($this->disk)->url($v['thumb']);
        }
        return $this->type === 'image' ? $this->url : '';
    }

    public function variantPath(string $name): ?string
    {
        return $this->variants[$name] ?? null;
    }

    public function variantUrl(string $name): ?string
    {
        $p = $this->variantPath($name);
        return $p ? Storage::disk($this->disk)->url($p) : null;
    }

    public function humanSize(): string
    {
        $bytes = $this->size;
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < 3) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 1).' '.$units[$i];
    }

    public function deleteFiles(): void
    {
        $disk = Storage::disk($this->disk);
        $disk->delete($this->path);
        foreach (($this->variants ?? []) as $v) {
            if ($v) {
                $disk->delete($v);
            }
        }
    }
}
