<?php

namespace App\Services;

use App\Http\Middleware\TrackPageView;

/**
 * Single place that decides which ad network may serve on the current request.
 *
 *  - AdSense: Settings → Monetization (publisher id + enabled).
 *  - Adsterra: Banner / Native Banner only. Popunder, Social Bar and Smartlink are never rendered,
 *    because Google does not allow AdSense on sites that contain or trigger pop-unders.
 *  - By default Adsterra pauses itself while AdSense is enabled, so the site stays clean for review.
 *  - Ads are never shown to logged-in admins (accidental self-clicks) or bots, when configured.
 *
 * Bound as a singleton so the per-request checks run once.
 */
class AdServing
{
    protected ?bool $viewerEligible = null;
    protected ?array $consent = null;

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

    public function viewerEligible(): bool
    {
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
        return $this->consent ??= app(TrackPageView::class)->consentState(request());
    }
}
