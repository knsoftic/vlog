@php /** @var \App\Support\DateRange $range */ @endphp
<form method="get" class="flex flex-wrap items-center gap-2" x-data="{ preset: '{{ $range->preset }}' }">
    @foreach(request()->except(['range', 'from', 'to', 'compare', 'page']) as $k => $v)
        @if(is_scalar($v))<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endif
    @endforeach
    <select name="range" x-model="preset" @change="if (preset !== 'custom') $el.form.submit()" class="select w-auto">
        @foreach(\App\Support\DateRange::PRESETS as $k => $label)<option value="{{ $k }}">{{ $label }}</option>@endforeach
    </select>
    <template x-if="preset === 'custom'">
        <div class="flex items-center gap-2">
            <input type="date" name="from" value="{{ $range->from->toDateString() }}" class="input w-auto">
            <span class="text-slate-400">→</span>
            <input type="date" name="to" value="{{ $range->to->toDateString() }}" class="input w-auto">
            <button class="btn-secondary btn-sm">Apply</button>
        </div>
    </template>
    <label class="flex items-center gap-1.5 text-xs text-slate-600"><input type="checkbox" name="compare" value="1" class="checkbox" {{ $range->compare ? 'checked' : '' }} onchange="this.form.submit()"> Compare with previous period</label>
    <span class="text-xs text-slate-400">{{ $range->label() }}</span>
</form>
