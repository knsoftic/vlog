<?php

namespace Tests\Feature;

use App\Models\AdSlot;
use App\Models\Post;
use App\Models\User;
use App\Services\SettingsService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Adsterra Banner / Native slots: code validation, admin screens and per-request serving rules.
 */
class AdsterraTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    public const BANNER_728 = <<<'HTML'
<script type="text/javascript">
	atOptions = {
		'key' : '0123456789abcdef0123456789abcdef',
		'format' : 'iframe',
		'height' : 90,
		'width' : 728,
		'params' : {}
	};
</script>
<script type="text/javascript" src="//www.highperformanceformat.com/0123456789abcdef0123456789abcdef/invoke.js"></script>
HTML;

    public const BANNER_320 = <<<'HTML'
<script type="text/javascript">
	atOptions = { 'key' : 'fedcba9876543210fedcba9876543210', 'format' : 'iframe', 'height' : 50, 'width' : 320, 'params' : {} };
</script>
<script type="text/javascript" src="//www.highperformanceformat.com/fedcba9876543210fedcba9876543210/invoke.js"></script>
HTML;

    public const NATIVE = <<<'HTML'
<script async="async" data-cfasync="false" src="//pl12345678.profitableratecpm.com/0123456789abcdef0123456789abcdef/invoke.js"></script>
<div id="container-0123456789abcdef0123456789abcdef"></div>
HTML;

    public const POPUNDER = "<script type='text/javascript' src='//pl12345678.profitableratecpm.com/01/23/45/0123456789abcdef.js'></script>";

    public const SMARTLINK = '<a href="https://www.profitableratecpm.com/abc123?key=0123456789abcdef">Click here</a>';

    protected const DESKTOP_UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/128.0 Safari/537.36';
    protected const MOBILE_UA = 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148 Safari/604.1';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->admin = User::where('email', 'admin@example.com')->firstOrFail();
    }

    protected function settings(array $pairs): void
    {
        app(SettingsService::class)->setMany($pairs);
        cache()->flush();
    }

    protected function enableHeaderBanner(?string $mobile = null): AdSlot
    {
        $slot = AdSlot::where('key', 'header')->firstOrFail();
        $slot->update(['enabled' => false, 'adsterra_enabled' => true, 'adsterra_code' => self::BANNER_728, 'adsterra_code_mobile' => $mobile, 'desktop' => true, 'tablet' => true, 'mobile' => true]);
        $this->settings(['adsterra.enabled' => true, 'adsterra.pause_when_adsense' => true, 'adsterra.require_consent' => true, 'adsense.enabled' => false, 'consent.mode' => 'never']);
        return $slot;
    }

    public function test_only_banner_and_native_codes_are_accepted(): void
    {
        $this->assertSame([], AdSlot::adsterraCodeProblems(self::BANNER_728));
        $this->assertSame([], AdSlot::adsterraCodeProblems(self::NATIVE));
        $this->assertNotEmpty(AdSlot::adsterraCodeProblems(self::POPUNDER));
        $this->assertNotEmpty(AdSlot::adsterraCodeProblems(self::SMARTLINK));
        $this->assertNotEmpty(AdSlot::adsterraCodeProblems(self::BANNER_728.'<script>window.open("https://x.test")</script>'));
        $this->assertSame([728, 90], (new AdSlot)->adsterraSize(self::BANNER_728));
        $this->assertNull((new AdSlot)->adsterraSize(self::NATIVE));
    }

    public function test_admin_page_saves_valid_code_and_rejects_popunder(): void
    {
        $slot = AdSlot::where('key', 'sidebar')->firstOrFail();
        $this->actingAs($this->admin)->get('/admin/monetization/adsterra')->assertOk()->assertSee('Adsterra settings')->assertSee($slot->name);

        $this->actingAs($this->admin)->put("/admin/monetization/adsterra/slots/{$slot->id}", ['adsterra_enabled' => 1, 'adsterra_code' => self::POPUNDER])
            ->assertSessionHasErrors('adsterra_code');
        $this->assertFalse($slot->fresh()->adsterra_enabled);

        $this->actingAs($this->admin)->put("/admin/monetization/adsterra/slots/{$slot->id}", ['adsterra_enabled' => 1, 'adsterra_code' => self::BANNER_728, 'adsterra_code_mobile' => self::BANNER_728])
            ->assertSessionHasErrors('adsterra_code_mobile');

        $this->actingAs($this->admin)->put("/admin/monetization/adsterra/slots/{$slot->id}", ['adsterra_enabled' => 1, 'adsterra_code' => self::BANNER_728, 'adsterra_code_mobile' => self::BANNER_320])
            ->assertSessionHasNoErrors();
        $this->assertTrue($slot->fresh()->adsterra_enabled);

        $this->actingAs($this->admin)->put('/admin/monetization/adsterra/settings', ['adsterra_enabled' => 1, 'adsterra_pause_when_adsense' => 1, 'adsterra_require_consent' => 1])
            ->assertSessionHasNoErrors();
        $this->assertTrue(setting_bool('adsterra.enabled'));
        $this->actingAs($this->admin)->get('/admin/monetization/checklist')->assertOk()->assertSee('Adsterra slots contain only Banner');
    }

    public function test_banner_serves_to_visitors_on_desktop(): void
    {
        $this->enableHeaderBanner();
        $this->withHeaders(['User-Agent' => self::DESKTOP_UA])->get('/')
            ->assertOk()->assertSee('data-ad-provider="adsterra"', false)->assertSee('highperformanceformat.com/0123456789abcdef0123456789abcdef/invoke.js', false)
            ->assertDontSee('adsbygoogle.js', false);
    }

    public function test_wide_banner_is_skipped_on_mobile_unless_a_mobile_code_exists(): void
    {
        $this->enableHeaderBanner();
        $this->withHeaders(['User-Agent' => self::MOBILE_UA])->get('/')->assertOk()->assertDontSee('data-ad-provider="adsterra"', false);

        $this->enableHeaderBanner(self::BANNER_320);
        $this->withHeaders(['User-Agent' => self::MOBILE_UA])->get('/')->assertOk()
            ->assertSee('fedcba9876543210fedcba9876543210/invoke.js', false)->assertDontSee('0123456789abcdef0123456789abcdef/invoke.js', false);
    }

    public function test_paused_while_adsense_is_enabled(): void
    {
        $this->enableHeaderBanner();
        $this->settings(['adsense.enabled' => true, 'adsense.client_id' => 'pub-1234567890123456']);
        $this->withHeaders(['User-Agent' => self::DESKTOP_UA])->get('/')->assertOk()->assertDontSee('data-ad-provider="adsterra"', false);

        $this->settings(['adsterra.pause_when_adsense' => false]);
        $this->withHeaders(['User-Agent' => self::DESKTOP_UA])->get('/')->assertOk()->assertSee('data-ad-provider="adsterra"', false);
    }

    public function test_not_served_to_bots_or_without_consent_in_consent_regions(): void
    {
        $this->enableHeaderBanner();
        $this->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)'])->get('/')->assertDontSee('data-ad-provider="adsterra"', false);

        $this->settings(['consent.mode' => 'auto']);
        // German visitor (CDN country header) with no consent cookie yet
        $this->withHeaders(['User-Agent' => self::DESKTOP_UA, 'CF-IPCountry' => 'DE'])->get('/')->assertOk()->assertDontSee('data-ad-provider="adsterra"', false);
        // ...after accepting advertising cookies
        $this->withUnencryptedCookie('vh_consent', 'v1.111')->withHeaders(['User-Agent' => self::DESKTOP_UA, 'CF-IPCountry' => 'DE'])->get('/')
            ->assertOk()->assertSee('data-ad-provider="adsterra"', false);
        // US visitor needs no consent banner choice
        $this->withHeaders(['User-Agent' => self::DESKTOP_UA, 'CF-IPCountry' => 'US'])->get('/')->assertOk()->assertSee('data-ad-provider="adsterra"', false);
    }

    public function test_not_served_to_logged_in_admins(): void
    {
        $this->enableHeaderBanner();
        $this->actingAs($this->admin)->withHeaders(['User-Agent' => self::DESKTOP_UA])->get('/')->assertOk()->assertDontSee('data-ad-provider="adsterra"', false);
    }

    public function test_current_adsterra_banner_code_format_is_accepted(): void
    {
        // Format shown in the Adsterra dashboard (no type attribute, loader domain varies)
        $code = "<script>\n  atOptions = {\n    'key' : '2852cab3b908a6f021af6b858d65619c',\n    'format' : 'iframe',\n    'height' : 60,\n    'width' : 468,\n    'params' : {}\n  };\n</script>\n<script src=\"https://pl28123456.effectivegatecpm.com/2852cab3b908a6f021af6b858d65619c/invoke.js\"></script>";
        $this->assertSame([], AdSlot::adsterraCodeProblems($code));
        $slot = AdSlot::where('key', 'in_article')->firstOrFail();
        $this->actingAs($this->admin)->put("/admin/monetization/adsterra/slots/{$slot->id}", ['adsterra_enabled' => 1, 'adsterra_code' => $code])->assertSessionHasNoErrors();
        $this->assertSame([468, 60], $slot->fresh()->adsterraSize($slot->fresh()->adsterra_code));
    }

    public function test_banner_code_variants_and_copy_paste_damage_are_accepted(): void
    {
        $key = 'fedcba9876543210fedcba9876543210';
        $docWrite = "<script type=\"text/javascript\">\n\tatOptions = {\n\t\t'key' : '{$key}',\n\t\t'format' : 'iframe',\n\t\t'height' : 50,\n\t\t'width' : 320,\n\t\t'params' : {}\n\t};\n\tdocument.write('<scr' + 'ipt type=\"text/javascript\" src=\"//www.highperformanceformat.com/{$key}/invoke.js\"></scr' + 'ipt>');\n</script>";
        $this->assertSame([], AdSlot::adsterraCodeProblems($docWrite), 'document.write variant');

        $curly = str_replace("'", "\u{2019}", self::BANNER_320);
        $this->assertSame([], AdSlot::adsterraCodeProblems(AdSlot::normalizeAdCode($curly)), 'curly quotes');

        $escaped = htmlspecialchars(self::BANNER_320, ENT_QUOTES);
        $this->assertSame([], AdSlot::adsterraCodeProblems(AdSlot::normalizeAdCode($escaped)), 'html-escaped');

        $missingLoader = "<script>atOptions = { 'key' : '{$key}', 'format' : 'iframe', 'height' : 50, 'width' : 320, 'params' : {} };</script>";
        $this->assertStringContainsString('loader', AdSlot::adsterraCodeProblems($missingLoader)[0]);

        $slot = AdSlot::where('key', 'header')->firstOrFail();
        $this->actingAs($this->admin)->put("/admin/monetization/adsterra/slots/{$slot->id}", ['adsterra_enabled' => 1, 'adsterra_code' => self::BANNER_728, 'adsterra_code_mobile' => $escaped])
            ->assertSessionHasNoErrors();
        $this->assertStringStartsWith('<script', $slot->fresh()->adsterra_code_mobile);
        $this->assertSame([320, 50], $slot->fresh()->adsterraSize($slot->fresh()->adsterra_code_mobile));
    }

    public function test_popunder_and_social_bar_load_only_while_adsense_is_off(): void
    {
        $social = "<script src='//pl28123456.effectivegatecpm.com/aa/bb/cc/aabbccsocial.js'></script>";
        $this->actingAs($this->admin)->put('/admin/monetization/adsterra/formats', ['popunder_enabled' => 1, 'popunder_code' => self::POPUNDER, 'socialbar_enabled' => 1, 'socialbar_code' => $social])
            ->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->put('/admin/monetization/adsterra/formats', ['popunder_enabled' => 1, 'popunder_code' => '<script>window.open("https://x.test")</script>'])
            ->assertSessionHasErrors('popunder_code');
        auth()->logout();
        $this->settings(['adsterra.enabled' => true, 'adsense.enabled' => false, 'consent.mode' => 'never']);

        $this->withHeaders(['User-Agent' => self::DESKTOP_UA])->get('/')->assertOk()
            ->assertSee('0123456789abcdef.js', false)->assertSee('aabbccsocial.js', false);

        $this->settings(['adsense.enabled' => true, 'adsense.client_id' => 'pub-1234567890123456', 'adsterra.pause_when_adsense' => false]);
        $this->withHeaders(['User-Agent' => self::DESKTOP_UA])->get('/')->assertOk()
            ->assertDontSee('0123456789abcdef.js', false)->assertDontSee('aabbccsocial.js', false);
    }

    public function test_smartlink_is_a_labelled_sponsored_link_and_deceptive_labels_are_rejected(): void
    {
        $this->actingAs($this->admin)->put('/admin/monetization/adsterra/formats', ['smartlink_enabled' => 1, 'smartlink_url' => 'https://www.example-offers.test/abc?key=123', 'smartlink_label' => 'Download now'])
            ->assertSessionHasErrors('smartlink_label');
        $this->actingAs($this->admin)->put('/admin/monetization/adsterra/formats', ['smartlink_enabled' => 1, 'smartlink_url' => 'https://www.example-offers.test/abc?key=123', 'smartlink_label' => 'Sponsored offer'])
            ->assertSessionHasNoErrors();
        auth()->logout();
        $this->settings(['adsterra.enabled' => true, 'adsense.enabled' => false, 'consent.mode' => 'never']);

        $post = Post::published()->vlogs()->first();
        $this->withHeaders(['User-Agent' => self::DESKTOP_UA])->get($post->url)->assertOk()
            ->assertSee('href="https://www.example-offers.test/abc?key=123"', false)->assertSee('rel="sponsored nofollow noopener"', false);
    }

    public function test_master_switch_off_serves_nothing(): void
    {
        $this->enableHeaderBanner();
        $this->settings(['adsterra.enabled' => false]);
        $this->withHeaders(['User-Agent' => self::DESKTOP_UA])->get('/')->assertOk()->assertDontSee('data-ad-provider="adsterra"', false);
    }

    public function test_thin_post_gets_no_adsterra_ads(): void
    {
        $slot = AdSlot::where('key', 'below_content')->firstOrFail();
        $slot->update(['adsterra_enabled' => true, 'adsterra_code' => self::NATIVE, 'desktop' => true]);
        $this->enableHeaderBanner();
        AdSlot::where('key', 'header')->update(['adsterra_enabled' => false]);
        cache()->flush();

        $post = Post::published()->vlogs()->first();
        $this->withHeaders(['User-Agent' => self::DESKTOP_UA])->get($post->url)->assertOk()->assertSee('container-0123456789abcdef0123456789abcdef', false);

        $post->update(['content' => '<p>Too short.</p>', 'video_type' => 'none']);
        cache()->flush();
        $this->withHeaders(['User-Agent' => self::DESKTOP_UA])->get($post->url)->assertOk()->assertDontSee('container-0123456789abcdef0123456789abcdef', false);
    }
}
