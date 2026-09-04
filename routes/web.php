<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Frontend;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public site
|--------------------------------------------------------------------------
*/
Route::middleware(['traffic', 'track'])->group(function () {
    Route::get('/', [Frontend\HomeController::class, 'index'])->name('home');
    Route::get('/vlogs', [Frontend\PostController::class, 'vlogs'])->name('vlogs');
    Route::get('/articles', [Frontend\PostController::class, 'articles'])->name('articles');
    Route::get('/trending', [Frontend\PostController::class, 'trending'])->name('trending');
    Route::get('/popular', [Frontend\PostController::class, 'popular'])->name('popular');
    Route::get('/vlog/{slug}', [Frontend\PostController::class, 'showVlog'])->name('vlog.show');
    Route::get('/article/{slug}', [Frontend\PostController::class, 'showArticle'])->name('article.show');
    Route::get('/categories', [Frontend\CategoryController::class, 'index'])->name('categories');
    Route::get('/category/{slug}', [Frontend\CategoryController::class, 'show'])->name('category.show');
    Route::get('/tag/{slug}', [Frontend\TagController::class, 'show'])->name('tag.show');
    Route::get('/author/{slug}', [Frontend\AuthorController::class, 'show'])->name('author.show');
    Route::get('/search', [Frontend\SearchController::class, 'index'])->name('search');
    Route::get('/page/{slug}', [Frontend\PageController::class, 'show'])->name('page.show');
    Route::post('/page/contact', [Frontend\PageController::class, 'contactSubmit'])->name('contact.submit');
    Route::post('/vlog/{post}/comments', [Frontend\CommentController::class, 'store'])->name('comments.store');
});

Route::middleware('traffic')->group(function () {
    Route::get('/sitemap.xml', [Frontend\SeoController::class, 'sitemap'])->name('sitemap');
    Route::get('/robots.txt', [Frontend\SeoController::class, 'robots'])->name('robots');
    Route::get('/ads.txt', [Frontend\SeoController::class, 'adsTxt'])->name('ads-txt');
    Route::get('/api/search/suggest', [Frontend\SearchController::class, 'suggest'])->name('search.suggest');
    Route::post('/api/consent', [Frontend\ConsentController::class, 'store'])->name('consent.store');
    // First-party tracking beacons (CSRF exempt, rate limited)
    Route::post('/api/track/heartbeat', [Frontend\TrackingController::class, 'heartbeat'])->name('track.heartbeat');
    Route::post('/api/track/video', [Frontend\TrackingController::class, 'video'])->name('track.video');
    Route::post('/api/track/event', [Frontend\TrackingController::class, 'event'])->name('track.event');
});

/*
|--------------------------------------------------------------------------
| Admin
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('login', [Admin\AuthController::class, 'showLogin'])->name('login')->middleware('guest');
    Route::post('login', [Admin\AuthController::class, 'login'])->middleware(['guest', 'throttle:10,1']);
    Route::post('logout', [Admin\AuthController::class, 'logout'])->name('logout')->middleware('auth');

    Route::middleware(['auth', 'admin'])->group(function () {
        Route::get('/', [Admin\DashboardController::class, 'index'])->name('dashboard');
        Route::get('realtime.json', [Admin\DashboardController::class, 'realtime'])->name('realtime.json');
        Route::get('profile', [Admin\ProfileController::class, 'edit'])->name('profile');
        Route::put('profile', [Admin\ProfileController::class, 'update']);
        Route::get('notifications', [Admin\NotificationController::class, 'index'])->name('notifications');
        Route::get('notifications/latest', [Admin\NotificationController::class, 'latest'])->name('notifications.latest');
        Route::post('notifications/{notification}/read', [Admin\NotificationController::class, 'read'])->name('notifications.read');
        Route::post('notifications/read-all', [Admin\NotificationController::class, 'readAll'])->name('notifications.read-all');

        // ---- Content ----
        foreach (['vlogs', 'articles'] as $kind) {
            Route::middleware('permission:posts.view')->group(function () use ($kind) {
                Route::get($kind, [Admin\PostController::class, 'index'])->name("$kind.index");
                Route::get("$kind/trash", [Admin\PostController::class, 'trash'])->name("$kind.trash");
                Route::get("$kind/create", [Admin\PostController::class, 'create'])->name("$kind.create")->middleware('permission:posts.create');
                Route::post($kind, [Admin\PostController::class, 'store'])->name("$kind.store")->middleware('permission:posts.create');
                Route::get("$kind/{post}/edit", [Admin\PostController::class, 'edit'])->name("$kind.edit")->middleware('permission:posts.edit');
                Route::put("$kind/{post}", [Admin\PostController::class, 'update'])->name("$kind.update")->middleware('permission:posts.edit');
                Route::delete("$kind/{post}", [Admin\PostController::class, 'destroy'])->name("$kind.destroy")->middleware('permission:posts.delete');
                Route::post("$kind/{post}/duplicate", [Admin\PostController::class, 'duplicate'])->name("$kind.duplicate")->middleware('permission:posts.create');
                Route::post("$kind/{post}/status", [Admin\PostController::class, 'status'])->name("$kind.status")->middleware('permission:posts.edit');
                Route::post("$kind/{id}/restore", [Admin\PostController::class, 'restore'])->name("$kind.restore")->middleware('permission:posts.delete');
                Route::delete("$kind/{id}/force", [Admin\PostController::class, 'forceDelete'])->name("$kind.force")->middleware('permission:posts.delete');
            });
        }
        Route::get('posts/{post}/preview', [Admin\PostController::class, 'preview'])->name('posts.preview')->middleware('permission:posts.view');
        Route::get('posts/{post}/analytics', [Admin\PostController::class, 'analytics'])->name('posts.analytics')->middleware('permission:analytics.view|posts.view');

        Route::middleware('permission:categories.manage')->group(function () {
            Route::resource('categories', Admin\CategoryController::class)->except(['show']);
            Route::post('categories/reorder', [Admin\CategoryController::class, 'reorder'])->name('categories.reorder');
            Route::get('tags', [Admin\TagController::class, 'index'])->name('tags.index');
            Route::post('tags', [Admin\TagController::class, 'store'])->name('tags.store');
            Route::put('tags/{tag}', [Admin\TagController::class, 'update'])->name('tags.update');
            Route::delete('tags/{tag}', [Admin\TagController::class, 'destroy'])->name('tags.destroy');
        });
        Route::middleware('permission:media.manage')->group(function () {
            Route::get('media', [Admin\MediaController::class, 'index'])->name('media.index');
            Route::post('media', [Admin\MediaController::class, 'store'])->name('media.store');
            Route::put('media/{media}', [Admin\MediaController::class, 'update'])->name('media.update');
            Route::delete('media/{media}', [Admin\MediaController::class, 'destroy'])->name('media.destroy');
        });
        Route::middleware('permission:comments.moderate')->group(function () {
            Route::get('comments', [Admin\CommentController::class, 'index'])->name('comments.index');
            Route::put('comments/{comment}', [Admin\CommentController::class, 'update'])->name('comments.update');
            Route::delete('comments/{comment}', [Admin\CommentController::class, 'destroy'])->name('comments.destroy');
        });

        // ---- Analytics ----
        Route::prefix('analytics')->name('analytics.')->middleware('permission:analytics.view')->group(function () {
            Route::get('/', [Admin\AnalyticsController::class, 'overview'])->name('overview');
            Route::get('realtime', [Admin\AnalyticsController::class, 'realtime'])->name('realtime');
            Route::get('traffic', [Admin\AnalyticsController::class, 'traffic'])->name('traffic');
            Route::get('content', [Admin\AnalyticsController::class, 'content'])->name('content');
            Route::get('video', [Admin\AnalyticsController::class, 'video'])->name('video');
            Route::get('audience', [Admin\AnalyticsController::class, 'audience'])->name('audience');
            Route::get('sources', [Admin\AnalyticsController::class, 'sources'])->name('sources');
            Route::get('search', [Admin\AnalyticsController::class, 'search'])->name('search');
        });
        Route::middleware('permission:analytics.view')->group(function () {
            Route::get('reports', [Admin\ReportController::class, 'index'])->name('reports.index');
            Route::get('reports/export', [Admin\ReportController::class, 'export'])->name('reports.export');
        });

        // ---- SEO ----
        Route::prefix('seo')->name('seo.')->middleware('permission:seo.manage')->group(function () {
            Route::get('/', [Admin\SeoController::class, 'overview'])->name('overview');
            Route::get('search-console', [Admin\SeoController::class, 'searchConsole'])->name('search-console');
            Route::post('search-console/site', [Admin\SeoController::class, 'selectSite'])->name('search-console.site');
            Route::get('sitemap', [Admin\SeoController::class, 'sitemap'])->name('sitemap');
            Route::post('sitemap/regenerate', [Admin\SeoController::class, 'regenerateSitemap'])->name('sitemap.regenerate');
            Route::get('redirects', [Admin\SeoController::class, 'redirects'])->name('redirects');
            Route::post('redirects', [Admin\SeoController::class, 'storeRedirect'])->name('redirects.store');
            Route::put('redirects/{redirect}', [Admin\SeoController::class, 'updateRedirect'])->name('redirects.update');
            Route::delete('redirects/{redirect}', [Admin\SeoController::class, 'destroyRedirect'])->name('redirects.destroy');
            Route::get('broken-links', [Admin\SeoController::class, 'brokenLinks'])->name('broken-links');
            Route::post('broken-links/run', [Admin\SeoController::class, 'runLinkCheck'])->name('broken-links.run');
            Route::post('broken-links/{link}/resolve', [Admin\SeoController::class, 'resolveLink'])->name('broken-links.resolve');
        });

        // ---- Monetization ----
        Route::prefix('monetization')->name('monetization.')->middleware('permission:monetization.manage')->group(function () {
            Route::get('/', [Admin\MonetizationController::class, 'dashboard'])->name('dashboard');
            Route::post('sync', [Admin\MonetizationController::class, 'sync'])->name('sync');
            Route::get('ad-units', [Admin\MonetizationController::class, 'adUnits'])->name('ad-units');
            Route::put('ad-units/{slot}', [Admin\MonetizationController::class, 'updateAdUnit'])->name('ad-units.update');
            Route::get('placement', [Admin\MonetizationController::class, 'placement'])->name('placement');
            Route::post('placement', [Admin\MonetizationController::class, 'updatePlacement'])->name('placement.update');
            Route::get('ads-txt', [Admin\MonetizationController::class, 'adsTxt'])->name('ads-txt');
            Route::put('ads-txt', [Admin\MonetizationController::class, 'updateAdsTxt'])->name('ads-txt.update');
            Route::get('settings', [Admin\MonetizationController::class, 'settings'])->name('settings');
            Route::put('settings', [Admin\MonetizationController::class, 'updateSettings'])->name('settings.update');
            Route::get('checklist', [Admin\MonetizationController::class, 'checklist'])->name('checklist');
        });

        // ---- Pages & appearance ----
        Route::middleware('permission:pages.manage')->group(function () {
            Route::resource('pages', Admin\PageController::class)->except(['show']);
            Route::get('appearance', [Admin\HomeSectionController::class, 'index'])->name('appearance');
            Route::put('appearance/sections', [Admin\HomeSectionController::class, 'update'])->name('appearance.sections');
            Route::post('appearance/menu', [Admin\HomeSectionController::class, 'storeMenu'])->name('appearance.menu.store');
            Route::put('appearance/menu', [Admin\HomeSectionController::class, 'updateMenu'])->name('appearance.menu.update');
            Route::delete('appearance/menu/{item}', [Admin\HomeSectionController::class, 'destroyMenu'])->name('appearance.menu.destroy');
        });

        // ---- Users ----
        Route::middleware('permission:users.manage')->group(function () {
            Route::get('users', [Admin\UserController::class, 'index'])->name('users.index');
            Route::get('authors', [Admin\UserController::class, 'index'])->name('authors.index');
            Route::get('users/create', [Admin\UserController::class, 'create'])->name('users.create');
            Route::post('users', [Admin\UserController::class, 'store'])->name('users.store');
            Route::get('users/{user}/edit', [Admin\UserController::class, 'edit'])->name('users.edit');
            Route::put('users/{user}', [Admin\UserController::class, 'update'])->name('users.update');
            Route::delete('users/{user}', [Admin\UserController::class, 'destroy'])->name('users.destroy');
        });
        Route::middleware('permission:roles.manage')->group(function () {
            Route::get('roles', [Admin\RoleController::class, 'index'])->name('roles.index');
            Route::post('roles', [Admin\RoleController::class, 'store'])->name('roles.store');
            Route::put('roles/{role}', [Admin\RoleController::class, 'update'])->name('roles.update');
            Route::delete('roles/{role}', [Admin\RoleController::class, 'destroy'])->name('roles.destroy');
            Route::get('permissions', [Admin\RoleController::class, 'permissions'])->name('permissions');
            Route::put('permissions', [Admin\RoleController::class, 'syncPermissions'])->name('permissions.update');
        });

        // ---- Logs ----
        Route::prefix('logs')->name('logs.')->middleware('permission:logs.view')->group(function () {
            Route::get('admin', [Admin\LogController::class, 'admin'])->name('admin');
            Route::get('security', [Admin\LogController::class, 'security'])->name('security');
            Route::get('system', [Admin\LogController::class, 'system'])->name('system');
            Route::post('system/clear', [Admin\LogController::class, 'clearSystem'])->name('system.clear');
        });

        // ---- Settings ----
        Route::middleware('permission:settings.manage')->group(function () {
            Route::get('settings/{tab?}', [Admin\SettingsController::class, 'edit'])->name('settings.edit');
            Route::put('settings/{tab}', [Admin\SettingsController::class, 'update'])->name('settings.update');
            Route::post('settings-cache-clear', [Admin\SettingsController::class, 'clearCache'])->name('settings.cache-clear');
            Route::get('google/connect/{service}', [Admin\GoogleController::class, 'connect'])->name('google.connect');
            Route::get('google/callback', [Admin\GoogleController::class, 'callback'])->name('google.callback');
            Route::post('google/disconnect/{service}', [Admin\GoogleController::class, 'disconnect'])->name('google.disconnect');
            Route::post('google/sync/{service}', [Admin\GoogleController::class, 'sync'])->name('google.sync');
        });
        Route::middleware('permission:backups.manage')->group(function () {
            Route::get('backups', [Admin\BackupController::class, 'index'])->name('backups.index');
            Route::post('backups', [Admin\BackupController::class, 'create'])->name('backups.create');
            Route::get('backups/{backup}/download', [Admin\BackupController::class, 'download'])->name('backups.download');
            Route::post('backups/{backup}/restore', [Admin\BackupController::class, 'restore'])->name('backups.restore');
            Route::delete('backups/{backup}', [Admin\BackupController::class, 'destroy'])->name('backups.destroy');
        });
    });
});

