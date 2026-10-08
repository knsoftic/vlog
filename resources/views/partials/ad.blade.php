@php
    /** @var \App\Models\AdSlot|null $slot */
    $adServing = app(\App\Services\AdServing::class);
    $device = \App\Services\AnalyticsService::deviceFromRequest(request());
    $provider = null;
    $adsterraCode = null;
    if (($adsAllowed ?? false) && ! ($isPreview ?? false) && $slot && $adServing->viewerEligible() && $slot->deviceAllowed($device)) {
        $client = setting('adsense.client_id');
        if ($adServing->adsenseActive() && $slot->enabled && setting_bool('adsense.manual_ads', true) && $client && ($slot->code || $slot->ad_slot_id)) {
            $provider = 'adsense';
        } elseif ($slot->adsterra_enabled && $adServing->adsterraActive() && $adServing->adsterraConsentOk()) {
            $adsterraCode = $slot->adsterraCodeFor($device);
            $provider = $adsterraCode ? 'adsterra' : null;
        }
    }
    $adLabel = setting('adsense.label', 'Advertisement');
@endphp
@if($provider === 'adsense')
<aside class="ad-slot ad-slot--{{ $slot->key }}" aria-label="{{ $adLabel }}" data-ad-key="{{ $slot->key }}">
    <div class="ad-slot-inner">
        @if(setting_bool('adsense.show_label', true))<span class="ad-label">{{ $adLabel }}</span>@endif
        @if($slot->code)
            {!! $slot->code !!}
        @else
            <ins class="adsbygoogle"
                 style="display:block"
                 data-ad-client="ca-{{ ltrim(str_replace('ca-', '', $client), '-') }}"
                 data-ad-slot="{{ $slot->ad_slot_id }}"
                 @if($slot->ad_format === 'fluid') data-ad-format="fluid" data-ad-layout="in-article" @else data-ad-format="{{ $slot->ad_format }}" data-full-width-responsive="true" @endif></ins>
        @endif
    </div>
</aside>
@elseif($provider === 'adsterra')
@php $adSize = $slot->adsterraSize($adsterraCode); @endphp
<aside class="ad-slot ad-slot--{{ $slot->key }} ad-slot--adsterra" aria-label="{{ $adLabel }}" data-ad-key="{{ $slot->key }}" data-ad-provider="adsterra">
    <div class="ad-slot-inner" @if($adSize) style="min-height: {{ $adSize[1] + 24 }}px" @endif>
        @if(setting_bool('adsense.show_label', true))<span class="ad-label">{{ $adLabel }}</span>@endif
        {!! $adsterraCode !!}
    </div>
</aside>
@endif
