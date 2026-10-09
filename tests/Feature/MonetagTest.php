<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use App\Services\Monetag;
use App\Services\SettingsService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Monetag: tag validation, verification meta, sw.js, serving rules and the AdSense hard block.
 */
class MonetagTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    public const ONCLICK = "<script>(function(d,z,s){s.src='https://'+d+'/401/'+z;try{(document.body||document.documentElement).appendChild(s)}catch(e){}})('groleegni.test',1234567,document.createElement('script'))</script>";
    public const INPAGE = "<script>(function(d,z,s){s.src='https://'+d+'/400/'+z;try{(document.body||document.documentElement).appendChild(s)}catch(e){}})('vemtoutcheeg.test',7654321,document.createElement('script'))</script>";
    public const MULTITAG = '<script src="https://quge5.test/88/tag.min.js" data-zone="135790" async data-cfasync="false"></script>';
    public const SW = "self.options = {\"domain\":\"3nbf4.test\",\"zoneId\":1234567}\nself.lary = \"\"\nimportScripts('https://3nbf4.test/act/files/service-worker.min.js?r=sw') // TEST-MONETAG-SW";

    protected const DESKTOP_UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/128.0 Safari/537.36';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->admin = User::where('email', 'admin@example.com')->firstOrFail();
    }

    protected function tearDown(): void
    {
        $sw = public_path('sw.js');
        if (is_file($sw) && str_contains((string) file_get_contents($sw), 'TEST-MONETAG-SW')) {
            unlink($sw);
        }
        parent::tearDown();
    }

    protected function settings(array $pairs): void
    {
        app(SettingsService::class)->setMany($pairs);
        cache()->flush();
    }

    protected function saveAsAdmin(array $data)
    {
        return $this->actingAs($this->admin)->put('/admin/monetization/monetag', $data + ['require_consent' => 1]);
    }

    public function test_tag_and_service_worker_validation(): void
    {
        foreach ([self::ONCLICK, self::INPAGE, self::MULTITAG] as $tag) {
            $this->assertSame([], Monetag::tagProblems($tag));
        }
        $this->assertNotEmpty(Monetag::tagProblems('<div>hello</div>'.self::ONCLICK));
        $this->assertNotEmpty(Monetag::tagProblems('<iframe src="https://x.test"></iframe>'));
        $this->assertNotEmpty(Monetag::tagProblems("<script>atOptions = {'key':'abc'};</script>"));

        $this->assertSame([], Monetag::serviceWorkerProblems(self::SW));
        $this->assertNotEmpty(Monetag::serviceWorkerProblems('<script>'.self::SW.'</script>'));
        $this->assertNotEmpty(Monetag::serviceWorkerProblems('console.log(1)'));
    }

    public function test_admin_page_saves_and_verification_meta_is_live_even_when_off(): void
    {
        $this->actingAs($this->admin)->get('/admin/monetization/monetag')->assertOk()->assertSee('Site verification')->assertSee('Vignette Banner');

        $this->saveAsAdmin(['verify_meta' => '<meta name="monetag" content="a1b2c3d4e5f6">', 'onclick_enabled' => 1, 'onclick_code' => '<p>nope</p>'])
            ->assertSessionHasErrors('onclick_code');

        $this->saveAsAdmin(['verify_meta' => '<meta name="monetag" content="a1b2c3d4e5f6">'])->assertSessionHasNoErrors();
        $this->assertSame('a1b2c3d4e5f6', setting('monetag.verify_meta'));
        auth()->logout();

        $this->withHeaders(['User-Agent' => self::DESKTOP_UA])->get('/')->assertOk()->assertSee('<meta name="monetag" content="a1b2c3d4e5f6">', false);
    }

    public function test_formats_load_only_while_adsense_is_off(): void
    {
        $this->saveAsAdmin(['enabled' => 1, 'onclick_enabled' => 1, 'onclick_code' => self::ONCLICK, 'inpage_enabled' => 1, 'inpage_code' => self::INPAGE])->assertSessionHasNoErrors();
        auth()->logout();
        $this->settings(['adsense.enabled' => false, 'consent.mode' => 'never']);

        $this->withHeaders(['User-Agent' => self::DESKTOP_UA])->get('/')->assertOk()
            ->assertSee("'groleegni.test',1234567", false)->assertSee("'vemtoutcheeg.test',7654321", false);

        $this->settings(['adsense.enabled' => true, 'adsense.client_id' => 'pub-1234567890123456']);
        $this->withHeaders(['User-Agent' => self::DESKTOP_UA])->get('/')->assertOk()
            ->assertDontSee('groleegni.test', false)->assertDontSee('vemtoutcheeg.test', false);
    }

    public function test_not_served_to_admins_bots_or_before_consent(): void
    {
        $this->saveAsAdmin(['enabled' => 1, 'multitag_enabled' => 1, 'multitag_code' => self::MULTITAG])->assertSessionHasNoErrors();
        $this->settings(['adsense.enabled' => false, 'consent.mode' => 'auto']);

        $this->actingAs($this->admin)->withHeaders(['User-Agent' => self::DESKTOP_UA])->get('/')->assertDontSee('quge5.test', false);
        auth()->logout();
        $this->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)'])->get('/')->assertDontSee('quge5.test', false);
        $this->withHeaders(['User-Agent' => self::DESKTOP_UA, 'CF-IPCountry' => 'DE'])->get('/')->assertDontSee('quge5.test', false);
        $this->withUnencryptedCookie('vh_consent', 'v1.111')->withHeaders(['User-Agent' => self::DESKTOP_UA, 'CF-IPCountry' => 'DE'])->get('/')->assertSee('quge5.test', false);
        $this->withHeaders(['User-Agent' => self::DESKTOP_UA, 'CF-IPCountry' => 'US'])->get('/')->assertSee('quge5.test', false);
    }

    public function test_service_worker_is_served_at_root(): void
    {
        $this->get('/sw.js')->assertNotFound();
        $this->saveAsAdmin(['sw_js' => self::SW])->assertSessionHasNoErrors();
        $this->get('/sw.js')->assertOk()->assertHeader('Service-Worker-Allowed', '/')->assertSee("importScripts('https://3nbf4.test", false);
        $this->assertStringContainsString('TEST-MONETAG-SW', (string) file_get_contents(public_path('sw.js')));

        $this->saveAsAdmin(['sw_js' => ''])->assertSessionHasNoErrors();
        $this->assertFileDoesNotExist(public_path('sw.js'));
    }

    public function test_direct_link_is_a_labelled_sponsored_link(): void
    {
        $this->saveAsAdmin(['enabled' => 1, 'directlink_enabled' => 1, 'directlink_url' => 'https://direct.monetag.test/abc', 'directlink_label' => 'Play video'])
            ->assertSessionHasErrors('directlink_label');
        $this->saveAsAdmin(['enabled' => 1, 'directlink_enabled' => 1, 'directlink_url' => 'https://direct.monetag.test/abc', 'directlink_label' => 'Sponsored offer'])
            ->assertSessionHasNoErrors();
        auth()->logout();
        $this->settings(['adsense.enabled' => false, 'consent.mode' => 'never']);

        $post = Post::published()->vlogs()->first();
        $this->withHeaders(['User-Agent' => self::DESKTOP_UA])->get($post->url)->assertOk()
            ->assertSee('href="https://direct.monetag.test/abc"', false)->assertSee('rel="sponsored nofollow noopener"', false);
    }

    public function test_checklist_flags_monetag(): void
    {
        $this->settings(['monetag.enabled' => true]);
        $this->actingAs($this->admin)->get('/admin/monetization/checklist')->assertOk()->assertSee('Monetag is off');
    }
}
