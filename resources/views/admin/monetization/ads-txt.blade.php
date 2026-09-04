@extends('layouts.admin')
@section('title', 'Ads.txt Manager')
@section('actions')<a href="{{ route('ads-txt') }}" target="_blank" class="btn-secondary">Open /ads.txt</a>@endsection
@section('content')
<div class="grid gap-5 lg:grid-cols-3">
    <form method="put" action="{{ route('admin.monetization.ads-txt.update') }}" method="post" class="card lg:col-span-2">@csrf @method('PUT')
        <div class="flex items-center justify-between"><h2 class="card-title">ads.txt content</h2><span class="text-xs text-slate-500">Last updated: {{ $updatedAt ? \Carbon\Carbon::parse($updatedAt)->format('M j, Y H:i') : 'never (auto-generated from publisher id)' }}</span></div>
        <textarea name="ads_txt" rows="12" class="textarea mt-3 font-mono text-xs" placeholder="google.com, pub-0000000000000000, DIRECT, f08c47fec0942fa0">{{ old('ads_txt', $content) }}</textarea>
        @if($suggested)<p class="help">Suggested Google line for your publisher id: <code class="select-all">{{ $suggested }}</code></p>@endif
        <div class="mt-3 flex gap-2"><button class="btn-primary">Save & publish</button></div>
    </form>
    <div class="space-y-5">
        <div class="card">
            <h2 class="card-title">Validation</h2>
            <p class="mt-2 text-sm">{{ $validation['records'] }} record(s) in {{ $validation['lines'] }} line(s).</p>
            @if($validation['errors'])<ul class="mt-2 space-y-1 text-sm text-rose-600">@foreach($validation['errors'] as $e)<li>✖ {{ $e }}</li>@endforeach</ul>@else<p class="mt-2 text-sm text-emerald-600">✔ No syntax errors.</p>@endif
            @foreach($validation['warnings'] as $w)<p class="mt-1 text-sm text-amber-600">⚠ {{ $w }}</p>@endforeach
        </div>
        <div class="card text-sm text-slate-600">
            <h2 class="card-title">How it works</h2>
            <p class="mt-2">The file is served dynamically at <code>/ads.txt</code> (publicly accessible, cached 1 hour). Format per IAB spec: <code>domain, publisher id, DIRECT|RESELLER, certification id</code>. Publisher id: <b>{{ $publisher ?: 'not set' }}</b>.</p>
        </div>
    </div>
</div>
@endsection
