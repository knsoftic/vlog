@extends('layouts.admin')
@section('title', 'System Health')
@section('content')
<div class="grid grid-cols-2 gap-3 md:grid-cols-4 xl:grid-cols-6">
    @include('admin.partials.stat', ['label' => '404 errors (24h)', 'value' => number_format($summary['404_24h'])])
    @include('admin.partials.stat', ['label' => '500 / exceptions (24h)', 'value' => number_format($summary['500_24h'])])
    @include('admin.partials.stat', ['label' => 'Failed API / syncs (7d)', 'value' => number_format($summary['api_failures_7d'])])
    @include('admin.partials.stat', ['label' => 'Broken links', 'value' => number_format($summary['broken_links'])])
    @include('admin.partials.stat', ['label' => 'Media storage', 'value' => round($storage['media_bytes'] / 1048576, 1).' MB', 'note' => $storage['disk_free'] ? round($storage['disk_free'] / 1073741824, 1).' GB free' : null])
    @include('admin.partials.stat', ['label' => 'Database', 'value' => $db['status'] === 'ok' ? round(($db['size'] ?? 0) / 1048576, 1).' MB' : 'Error', 'note' => $db['status'] === 'ok' ? ($db['tables'].' tables · '.$db['version']) : $db['status']])
</div>
<div class="mt-5 grid gap-5 lg:grid-cols-3">
    <div class="card">
        <div class="flex items-center justify-between"><h2 class="card-title">Scheduled jobs</h2><span class="{{ $schedulerOk ? 'badge-green' : 'badge-red' }}">{{ $schedulerOk ? 'scheduler running' : 'scheduler not running' }}</span></div>
        @unless($schedulerOk)<p class="alert-warning mt-3 mb-0 text-xs">No job ran in the last 15 minutes. Add a cron entry: <code>* * * * * php {{ base_path('artisan') }} schedule:run</code> (or run <code>php artisan schedule:work</code> locally).</p>@endunless
        <table class="table mt-3"><thead><tr><th>Job</th><th>Last run</th></tr></thead><tbody>
        @foreach($expectedJobs as $j)<tr><td class="font-mono text-xs">{{ $j }}</td><td class="text-xs text-slate-500">{{ isset($lastRuns[$j]) ? \Carbon\Carbon::parse($lastRuns[$j]->last_at)->diffForHumans() : 'never' }}</td></tr>@endforeach
        </tbody></table>
        <h3 class="mt-4 text-xs font-semibold uppercase text-slate-400">Recent runs</h3>
        <ul class="mt-2 max-h-64 space-y-1 overflow-auto text-xs">@foreach($jobs as $j)<li class="flex gap-2"><span class="{{ $j->status === 'success' ? 'text-emerald-500' : ($j->status === 'failed' ? 'text-rose-500' : 'text-amber-500') }}">●</span><span class="font-mono">{{ $j->name }}</span><span class="text-slate-400">{{ $j->started_at->diffForHumans() }}</span>@if($j->message)<span class="truncate text-slate-500" title="{{ $j->message }}">{{ \Illuminate\Support\Str::limit($j->message, 60) }}</span>@endif</li>@endforeach</ul>
    </div>
    <div class="lg:col-span-2">
        <div class="mb-3 flex flex-wrap items-center gap-2">
            <form method="get" class="flex gap-2"><select name="type" class="select w-40"><option value="">All types</option>@foreach($types as $t)<option @selected(request('type') === $t)>{{ $t }}</option>@endforeach</select><button class="btn-secondary">Filter</button></form>
            <form method="post" action="{{ route('admin.logs.system.clear') }}" class="ml-auto" data-confirm="Clear these log entries?">@csrf<input type="hidden" name="type" value="{{ request('type') }}"><button class="btn-secondary btn-sm">Clear {{ request('type') ?: 'all' }}</button></form>
        </div>
        <div class="table-wrap"><table class="table"><thead><tr><th>Type</th><th>Message</th><th>URL</th><th class="text-right">Count</th><th>Last seen</th></tr></thead><tbody>
        @forelse($logs as $l)
            <tr><td><span class="{{ in_array($l->type, ['500', 'exception']) ? 'badge-red' : ($l->type === '404' ? 'badge-yellow' : 'badge-blue') }}">{{ $l->type }}</span></td><td class="max-w-md text-xs">{{ \Illuminate\Support\Str::limit($l->message, 160) }}@if($l->context)<span class="block text-slate-400">{{ json_encode($l->context) }}</span>@endif</td><td class="max-w-xs truncate text-xs text-slate-500" title="{{ $l->url }}">{{ $l->url ? parse_url($l->url, PHP_URL_PATH) : '' }}@if($l->referrer)<span class="block text-slate-400">from {{ \Illuminate\Support\Str::limit($l->referrer, 40) }}</span>@endif</td><td class="text-right">{{ $l->occurrences }}</td><td class="whitespace-nowrap text-xs text-slate-500">{{ $l->last_seen_at?->diffForHumans() }}</td></tr>
        @empty<tr><td colspan="5" class="py-10 text-center text-slate-400">No system events. 🎉</td></tr>@endforelse
        </tbody></table></div>
        <div class="mt-4">{{ $logs->links() }}</div>
    </div>
</div>
@endsection
