<?php

namespace Database\Seeders;

use App\Models\AdSlot;
use App\Models\Category;
use App\Models\HomeSection;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\Permission;
use App\Models\Post;
use App\Models\Role;
use App\Models\Setting;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;

/**
 * Idempotent seeder: safe to re-run, never deletes existing data.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->roles();
        $admin = $this->adminUser();
        $this->settings();
        $this->adSlots();
        $this->homeSections();
        $this->pages();
        $this->menus();
        $this->categoriesAndSamplePosts($admin);
        Cache::flush();
    }

    protected function roles(): void
    {
        $perms = [
            'Content' => ['posts.view' => 'View content', 'posts.create' => 'Create content', 'posts.edit' => 'Edit own content', 'posts.edit_any' => 'Edit any content', 'posts.publish' => 'Publish / schedule', 'posts.delete' => 'Delete content', 'categories.manage' => 'Manage categories & tags', 'media.manage' => 'Manage media library', 'comments.moderate' => 'Moderate comments', 'pages.manage' => 'Manage pages & appearance'],
            'Analytics' => ['analytics.view' => 'View analytics & reports'],
            'SEO' => ['seo.manage' => 'Manage SEO, redirects, Search Console'],
            'Monetization' => ['monetization.manage' => 'Manage AdSense, ad units, ads.txt'],
            'Users' => ['users.manage' => 'Manage users & authors', 'roles.manage' => 'Manage roles & permissions'],
            'System' => ['logs.view' => 'View logs', 'settings.manage' => 'Manage settings & integrations', 'backups.manage' => 'Manage backups'],
        ];
        $ids = [];
        foreach ($perms as $group => $list) {
            foreach ($list as $slug => $name) {
                $ids[$slug] = Permission::updateOrCreate(['slug' => $slug], ['name' => $name, 'group' => $group])->id;
            }
        }
        $roles = [
            'super_admin' => ['Super Admin', 100, array_keys($ids)],
            'admin' => ['Admin', 80, array_diff(array_keys($ids), ['roles.manage', 'backups.manage'])],
            'editor' => ['Editor', 60, ['posts.view', 'posts.create', 'posts.edit', 'posts.edit_any', 'posts.publish', 'posts.delete', 'categories.manage', 'media.manage', 'comments.moderate', 'pages.manage', 'analytics.view']],
            'author' => ['Author', 40, ['posts.view', 'posts.create', 'posts.edit', 'media.manage', 'analytics.view']],
            'seo_manager' => ['SEO Manager', 50, ['posts.view', 'posts.edit', 'posts.edit_any', 'seo.manage', 'analytics.view', 'pages.manage']],
        ];
        foreach ($roles as $slug => [$name, $level, $permSlugs]) {
            $role = Role::updateOrCreate(['slug' => $slug], ['name' => $name, 'level' => $level, 'is_system' => true]);
            if ($role->wasRecentlyCreated || $role->permissions()->count() === 0) {
                $role->permissions()->sync(array_values(array_intersect_key($ids, array_flip($permSlugs))));
            }
        }
    }

    protected function adminUser(): User
    {
        $email = env('ADMIN_EMAIL', 'admin@example.com');
        $user = User::where('email', $email)->first();
        if (! $user) {
            $user = User::create([
                'name' => env('ADMIN_NAME', 'Site Owner'),
                'email' => $email,
                'password' => env('ADMIN_PASSWORD', 'ChangeMe12345!'),
                'role_id' => Role::where('slug', 'super_admin')->value('id'),
                'is_active' => true,
                'bio' => 'Founder and lead creator.',
                'email_verified_at' => now(),
            ]);
        }
        return $user;
    }

    protected function settings(): void
    {
        foreach (\App\Services\SettingsService::defaults() as $key => $value) {
            if (! Setting::where('key', $key)->exists()) {
                Setting::create(['key' => $key, 'group' => explode('.', $key)[0], 'value' => $value]);
            }
        }
    }

    protected function adSlots(): void
    {
        foreach (AdSlot::defaults() as $slot) {
            AdSlot::firstOrCreate(['key' => $slot['key']], $slot);
        }
    }

    protected function homeSections(): void
    {
        foreach (HomeSection::defaults() as $i => $s) {
            HomeSection::firstOrCreate(['key' => $s['key']], $s + ['sort_order' => $i, 'enabled' => $s['key'] !== 'newsletter']);
        }
    }

    protected function pages(): void
    {
        $site = setting('site.name', 'VlogHub');
        $pages = [
            ['about-us', 'About Us', 'default', "<h2>Who we are</h2><p>{$site} is an independent vlogging and publishing platform. We create original video stories, guides and articles about the things we love.</p><h2>Our mission</h2><p>To publish honest, useful and entertaining content — and to keep improving it with feedback from our community.</p><h2>Contact</h2><p>Have a question or a collaboration idea? Visit our <a href=\"/page/contact-us\">contact page</a>.</p>"],
            ['contact-us', 'Contact Us', 'contact', '<p>We would love to hear from you. Fill in the form below and we will get back to you as soon as possible.</p>'],
            ['privacy-policy', 'Privacy Policy', 'default', $this->privacyPolicy($site)],
            ['cookie-policy', 'Cookie Policy', 'default', "<h2>What are cookies?</h2><p>Cookies are small text files stored on your device when you visit a website. We use them to make the site work, to understand how it is used, and — with your consent — to show advertising.</p><h2>Categories we use</h2><ul><li><strong>Necessary</strong> — required for the site to function (session, security, your cookie preferences).</li><li><strong>Analytics</strong> — our first-party analytics cookie and, if enabled, Google Analytics, to understand which content is popular.</li><li><strong>Advertising</strong> — Google AdSense and its certified partners may use cookies to serve and measure ads. In regions where required, personalised ads are only shown with your consent.</li></ul><h2>Managing cookies</h2><p>You can change your choices at any time using the <a href=\"#\" data-consent-open>cookie preferences</a> link in the footer, or through your browser settings. Learn how Google uses data from sites that use its services at <a href=\"https://policies.google.com/technologies/partner-sites\" rel=\"noopener\" target=\"_blank\">policies.google.com/technologies/partner-sites</a>.</p>"],
            ['terms-and-conditions', 'Terms & Conditions', 'default', "<h2>Use of this website</h2><p>By accessing {$site} you agree to these terms. Content is provided for general information and entertainment. You may not copy, redistribute or commercially exploit our content without written permission.</p><h2>User content</h2><p>Comments you submit must be respectful and lawful. We may moderate or remove content at our discretion.</p><h2>Limitation of liability</h2><p>We do our best to keep information accurate but make no warranties. We are not liable for losses arising from use of this site or third-party links.</p><h2>Changes</h2><p>We may update these terms from time to time; continued use means acceptance of the current version.</p>"],
            ['disclaimer', 'Disclaimer', 'default', "<p>All content on {$site} reflects the personal opinions and experiences of its authors. It is provided for informational purposes only and does not constitute professional advice.</p><p>Some pages may contain affiliate links or advertising. Advertising is clearly labelled and does not influence our editorial content.</p><p>External links are provided for convenience; we are not responsible for the content or privacy practices of third-party sites.</p>"],
        ];
        foreach ($pages as $i => [$slug, $title, $template, $content]) {
            Page::firstOrCreate(['slug' => $slug], ['title' => $title, 'template' => $template, 'content' => $content, 'status' => 'published', 'is_system' => true, 'show_in_footer' => true, 'sort_order' => $i, 'meta_description' => \Illuminate\Support\Str::limit(strip_tags($content), 155, '')]);
        }
    }

    protected function privacyPolicy(string $site): string
    {
        return "<p><em>Last updated: ".now()->format('F j, Y')."</em></p>
<h2>1. Who we are</h2><p>{$site} (\"we\", \"us\") operates this website. This policy explains what data we collect, why, and the choices you have.</p>
<h2>2. Data we collect</h2><ul><li><strong>Usage data</strong> — pages viewed, approximate country, device and browser type, referrer and how long you engage with content. Our first-party analytics uses a random identifier stored in a cookie; we do not store your IP address as an identifier.</li><li><strong>Contact & comments</strong> — the name, email and message you submit voluntarily.</li><li><strong>Cookie preferences</strong> — your consent choices.</li></ul>
<h2>3. Google products we use</h2><p>We use <strong>Google Analytics 4</strong> to understand how the site is used, and <strong>Google AdSense</strong> to display advertising. Google and its partners use cookies and similar technologies to serve ads based on your visits to this and other websites, and to measure ad performance. You can opt out of personalised advertising at <a href=\"https://www.google.com/settings/ads\" rel=\"noopener\" target=\"_blank\">Google Ads Settings</a>. See how Google uses information from sites that use its services: <a href=\"https://policies.google.com/technologies/partner-sites\" rel=\"noopener\" target=\"_blank\">policies.google.com/technologies/partner-sites</a>. Third-party ad vendors may also use cookies as described in our Cookie Policy.</p>
<h2>4. Consent</h2><p>Where required by law (for example in the EEA, UK and Switzerland) we ask for your consent before setting analytics or advertising cookies, and we honour Google Consent Mode. You can change or withdraw consent at any time via the cookie preferences link in the footer.</p>
<h2>5. Legal bases & retention</h2><p>We process data on the basis of consent (analytics/advertising cookies), legitimate interest (security, fraud prevention, basic aggregate statistics) and contract (responding to your messages). Raw analytics data is deleted or anonymised according to our retention schedule; aggregated statistics contain no personal data.</p>
<h2>6. Your rights</h2><p>Depending on your location you may have the right to access, correct, delete or restrict processing of your data, and to object or withdraw consent. Contact us via the contact page to exercise these rights.</p>
<h2>7. Security</h2><p>We use encryption, access controls and logging to protect the site and your data.</p>
<h2>8. Children</h2><p>This site is not directed at children under 13 and we do not knowingly collect their data.</p>
<h2>9. Changes</h2><p>We may update this policy; the date above shows the latest revision.</p>";
    }

    protected function menus(): void
    {
        if (MenuItem::count() === 0) {
            foreach ([['Home', '/'], ['Vlogs', '/vlogs'], ['Articles', '/articles'], ['Trending', '/trending'], ['Categories', '/categories']] as $i => [$l, $u]) {
                MenuItem::create(['location' => 'header', 'label' => $l, 'url' => $u, 'sort_order' => $i]);
            }
            foreach ([['Popular', '/popular'], ['Search', '/search']] as $i => [$l, $u]) {
                MenuItem::create(['location' => 'footer', 'label' => $l, 'url' => $u, 'sort_order' => $i]);
            }
        }
    }

    protected function categoriesAndSamplePosts(User $author): void
    {
        if (Category::count() > 0) {
            return;
        }
        $cats = [
            'Travel' => ['Adventures, city guides and hidden gems from around the world.', ['Asia', 'Europe', 'Road Trips']],
            'Technology' => ['Reviews, tutorials and honest opinions on gadgets and software.', ['Reviews', 'Tutorials']],
            'Lifestyle' => ['Daily vlogs, routines, food and everything in between.', ['Food', 'Fitness']],
            'Creators' => ['Behind the scenes of making videos and growing a channel.', []],
        ];
        $made = [];
        foreach ($cats as $i => $name) {
            // placeholder loop replaced below
        }
        $i = 0;
        foreach ($cats as $name => [$desc, $children]) {
            $c = Category::create(['name' => $name, 'description' => $desc, 'status' => 'active', 'sort_order' => $i++]);
            $made[$name] = $c;
            foreach ($children as $j => $child) {
                Category::create(['name' => $child, 'parent_id' => $c->id, 'status' => 'active', 'sort_order' => $j]);
            }
        }
        if (Post::count() > 0) {
            return;
        }
        $lorem = fn (string $topic) => "<p>Welcome to this episode. In this {$topic} vlog I walk you through the whole journey from planning to the final result, sharing the small decisions that made the biggest difference along the way.</p><h2>Why this matters</h2><p>Most guides skip the messy middle part. Here I keep the camera rolling through the mistakes, the detours and the moments that actually taught me something, because that is where the useful lessons hide.</p><p>You will see the full setup, the tools I used, what it cost, and what I would do differently next time. If you are planning something similar, this should save you a few hours of research and at least one expensive mistake.</p><h2>Key takeaways</h2><ul><li>Plan for twice the time you think you need.</li><li>Keep the gear simple; the story matters more than the equipment.</li><li>Ask locals — the best tips never show up in search results.</li><li>Back everything up before you move on to the next stop.</li></ul><h2>What is next</h2><p>Next week I am covering the follow-up questions from the comments, so leave yours below. Thanks for watching, and if you enjoyed this one, sharing it genuinely helps the channel grow.</p><p>Timestamps, links and the full gear list are in the description. See you in the next one.</p>";
        $samples = [
            ['vlog', '48 Hours in Istanbul on a Budget', 'Travel', 'Asia', 'dQw4w9WgXcQ', ['istanbul', 'budget travel', 'city guide'], true, true],
            ['vlog', 'Building My Dream Desk Setup for Under $500', 'Technology', 'Reviews', 'ysz5S6PUM-U', ['desk setup', 'productivity', 'budget'], true, false],
            ['vlog', 'A Full Day of Eating Street Food in Lahore', 'Lifestyle', 'Food', 'jNQXAC9IVRw', ['street food', 'lahore', 'food vlog'], false, true],
            ['vlog', 'Road Trip Across the Scottish Highlands', 'Travel', 'Road Trips', 'aqz-KE-bpKQ', ['scotland', 'road trip', 'camping'], true, false],
            ['vlog', 'How I Edit a Vlog in Under 2 Hours', 'Creators', null, 'hY7m5jjJ9mM', ['editing', 'workflow', 'youtube'], false, true],
            ['article', 'The Complete Beginner Guide to Vlogging Gear', 'Creators', null, null, ['gear', 'cameras', 'beginner'], false, false],
            ['article', 'Ten Travel Apps I Cannot Live Without', 'Technology', 'Tutorials', null, ['apps', 'travel', 'tools'], false, false],
            ['article', 'My Morning Routine for Creative Focus', 'Lifestyle', 'Fitness', null, ['routine', 'focus', 'habits'], false, false],
        ];
        foreach ($samples as $k => [$type, $title, $cat, $sub, $yt, $tags, $featured, $trending]) {
            $post = Post::create([
                'type' => $type, 'title' => $title, 'excerpt' => \Illuminate\Support\Str::limit(strip_tags($lorem($cat)), 180, ''),
                'content' => $lorem(strtolower($cat)), 'video_type' => $yt ? 'youtube' : 'none', 'video_url' => $yt ? 'https://www.youtube.com/watch?v='.$yt : null,
                'category_id' => $made[$cat]->id, 'subcategory_id' => $sub ? Category::where('name', $sub)->value('id') : null,
                'author_id' => $author->id, 'created_by' => $author->id, 'status' => 'published', 'published_at' => now()->subDays(count($samples) - $k)->subHours($k),
                'is_featured' => $featured, 'is_trending' => $trending, 'is_recommended' => $k % 3 === 0, 'allow_comments' => true,
                'meta_description' => 'Watch '.$title.' — an original '.$type.' from '.setting('site.name', 'VlogHub').' with practical tips and behind-the-scenes moments.',
                'focus_keyword' => strtolower($cat), 'featured_image_alt' => $title,
            ]);
            $post->tags()->sync(Tag::findOrCreateByNames($tags));
        }
        foreach (Category::all() as $c) {
            $c->update(['posts_count' => Post::published()->where('category_id', $c->id)->count()]);
        }
    }
}
