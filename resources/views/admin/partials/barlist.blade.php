@php
    // $title, $rows (value/sessions/page_views/visitors), $metric (sessions|page_views|visitors), $country (bool), $capitalize
    $metric = $metric ?? 'sessions';
    $max = max(1, collect($rows)->max($metric) ?? 1);
@endphp
<div class="card">
    <div class="flex items-center justify-between"><h3 class="card-title">{{ $title }}</h3><span class="text-[11px] uppercase text-slate-400">{{ str_replace('_', ' ', $metric) }}</span></div>
    <div class="mt-3 space-y-2">
        @forelse($rows as $r)
            <div class="bar-row">
                <span class="w-32 truncate {{ ($capitalize ?? true) ? 'capitalize' : '' }}" title="{{ $r['value'] }}">{{ ($country ?? false) ? \App\Services\GeoService::countryName($r['value']) : $r['value'] }}</span>
                <div class="flex-1"><div class="bar" style="width: {{ round($r[$metric] / $max * 100) }}%"></div></div>
                <span class="w-14 text-right text-xs font-semibold">{{ compact_number($r[$metric]) }}</span>
            </div>
        @empty<p class="text-sm text-slate-400">No data for this period.</p>@endforelse
    </div>
</div>
