@extends('layouts.admin')
@section('title', 'AdSense Policy Checklist')
@section('content')
@php $ok = collect($checks)->where('ok', true)->count(); @endphp
<div class="card max-w-3xl">
    <div class="flex items-center justify-between"><h2 class="text-lg font-semibold">Pre-launch review</h2><span class="text-sm font-bold {{ $ok === count($checks) ? 'text-emerald-600' : 'text-amber-600' }}">{{ $ok }} / {{ count($checks) }} passed</span></div>
    <div class="progress mt-3"><div style="width: {{ round($ok / count($checks) * 100) }}%"></div></div>
    <ul class="mt-5 divide-y divide-slate-100">
        @foreach($checks as $c)<li class="flex items-start gap-3 py-3 text-sm"><span class="{{ $c['ok'] ? 'text-emerald-500' : 'text-rose-500' }}">{{ $c['ok'] ? '✔' : '✖' }}</span><span>{{ $c['label'] }}</span></li>@endforeach
    </ul>
    <p class="help mt-4">This checklist verifies technical readiness against current Google Publisher / AdSense program policies as implemented here. It does not guarantee approval — Google reviews content quality, originality and traffic separately. Re-run it before every launch and after policy changes.</p>
</div>
@endsection
