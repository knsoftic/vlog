<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdSlot extends Model
{
    protected $fillable = [
        'key', 'name', 'position', 'description', 'code', 'ad_slot_id', 'ad_format', 'enabled', 'desktop', 'tablet', 'mobile',
        'is_safe_zone', 'safety_note', 'sort_order', 'paragraph_offset',
        'adsterra_enabled', 'adsterra_code', 'adsterra_code_mobile',
    ];

    protected $casts = [
        'enabled' => 'boolean', 'desktop' => 'boolean', 'tablet' => 'boolean', 'mobile' => 'boolean', 'is_safe_zone' => 'boolean',
        'adsterra_enabled' => 'boolean',
    ];

    /**
     * Recommended, policy-safe placement zones. Placements outside of these are flagged in the admin UI.
     */
    public static function defaults(): array
    {
        return [
            ['key' => 'header', 'name' => 'Header Ad', 'position' => 'Header, after navigation', 'ad_format' => 'horizontal', 'sort_order' => 1,
                'description' => 'Displayed below the main navigation, above the page content. Kept at a safe distance from nav links.'],
            ['key' => 'in_article', 'name' => 'In-Article Ad', 'position' => 'Between article paragraphs', 'ad_format' => 'fluid', 'sort_order' => 2, 'paragraph_offset' => 3,
                'description' => 'Inserted after the Nth paragraph of the article body. Never placed directly next to the video player.'],
            ['key' => 'between_content', 'name' => 'Between Content Ad', 'position' => 'Between content sections / listing rows', 'ad_format' => 'auto', 'sort_order' => 3,
                'description' => 'Shown between content sections on the home page and between listing rows.'],
            ['key' => 'sidebar', 'name' => 'Sidebar Ad', 'position' => 'Sidebar (desktop / tablet)', 'ad_format' => 'rectangle', 'sort_order' => 4, 'mobile' => false,
                'description' => 'Sticky sidebar rectangle on wide screens. Hidden on mobile by default to protect readability.'],
            ['key' => 'below_content', 'name' => 'Below Content Ad', 'position' => 'After article / vlog content', 'ad_format' => 'auto', 'sort_order' => 5,
                'description' => 'Displayed after the main content ends, before related content.'],
            ['key' => 'related', 'name' => 'Related Content Ad', 'position' => 'Related-content area', 'ad_format' => 'auto', 'sort_order' => 6,
                'description' => 'Placed inside the related-content grid, visually separated with an "Advertisement" label.'],
            ['key' => 'footer', 'name' => 'Footer Ad', 'position' => 'Footer area', 'ad_format' => 'horizontal', 'sort_order' => 7,
                'description' => 'Displayed above the footer, away from footer links.'],
        ];
    }

    public function isVisibleFor(string $device): bool
    {
        return $this->enabled && (bool) ($this->{$device} ?? false);
    }

    /** Device toggle only (shared by AdSense and Adsterra). */
    public function deviceAllowed(string $device): bool
    {
        return (bool) ($this->{$device} ?? false);
    }

    /** Max banner width that still fits a phone screen. */
    public const MOBILE_MAX_WIDTH = 336;

    /** Adsterra code to render for a device, or null when nothing suitable is configured. */
    public function adsterraCodeFor(string $device): ?string
    {
        $mobileCode = trim((string) $this->adsterra_code_mobile);
        $code = trim((string) $this->adsterra_code);
        if ($device === 'mobile' && $mobileCode !== '') {
            $code = $mobileCode;
        }
        if ($code === '' || static::adsterraCodeProblems($code)) {
            return null;
        }
        $size = $this->adsterraSize($code);
        if ($device === 'mobile' && $size && $size[0] > self::MOBILE_MAX_WIDTH) {
            return null; // a 728px banner would break the mobile layout
        }
        return $code;
    }

    /** [width, height] from an Adsterra banner "atOptions" block, null for native banners. */
    public function adsterraSize(?string $code): ?array
    {
        $code = (string) $code;
        if (preg_match("/['\"]?width['\"]?\s*:\s*(\d+)/i", $code, $w) && preg_match("/['\"]?height['\"]?\s*:\s*(\d+)/i", $code, $h)) {
            return [(int) $w[1], (int) $h[1]];
        }
        return null;
    }

    /**
     * Banner / Native Banner validation, based on the Adsterra ad key (not on a particular loader domain or
     * file name, which Adsterra changes over time).
     *  - Banner: an "atOptions = { 'key' : '…' }" block plus an external <script src>.
     *  - Native: a <div id="container-KEY"> plus an external <script src>.
     * Popunder / Social Bar (bare script) and Smartlink (link) belong in their own fields and are rejected here.
     */
    public static function adsterraCodeProblems(?string $code): array
    {
        $code = trim((string) $code);
        if ($code === '') {
            return [];
        }
        $problems = [];
        if (mb_strlen($code) > 5000) {
            $problems[] = 'Code is too long (max 5000 characters).';
        }
        // Loader: a real <script src="//…">, or the document.write('<scr'+'ipt src="//…">') variant some banner sizes use.
        $hasLoader = (bool) preg_match('~<script\b[^>]*\bsrc\s*=\s*["\']?(?:https?:)?//~i', $code)
            || (bool) preg_match('~document\.write\s*\(.*?src\s*=\s*[^/\s>]{0,3}(?:https?:)?//~is', $code);
        $isBanner = (bool) preg_match('~atOptions\s*=\s*\{.*?["\']?key["\']?\s*:\s*["\'][A-Za-z0-9]{8,64}["\']~is', $code);
        $isNative = (bool) preg_match('~id\s*=\s*["\']container-[A-Za-z0-9]{8,64}["\']~i', $code);
        if (! $isBanner && ! $isNative) {
            $problems[] = preg_match('~atOptions~i', $code)
                ? 'The "atOptions" block has no ad key. Copy the complete code again from Adsterra → Get code.'
                : 'This is not an Adsterra Banner or Native Banner code: no "atOptions" block (Banner) or <div id="container-…"> (Native Banner) was found. Popunder, Social Bar and Smartlink go in the site-wide section.';
        } elseif (! $hasLoader) {
            $problems[] = 'The loader <script src="…"> line is missing. Copy both script tags from Adsterra → Get code.';
        }
        if (preg_match('/window\.open|location\.(href|replace|assign)|\.click\(\)|onclick\s*=|setInterval|addEventListener\(\s*[\'"](click|mousedown|touchstart)/i', $code)) {
            $problems[] = 'Code contains redirect, auto-click or popup logic, which is not allowed in a banner slot.';
        }
        if (preg_match('/<a\s[^>]*href=/i', $code)) {
            $problems[] = 'Links are not allowed in a banner slot. Use the Smartlink field for direct links.';
        }
        return $problems;
    }

    /**
     * Undo common copy/paste damage before validating or saving: HTML-escaped tags (&lt;script&gt;),
     * curly quotes from word processors / chat apps, and non-breaking spaces.
     */
    public static function normalizeAdCode(?string $code): string
    {
        $code = trim((string) $code);
        if ($code === '') {
            return '';
        }
        if (stripos($code, '&lt;script') !== false) {
            $code = html_entity_decode($code, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        $code = str_replace(["\u{2018}", "\u{2019}", "\u{201C}", "\u{201D}", "\u{00A0}"], ["'", "'", '"', '"', ' '], $code);
        return trim($code);
    }

    /**
     * Popunder / Social Bar fields: only external <script src="…"></script> tags, nothing inline.
     */
    public static function adsterraScriptProblems(?string $code): array
    {
        $code = trim((string) $code);
        if ($code === '') {
            return [];
        }
        if (mb_strlen($code) > 2000) {
            return ['Code is too long (max 2000 characters).'];
        }
        $scripts = '~<script\b[^>]*\bsrc\s*=\s*["\']?(?:https?:)?//[^"\'\s>]+["\']?[^>]*>\s*</script>~i';
        if (! preg_match($scripts, $code)) {
            return ['Paste the Adsterra code exactly as given: a <script src="//…"></script> tag.'];
        }
        $rest = trim(preg_replace(['~<!--.*?-->~s', $scripts], '', $code));
        if ($rest !== '') {
            return ['Only <script src="…"></script> tags are allowed here (no inline JavaScript or HTML).'];
        }
        return [];
    }

    /** Simple static policy checks for admin warnings. */
    public function policyWarnings(): array
    {
        $w = [];
        $code = (string) $this->code;
        if ($code !== '') {
            if (preg_match('/<script(?![^>]*pagead2\.googlesyndication\.com)[^>]*src=/i', $code)) {
                $w[] = 'Ad code contains a third-party script that is not the Google AdSense loader. Verify it is permitted.';
            }
            if (preg_match('/onclick|\.click\(\)|setInterval|location\.reload|window\.open/i', $code)) {
                $w[] = 'Ad code contains click/refresh/popup JavaScript. Auto-clicks, auto-refresh and pop-unders violate AdSense policies.';
            }
            if (preg_match('/click (here|the ad)|support us by clicking|click ads/i', $code)) {
                $w[] = 'Ad code contains wording that encourages clicks. This is not allowed.';
            }
            if (preg_match('/display\s*:\s*none|visibility\s*:\s*hidden|opacity\s*:\s*0/i', $code)) {
                $w[] = 'Ad code hides the ad unit. Hidden ads are a policy violation.';
            }
        }
        if (! $this->is_safe_zone) {
            $w[] = 'This placement is outside the recommended safe zones. Review it against the AdSense ad placement policies.';
        }
        return $w;
    }
}
