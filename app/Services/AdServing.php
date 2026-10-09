<?php

namespace App\Services;

use App\Http\Middleware\TrackPageView;

/**
 * Single place that decides which ad network may serve on the current request.
 *
 *  - AdSense: Settings → Monetization (publisher id + enabled).
 *  - Adsterra Banner / Native: per ad slot. Pauses while AdSense is enabled (configurable).
 *  - Adsterra Popunder / Social Bar: site-wide scripts. ALWAYS off while AdSense is enabled, because Google
 *    does not allow AdSense on sites that contain or trigger pop-unders. Not overridable.
 *  - Adsterra Smartlink: a clearly labelled "Sponsored" link, follows the banner rules.
 *  - Ads are never shown to logged-in admins (accidental self-clicks) or bots, when configured.
 *
 * Bound as a singleton so the per-request checks run once.
 */
class AdServing
{
    protected ?bool $viewerEligible = null;
    protected ?array $consent = null;
    protected ?int $requestId = null;

    /** Memoised values belong to one request only (tests and long-running workers reuse the container). */
    protected function sync(): void
    {
        $id = spl_object_id(request());
        if ($this->requestId !== $id) {
            $this->requestId = $id;
            $this->viewerEligible = null;
            $this->consent = null;
        }
    }

    public function adsenseActive(): bool
    {
        return setting_bool('adsense.enabled') && (bool) setting('adsense.client_id');
    }

    public function adsterraActive(): bool
    {
        if (! setting_bool('adsterra.enabled')) {
            return false;
        }
        return ! (setting_bool('adsterra.pause_when_adsense', true) && $this->adsenseActive());
    }

    /** Adsterra has no Google Consent Mode, so in consent regions it only serves after advertising consent. */
    public function adsterraConsentOk(): bool
    {
        if (! setting_bool('adsterra.require_consent', true)) {
            return true;
        }
        return (bool) ($this->consent()['advertising'] ?? false);
    }

    public function anyActive(): bool
    {
        return $this->adsenseActive() || ($this->adsterraActive() && $this->adsterraConsentOk());
    }

    /** Popunder / Social Bar may load: Adsterra on, AdSense OFF (hard rule), consent given, real visitor. */
    public function scriptFormatsAllowed(): bool
    {
        return setting_bool('adsterra.enabled')
            && ! $this->adsenseActive()
            && $this->adsterraConsentOk()
            && $this->viewerEligible();
    }

    /** @return array<string,string> format key => code that should be printed before </body> */
    public function scriptFormats(): array
    {
        if (! $this->scriptFormatsAllowed()) {
            return [];
        }
        $out = [];
        foreach (['socialbar', 'popunder'] as $f) {
            $code = trim((string) setting("adsterra.{$f}_code"));
            if (setting_bool("adsterra.{$f}_enabled") && $code !== '' && ! \App\Models\AdSlot::adsterraScriptProblems($code)) {
                $out[$f] = $code;
            }
        }
        return $out;
    }

    /** Smartlink to render as a labelled sponsored link, or null. */
    public function smartlink(): ?array
    {
        $url = trim((string) setting('adsterra.smartlink_url'));
        if (! setting_bool('adsterra.smartlink_enabled') || $url === '' || ! $this->adsterraActive() || ! $this->adsterraConsentOk() || ! $this->viewerEligible()) {
            return null;
        }
        if (! preg_match('~^https://~i', $url) || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return null;
        }
        return ['url' => $url, 'label' => setting('adsterra.smartlink_label') ?: 'Sponsored offer'];
    }

    public function viewerEligible(): bool
    {
        $this->sync();
        if ($this->viewerEligible !== null) {
            return $this->viewerEligible;
        }
        $ok = true;
        if (auth()->check() && setting_bool('adsense.hide_for_admins', true)) {
            $ok = false;
        }
        if ($ok && setting_bool('adsense.hide_for_bots', true) && app(BotDetector::class)->classify(request())['is_bot']) {
            $ok = false;
        }
        return $this->viewerEligible = $ok;
    }

    public function consent(): array
    {
        $this->sync();
        return $this->consent ??= app(TrackPageView::class)->consentState(request());
    }
}
