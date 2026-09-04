@php
    /** @var \App\Models\AdSlot $slot */
    $client = setting('adsense.client_id');
    $show = ($adsAllowed ?? false) && $slot->enabled && setting_bool('adsense.manual_ads', true) && $client;
    $device = \App\Services\AnalyticsService::deviceFromRequest(request());
    $show = $show && $slot->isVisibleFor($device);
@endphp
@if($show)
<aside class="ad-slot ad-slot--{{ $slot->key }}" aria-label="{{ setting('adsense.label', 'Advertisement') }}" data-ad-key="{{ $slot->key }}">
    <div class="ad-slot-inner">
        @if(setting_bool('adsense.show_label', true))<span class="ad-label">{{ setting('adsense.label', 'Advertisement') }}</span>@endif
        @if($slot->code)
            {!! $slot->code !!}
        @elseif($slot->ad_slot_id)
            <ins class="adsbygoogle"
                 style="display:block"
                 data-ad-client="ca-{{ ltrim(str_replace('ca-', '', $client), '-') }}"
                 data-ad-slot="{{ $slot->ad_slot_id }}"
                 @if($slot->ad_format === 'fluid') data-ad-format="fluid" data-ad-layout="in-article" @else data-ad-format="{{ $slot->ad_format }}" data-full-width-responsive="true" @endif></ins>
        @endif
    </div>
</aside>
@endif
