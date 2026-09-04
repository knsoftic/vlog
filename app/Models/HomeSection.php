<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HomeSection extends Model
{
    protected $fillable = ['key', 'title', 'subtitle', 'enabled', 'sort_order', 'settings'];

    protected $casts = ['enabled' => 'boolean', 'settings' => 'array'];

    public static function defaults(): array
    {
        return [
            ['key' => 'hero', 'title' => 'Featured', 'subtitle' => 'Hand-picked stories worth your time', 'settings' => ['limit' => 5]],
            ['key' => 'latest', 'title' => 'Latest Vlogs', 'subtitle' => 'Fresh from the camera', 'settings' => ['limit' => 8]],
            ['key' => 'trending', 'title' => 'Trending Now', 'subtitle' => 'What everyone is watching', 'settings' => ['limit' => 6]],
            ['key' => 'categories', 'title' => 'Explore Categories', 'subtitle' => 'Browse by topic', 'settings' => ['limit' => 8]],
            ['key' => 'popular', 'title' => 'Most Popular', 'subtitle' => 'All-time favourites', 'settings' => ['limit' => 6]],
            ['key' => 'articles', 'title' => 'Latest Articles', 'subtitle' => 'Long-form reads', 'settings' => ['limit' => 6]],
            ['key' => 'recommended', 'title' => 'Recommended For You', 'subtitle' => 'Editor picks', 'settings' => ['limit' => 6]],
            ['key' => 'newsletter', 'title' => 'Stay in the loop', 'subtitle' => 'Get new vlogs in your inbox', 'settings' => []],
        ];
    }

    public function setting(string $key, $default = null)
    {
        return $this->settings[$key] ?? $default;
    }
}
