<?php

namespace App\Services;

/**
 * Monetag (monetag.com) integration helpers.
 *
 * Formats are site-wide tags pasted from the Monetag dashboard (Sites → Add zone → Get tag):
 * MultiTag, OnClick (Popunder), In-Page Push, Push Notifications and Vignette Banner, plus a Direct Link.
 * Push Notifications also need Monetag's sw.js at the site root.
 *
 * Every Monetag format is OFF while AdSense is enabled: pop-unders, push prompts and interstitials that are
 * not started by the user are not allowed on AdSense sites. That rule lives in AdServing and is not overridable.
 */
class Monetag
{
    public const FORMATS = [
        'multitag' => ['MultiTag (all-in-one)', 'One tag that runs OnClick, Push, In-Page Push and Vignette together. Use this OR the single formats below, not both.'],
        'onclick' => ['OnClick (Popunder)', 'Opens an advertiser tab when the visitor clicks on the page.'],
        'inpage' => ['In-Page Push', 'Notification-style banner inside the page. Works on iOS too.'],
        'push' => ['Push Notifications', 'Asks the visitor to allow browser notifications. Needs the sw.js file below.'],
        'vignette' => ['Vignette Banner', 'Full-screen ad shown between page navigations.'],
    ];

    public const MAX_TAG_LENGTH = 3000;
    public const MAX_SW_LENGTH = 5000;

    /** Normalise pasted code (HTML-escaped tags, curly quotes, non-breaking spaces). */
    public static function normalize(?string $code): string
    {
        return \App\Models\AdSlot::normalizeAdCode($code);
    }

    /**
     * A Monetag tag must consist only of <script> elements (Monetag tags are small inline loaders or
     * <script src> tags). Any other HTML is rejected so the field cannot be misused for arbitrary markup.
     */
    public static function tagProblems(?string $code): array
    {
        $code = trim((string) $code);
        if ($code === '') {
            return [];
        }
        if (mb_strlen($code) > self::MAX_TAG_LENGTH) {
            return ['Code is too long (max '.self::MAX_TAG_LENGTH.' characters).'];
        }
        $scripts = '~<script\b[^>]*>.*?</script>~is';
        if (! preg_match($scripts, $code)) {
            return ['Paste the Monetag tag exactly as given in the dashboard (it starts with <script).'];
        }
        $rest = trim(preg_replace(['~<!--.*?-->~s', $scripts], '', $code));
        if ($rest !== '') {
            return ['Only <script> tags are allowed here. Remove any other HTML around the Monetag tag.'];
        }
        if (preg_match('~atOptions\s*=~', $code)) {
            return ['This looks like an Adsterra banner code. Paste it on the Adsterra Ads page instead.'];
        }
        return [];
    }

    /** sw.js content from Monetag: plain JavaScript that imports Monetag's worker script. */
    public static function serviceWorkerProblems(?string $code): array
    {
        $code = trim((string) $code);
        if ($code === '') {
            return [];
        }
        if (mb_strlen($code) > self::MAX_SW_LENGTH) {
            return ['sw.js content is too long (max '.self::MAX_SW_LENGTH.' characters).'];
        }
        if (preg_match('~<\s*/?\s*(script|html|body|div)\b~i', $code)) {
            return ['sw.js must be plain JavaScript (open the downloaded sw.js file in Notepad and paste its contents, without <script> tags).'];
        }
        if (! preg_match('~importScripts\s*\(~', $code)) {
            return ['This does not look like Monetag\'s sw.js. The file contains an importScripts(...) line.'];
        }
        return [];
    }

    /**
     * Write public/sw.js so the web server can serve it directly (aaPanel's nginx serves *.js from disk and never
     * reaches Laravel). Returns an error message when the file could not be written.
     */
    public static function writeServiceWorker(string $content): ?string
    {
        $path = public_path('sw.js');
        try {
            if (trim($content) === '') {
                if (is_file($path) && str_contains((string) @file_get_contents($path), 'importScripts')) {
                    @unlink($path);
                }
                return null;
            }
            if (@file_put_contents($path, rtrim($content)."\n") === false) {
                return 'Could not write public/sw.js (folder permissions). The /sw.js route still serves it, but on aaPanel you may need to upload the file to the public folder manually.';
            }
        } catch (\Throwable $e) {
            return 'Could not write public/sw.js: '.$e->getMessage();
        }
        return null;
    }
}
