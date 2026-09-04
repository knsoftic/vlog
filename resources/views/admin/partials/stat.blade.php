@php
    // $label, $value (formatted), $prev (raw previous), $current (raw current), $note, $compare (bool), $unavailable
    $delta = isset($prev, $current) && ($compare ?? false) ? percent_change($current, $prev) : null;
@endphp
<div class="stat">
    <p class="stat-label">{{ $label }}</p>
    @if($unavailable ?? false)
        <p class="mt-1 text-sm font-semibold text-slate-400">Data unavailable</p>
        <p class="stat-note">{{ $note ?? 'Sync pending or not connected' }}</p>
    @else
        <p class="stat-value">{{ $value }}</p>
        @if($delta !== null)
            <p class="stat-delta {{ $delta >= 0 ? 'up' : 'down' }}">{{ $delta >= 0 ? '▲' : '▼' }} {{ abs($delta) }}% <span class="font-normal text-slate-400">vs previous</span></p>
        @endif
        @if($note ?? null)<p class="stat-note">{{ $note }}</p>@endif
    @endif
</div>
