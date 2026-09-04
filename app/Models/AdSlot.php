<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdSlot extends Model
{
    protected $fillable = [
        'key', 'name', 'position', 'description', 'code', 'ad_slot_id', 'ad_format', 'enabled', 'desktop', 'tablet', 'mobile',
        'is_safe_zone', 'safety_note', 'sort_order', 'paragraph_offset',
    ];

    protected $casts = [
        'enabled' => 'boolean', 'desktop' => 'boolean', 'tablet' => 'boolean', 'mobile' => 'boolean', 'is_safe_zone' => 'boolean',
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
