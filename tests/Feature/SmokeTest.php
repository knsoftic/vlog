<?php

namespace Tests\Feature;

use App\Models\AdSlot;
use App\Models\Post;
use App\Models\Role;
use App\Models\User;
use App\Services\AnalyticsService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * End-to-end smoke tests: public pages, SEO endpoints, tracking beacons, consent, and every admin screen.
 */
class SmokeTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->admin = User::where('email', 'admin@example.com')->firstOrFail();
    }

    protected function browser(): static
    {
        return $this->withHeaders(['User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/128.0 Safari/537.36']);
    }

    public function test_public_pages_render(): void
    {
        $post = Post::published()->vlogs()->first();
        $article = Post::published()->articles()->first();
        foreach (['/', '/vlogs', '/articles', '/trending', '/popular', '/categories', '/category/travel', '/search?q=istanbul', '/page/privacy-policy', '/page/contact-us', '/author/'.$this->admin->slug, $post->url, $article->url] as $url) {
            $this->browser()->get($url)->assertOk();
        }
        $this->get('/tag/'.$post->tags->first()->slug)->assertOk();
        $this->get('/does-not-exist')->assertNotFound();
    }

    public function test_seo_endpoints(): void
    {
        $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8')->assertSee('<urlset', false)->assertSee('video:video', false);
        $this->get('/robots.txt')->assertOk()->assertSee('Sitemap:');
        $this->get('/ads.txt')->assertOk();
        $post = Post::published()->vlogs()->first();
        $this->browser()->get($post->url)->assertSee('application/ld+json', false)->assertSee('VideoObject')->assertSee('BreadcrumbList')->assertSee('<link rel="canonical"', false);
    }

    public function test_wrong_type_url_redirects_to_canonical(): void
    {
        $post = Post::published()->vlogs()->first();
        $this->get('/article/'.$post->slug)->assertRedirect($post->url);
    }

    public function test_page_view_is_tracked_and_bots_are_separated(): void
    {
        $post = Post::published()->vlogs()->first();
        $this->browser()->get($post->url)->assertOk()->assertSee('name="vh-pv"', false);
        $this->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)'])->get($post->url)->assertOk();
        $this->assertDatabaseHas('page_views', ['post_id' => $post->id, 'is_bot' => 0]);
        $this->assertDatabaseHas('page_views', ['post_id' => $post->id, 'is_bot' => 1]);
        $this->assertSame(1, Post::find($post->id)->views_count, 'bot views must not increase the human counter');
    }

    public function test_tracking_beacons_and_aggregation(): void
    {
        $post = Post::published()->vlogs()->first();
        $html = $this->browser()->get($post->url)->getContent();
        preg_match('/name="vh-pv" content="(\d+)"/', $html, $pv);
        preg_match('/name="vh-sk" content="([^"]+)"/', $html, $sk);
        $this->browser()->postJson('/api/track/heartbeat', ['pv' => (int) $pv[1], 'engaged' => 20, 'scroll' => 75, 'sk' => $sk[1]])->assertNoContent();
        $this->browser()->postJson('/api/track/video', ['post_id' => $post->id, 'event' => 'start', 'provider' => 'youtube', 'duration' => 300, 'play_id' => 'play1', 'sk' => $sk[1]])->assertNoContent();
        $this->browser()->postJson('/api/track/video', ['post_id' => $post->id, 'event' => 'start', 'provider' => 'youtube', 'play_id' => 'play1', 'sk' => $sk[1]])->assertNoContent(); // duplicate milestone ignored
        $this->browser()->postJson('/api/track/video', ['post_id' => $post->id, 'event' => 'heartbeat', 'watch_seconds' => 30, 'play_id' => 'play1', 'sk' => $sk[1]])->assertNoContent();
        $this->browser()->postJson('/api/track/video', ['post_id' => $post->id, 'event' => 'complete', 'play_id' => 'play1', 'sk' => $sk[1]])->assertNoContent();
        $this->browser()->postJson('/api/track/event', ['type' => 'share', 'value' => 'twitter', 'post_id' => $post->id, 'sk' => $sk[1]])->assertNoContent();
        $this->browser()->get('/search?q=zzzznothing')->assertOk();

        $this->assertDatabaseHas('page_views', ['id' => (int) $pv[1], 'engagement_time' => 20, 'scroll_depth' => 75]);
        $this->assertSame(1, \App\Models\VideoEvent::where('event', 'start')->count());
        $this->assertSame(1, Post::find($post->id)->video_plays_count);
        $this->assertSame(1, Post::find($post->id)->shares_count);
        $this->assertDatabaseHas('search_logs', ['query' => 'zzzznothing', 'results_count' => 0]);

        app(AnalyticsService::class)->aggregateDay(now());
        $daily = \App\Models\AnalyticsDaily::first();
        $this->assertSame(2, (int) $daily->page_views);
        $this->assertSame(1, (int) $daily->video_plays);
        $this->assertSame(1, (int) $daily->video_completes);
        $this->assertSame(30, (int) $daily->watch_time);
        $this->assertSame(1, (int) $daily->shares);
        $this->assertSame(1, (int) $daily->zero_result_searches);
        $this->assertDatabaseHas('content_daily', ['post_id' => $post->id, 'views' => 1, 'video_starts' => 1, 'completes' => 1]);
    }

    public function test_consent_endpoint_sets_cookie(): void
    {
        $r = $this->browser()->postJson('/api/consent', ['analytics' => 1, 'advertising' => 0])->assertOk()->assertJson(['consent' => 'v1.100']);
        $r->assertCookie('vh_consent', 'v1.100', false); // plain-text cookie so the browser script can read it
        $this->assertDatabaseHas('consents', ['analytics' => 1, 'advertising' => 0]);
    }

    public function test_bot_beacons_are_ignored(): void
    {
        $post = Post::published()->vlogs()->first();
        $this->withHeaders(['User-Agent' => 'python-requests/2.31'])->postJson('/api/track/video', ['post_id' => $post->id, 'event' => 'start', 'play_id' => 'bot'])->assertNoContent();
        $this->assertDatabaseCount('video_events', 0);
    }

    public function test_admin_requires_login_and_roles(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
        $author = User::create(['name' => 'Writer', 'email' => 'writer@example.com', 'password' => 'Password12345', 'role_id' => Role::where('slug', 'author')->value('id')])->fresh();
        $this->actingAs($author)->get('/admin')->assertOk();
        $this->actingAs($author)->get('/admin/settings')->assertForbidden();
        $this->actingAs($author)->get('/admin/monetization')->assertForbidden();
        $this->actingAs($author)->get('/admin/vlogs')->assertOk();
    }

    public function test_failed_logins_are_logged_and_locked(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/admin/login', ['email' => 'admin@example.com', 'password' => 'wrong-password']);
        }
        $this->assertDatabaseHas('security_logs', ['type' => 'failed_login', 'email' => 'admin@example.com']);
        $this->assertNotNull(User::find($this->admin->id)->locked_until);
    }

    public function test_all_admin_screens_render_for_super_admin(): void
    {
        $post = Post::first();
        $screens = [
            '/admin', '/admin/vlogs', '/admin/articles', '/admin/vlogs/create', '/admin/articles/create', "/admin/vlogs/{$post->id}/edit", "/admin/posts/{$post->id}/analytics", "/admin/posts/{$post->id}/preview", '/admin/vlogs/trash',
            '/admin/categories', '/admin/categories/create', '/admin/categories/1/edit', '/admin/tags', '/admin/authors', '/admin/media', '/admin/comments',
            '/admin/analytics', '/admin/analytics/realtime', '/admin/analytics/traffic', '/admin/analytics/content', '/admin/analytics/video', '/admin/analytics/audience', '/admin/analytics/sources', '/admin/analytics/search', '/admin/reports',
            '/admin/seo', '/admin/seo/search-console', '/admin/seo/sitemap', '/admin/seo/redirects', '/admin/seo/broken-links',
            '/admin/monetization', '/admin/monetization/ad-units', '/admin/monetization/placement', '/admin/monetization/ads-txt', '/admin/monetization/settings', '/admin/monetization/checklist',
            '/admin/pages', '/admin/pages/create', '/admin/pages/1/edit', '/admin/appearance',
            '/admin/users', '/admin/users/create', "/admin/users/{$this->admin->id}/edit", '/admin/roles', '/admin/permissions',
            '/admin/logs/admin', '/admin/logs/security', '/admin/logs/system',
            '/admin/settings', '/admin/settings/branding', '/admin/settings/analytics', '/admin/settings/google', '/admin/settings/email', '/admin/settings/consent', '/admin/settings/seo', '/admin/settings/backup', '/admin/settings/performance', '/admin/settings/security',
            '/admin/backups', '/admin/notifications', '/admin/profile', '/admin/realtime.json', '/admin/notifications/latest',
        ];
        foreach ($screens as $url) {
            $this->actingAs($this->admin)->get($url)->assertOk();
        }
        foreach (['traffic', 'content', 'seo', 'adsense', 'video'] as $report) {
            $this->actingAs($this->admin)->get("/admin/reports/export?report={$report}&format=csv&range=7d")->assertOk();
        }
    }

    public function test_post_crud_and_audit_log(): void
    {
        $this->actingAs($this->admin)->post('/admin/vlogs', [
            'title' => 'My Test Vlog', 'excerpt' => 'A short description that is long enough for the checklist.', 'content' => '<p>Hello</p><script>alert(1)</script><p>World</p>',
            'video_type' => 'youtube', 'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'status' => 'published', 'category_id' => 1, 'tags' => 'test, demo',
        ])->assertRedirect();
        $post = Post::where('title', 'My Test Vlog')->firstOrFail();
        $this->assertSame('my-test-vlog', $post->slug);
        $this->assertStringNotContainsString('<script', $post->content, 'content must be sanitised');
        $this->assertSame(2, $post->tags()->count());
        $this->assertDatabaseHas('admin_logs', ['action' => 'created', 'model_id' => $post->id]);
        // duplicate slug protection
        $this->actingAs($this->admin)->post('/admin/vlogs', ['title' => 'My Test Vlog', 'video_type' => 'none', 'status' => 'draft']);
        $this->assertSame('my-test-vlog-2', Post::where('title', 'My Test Vlog')->latest('id')->first()->slug);
        // schedule
        $this->actingAs($this->admin)->post("/admin/vlogs/{$post->id}/status", ['status' => 'scheduled', 'scheduled_at' => now()->subMinute()->toDateTimeString()])->assertRedirect();
        $this->artisan('posts:publish-scheduled')->assertSuccessful();
        $this->assertSame('published', Post::find($post->id)->status);
        // delete + restore
        $this->actingAs($this->admin)->delete("/admin/vlogs/{$post->id}")->assertRedirect();
        $this->assertSoftDeleted('posts', ['id' => $post->id]);
        $this->actingAs($this->admin)->post("/admin/vlogs/{$post->id}/restore")->assertRedirect();
        $this->assertDatabaseHas('posts', ['id' => $post->id, 'deleted_at' => null]);
    }

    public function test_adsense_settings_validation_and_ads_txt(): void
    {
        $this->actingAs($this->admin)->put('/admin/monetization/settings', ['adsense_enabled' => 1, 'adsense_publisher_id' => 'pub-1234567890123456', 'adsense_label' => 'Click here to support us'])
            ->assertSessionHasErrors(['adsense_label']);
        $this->actingAs($this->admin)->put('/admin/monetization/settings', ['adsense_enabled' => 1, 'adsense_publisher_id' => 'pub-1234567890123456', 'adsense_label' => 'Advertisement', 'adsense_min_words_for_ads' => 150])->assertSessionHasNoErrors();
        $this->get('/ads.txt')->assertOk()->assertSee('google.com, pub-1234567890123456, DIRECT, f08c47fec0942fa0');
        $this->actingAs($this->admin)->put('/admin/monetization/ads-txt', ['ads_txt' => 'google.com, pub-1234567890123456, WRONG'])->assertSessionHasErrors(['ads_txt']);
        $slot = AdSlot::where('key', 'header')->first();
        $this->actingAs($this->admin)->put("/admin/monetization/ad-units/{$slot->id}", ['name' => 'Header', 'ad_format' => 'auto', 'code' => '<script>setInterval(()=>document.querySelector(".adsbygoogle").click(),1000)</script>', 'enabled' => 1])
            ->assertSessionHasErrors(['code']);
        $this->assertDatabaseHas('admin_logs', ['action' => 'adsense_changed']);
    }

    public function test_thin_pages_get_noindex_and_no_ads(): void
    {
        $thin = Post::create(['type' => 'article', 'title' => 'Thin', 'content' => '<p>Too short.</p>', 'status' => 'published', 'author_id' => $this->admin->id, 'video_type' => 'none']);
        $this->browser()->get($thin->url)->assertOk()->assertSee('noindex, follow');
    }

    public function test_redirects_and_404_logging(): void
    {
        $this->actingAs($this->admin)->post('/admin/seo/redirects', ['from_path' => '/old-page', 'to_path' => '/vlogs', 'status_code' => 301]);
        $this->get('/old-page')->assertRedirect('/vlogs');
        $this->get('/missing-page')->assertNotFound();
        $this->assertDatabaseHas('system_logs', ['type' => '404']);
    }

    public function test_upload_rejects_dangerous_files(): void
    {
        $file = \Illuminate\Http\UploadedFile::fake()->create('evil.php', 10, 'text/plain');
        $this->actingAs($this->admin)->postJson('/admin/media', ['file' => $file])->assertStatus(422);
        $this->assertDatabaseHas('security_logs', ['type' => 'upload_rejected']);
    }

    public function test_realtime_widget_with_active_visitors(): void
    {
        $post = Post::published()->vlogs()->first();
        $this->browser()->get($post->url)->assertOk();
        $this->assertDatabaseHas('realtime_visitors', ['post_id' => $post->id]);
        $this->actingAs($this->admin)->get('/admin/realtime.json')->assertOk()->assertJsonPath('online', 1)->assertJsonPath('vlogs.0.post.id', $post->id);
        $this->actingAs($this->admin)->get('/admin')->assertOk()->assertSee($post->title);
        $this->actingAs($this->admin)->get('/admin/analytics/realtime')->assertOk();
    }

    public function test_scheduled_commands_run(): void
    {
        $this->artisan('analytics:aggregate')->assertSuccessful();
        $this->artisan('analytics:retention')->assertSuccessful();
        $this->artisan('backup:run database')->assertSuccessful();
        $this->assertDatabaseHas('backups', ['type' => 'database', 'status' => 'completed']);
        $this->assertDatabaseHas('job_runs', ['name' => 'analytics:aggregate', 'status' => 'success']);
    }
}
