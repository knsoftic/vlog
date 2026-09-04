<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'role_id', 'name', 'slug', 'email', 'password', 'avatar', 'bio', 'social_links', 'is_active',
        'last_login_at', 'last_login_ip', 'failed_login_attempts', 'locked_until',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'locked_until' => 'datetime',
            'password' => 'hashed',
            'social_links' => 'array',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (User $user) {
            if (empty($user->slug)) {
                $base = Str::slug($user->name) ?: 'author';
                $slug = $base;
                $i = 1;
                while (static::where('slug', $slug)->where('id', '!=', $user->id ?? 0)->exists()) {
                    $slug = $base.'-'.(++$i);
                }
                $user->slug = $slug;
            }
        });
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class, 'author_id');
    }

    public function publishedPosts(): HasMany
    {
        return $this->posts()->published();
    }

    public function media(): HasMany
    {
        return $this->hasMany(Media::class);
    }

    public function hasRole(string|array $slugs): bool
    {
        $slugs = (array) $slugs;
        return $this->role && in_array($this->role->slug, $slugs, true);
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole('super_admin');
    }

    public function can($abilities, $arguments = []): bool
    {
        if (is_string($abilities) && $this->role) {
            if ($this->isSuperAdmin()) {
                return true;
            }
            if ($this->role->hasPermission($abilities)) {
                return true;
            }
        }
        return parent::can($abilities, $arguments);
    }

    public function hasPermission(string $slug): bool
    {
        if (! $this->role) {
            return false;
        }
        return $this->isSuperAdmin() || $this->role->hasPermission($slug);
    }

    public function isLocked(): bool
    {
        return $this->locked_until && $this->locked_until->isFuture();
    }

    public function getAvatarUrlAttribute(): string
    {
        if ($this->avatar) {
            return Str::startsWith($this->avatar, ['http://', 'https://']) ? $this->avatar : asset('storage/'.$this->avatar);
        }
        return 'https://ui-avatars.com/api/?background=0f172a&color=fff&name='.urlencode($this->name);
    }

    public function getUrlAttribute(): string
    {
        return route('author.show', $this->slug);
    }
}
