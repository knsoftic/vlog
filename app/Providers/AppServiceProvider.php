<?php

namespace App\Providers;

use App\Models\AdSlot;
use App\Models\Category;
use App\Models\MenuItem;
use App\Models\Page;
use App\Services\AdSenseService;
use App\Services\AnalyticsService;
use App\Services\AuditLogger;
use App\Services\BotDetector;
use App\Services\GeoService;
use App\Services\HtmlSanitizer;
use App\Services\SeoService;
use App\Services\SettingsService;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Helpers are also registered in composer.json "files"; this guarantees availability even if the
        // autoloader was generated without them (e.g. a partial deploy).
        require_once app_path('Support/helpers.php');

        $this->app->singleton(SettingsService::class);
        $this->app->singleton(AuditLogger::class);
        $this->app->singleton(BotDetector::class);
        $this->app->singleton(GeoService::class);
        $this->app->singleton(HtmlSanitizer::class);
        $this->app->singleton(SeoService::class);
        $this->app->singleton(AnalyticsService::class);
    }

    public function boot(): void
    {
        if ($this->app->environment('production') || str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        // Shared frontend data (cached; invalidated by admin actions)
        View::composer(['layouts.app', 'partials.*', 'frontend.*'], function ($view) {
            $view->with('siteNav', $this->siteNav());
        });

        // Global data for the admin layout
        View::composer('layouts.admin', function ($view) {
            try {
                $view->with('unreadNotifications', \App\Models\AdminNotification::where('is_read', false)->count());
            } catch (\Throwable) {
                $view->with('unreadNotifications', 0);
            }
        });

        Blade::directive('money', function ($expr) {
            return "<?php echo number_format((float) ($expr), 2); ?>";
        });
        Blade::directive('compact', function ($expr) {
            return "<?php echo compact_number($expr); ?>";
        });
        Blade::if('permission', function (string $slug) {
            return auth()->check() && auth()->user()->hasPermission($slug);
        });
        Blade::if('setting', function (string $key) {
            return setting_bool($key);
        });
    }

    protected function siteNav(): array
    {
        try {
            if (! Schema::hasTable('categories')) {
                return ['categories' => collect(), 'headerMenu' => collect(), 'footerMenu' => collect(), 'footerPages' => collect(), 'adSlots' => collect()];
            }
            return Cache::remember('site.nav', 600, function () {
                return [
                    'categories' => Category::active()->topLevel()->orderBy('sort_order')->orderBy('name')->with('children')->get(),
                    'headerMenu' => MenuItem::where('location', 'header')->where('is_active', true)->orderBy('sort_order')->get(),
                    'footerMenu' => MenuItem::where('location', 'footer')->where('is_active', true)->orderBy('sort_order')->get(),
                    'footerPages' => Page::published()->where('show_in_footer', true)->orderBy('sort_order')->get(['title', 'slug']),
                    'adSlots' => AdSlot::where('enabled', true)->get()->keyBy('key'),
                ];
            });
        } catch (\Throwable) {
            return ['categories' => collect(), 'headerMenu' => collect(), 'footerMenu' => collect(), 'footerPages' => collect(), 'adSlots' => collect()];
        }
    }
}
