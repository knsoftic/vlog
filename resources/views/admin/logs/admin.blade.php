@extends('layouts.admin')
@section('title', 'Admin Activity Logs')
@section('content')
<form method="get" class="mb-4 flex flex-wrap gap-2">
    <select name="user" class="select w-40"><option value="">All users</option>@foreach($users as $u)<option value="{{ $u->id }}" @selected(request('user') == $u->id)>{{ $u->name }}</option>@endforeach</select>
    <select name="action" class="select w-40"><option value="">All actions</option>@foreach($actions as $a)<option @selected(request('action') === $a)>{{ $a }}</option>@endforeach</select>
    <select name="module" class="select w-40"><option value="">All modules</option>@foreach($modules as $m)<option @selected(request('module') === $m)>{{ $m }}</option>@endforeach</select>
    <input type="date" name="from" value="{{ request('from') }}" class="input w-40"><input type="date" name="to" value="{{ request('to') }}" class="input w-40">
    <button class="btn-secondary">Filter</button>
</form>
<div class="table-wrap"><table class="table"><thead><tr><th>When</th><th>User</th><th>Action</th><th>Module</th><th>Description</th><th>IP / Device</th><th>Changes</th></tr></thead><tbody>
@forelse($logs as $l)
    <tr x-data="{open:false}">
        <td class="whitespace-nowrap text-xs text-slate-500">{{ $l->created_at->format('M j, Y') }}<br>{{ $l->created_at->format('H:i:s') }}</td>
        <td class="text-sm">{{ $l->user?->name ?? $l->user_name ?? 'system' }}</td>
        <td><span class="badge-blue">{{ $l->action }}</span></td><td class="text-xs">{{ $l->module }}</td>
        <td class="max-w-md text-sm">{{ $l->description }}</td>
        <td class="text-xs text-slate-500">{{ $l->ip ?? 'anonymised' }}<br>{{ $l->device }}</td>
        <td>@if($l->before || $l->after)<button type="button" @click="open=!open" class="btn-secondary btn-sm">Diff</button>
            <div x-show="open" x-cloak class="mt-2 grid max-w-xl gap-2 text-[11px] sm:grid-cols-2"><div><p class="font-semibold text-slate-500">Before</p><pre class="max-h-48 overflow-auto rounded bg-rose-50 p-2">{{ json_encode($l->before, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre></div><div><p class="font-semibold text-slate-500">After</p><pre class="max-h-48 overflow-auto rounded bg-emerald-50 p-2">{{ json_encode($l->after, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre></div></div>@endif</td>
    </tr>
@empty<tr><td colspan="7" class="py-10 text-center text-slate-400">No log entries.</td></tr>@endforelse
</tbody></table></div>
<div class="mt-4">{{ $logs->links() }}</div>
<p class="help mt-2">Passwords, tokens and API secrets are never written to logs. IPs are anonymised after {{ setting('analytics.security_retention_days', 180) }} days.</p>
@endsection
