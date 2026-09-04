@extends('layouts.admin')
@section('title', 'Security Logs')
@section('content')
<div class="mb-4 grid grid-cols-3 gap-3">
    @include('admin.partials.stat', ['label' => 'Failed logins (24h)', 'value' => $summary['failed_24h']])
    @include('admin.partials.stat', ['label' => 'Rate-limited / blocked (24h)', 'value' => $summary['blocked_24h']])
    @include('admin.partials.stat', ['label' => 'Critical events (7d)', 'value' => $summary['critical_7d']])
</div>
<form method="get" class="mb-4 flex gap-2"><select name="type" class="select w-44"><option value="">All types</option>@foreach($types as $t)<option @selected(request('type') === $t)>{{ $t }}</option>@endforeach</select><select name="severity" class="select w-36"><option value="">All severities</option>@foreach(['info', 'warning', 'critical'] as $s)<option @selected(request('severity') === $s)>{{ $s }}</option>@endforeach</select><button class="btn-secondary">Filter</button></form>
<div class="table-wrap"><table class="table"><thead><tr><th>When</th><th>Type</th><th>Severity</th><th>Email / User</th><th>IP</th><th>Path</th><th>Details</th></tr></thead><tbody>
@forelse($logs as $l)
    <tr><td class="whitespace-nowrap text-xs text-slate-500">{{ $l->created_at->format('M j, H:i:s') }}</td><td><span class="badge-blue">{{ $l->type }}</span></td><td><span class="{{ $l->severity === 'critical' ? 'badge-red' : ($l->severity === 'warning' ? 'badge-yellow' : 'badge-gray') }}">{{ $l->severity }}</span></td><td class="text-xs">{{ $l->email }}</td><td class="text-xs text-slate-500">{{ $l->ip ?? 'anonymised' }}</td><td class="max-w-xs truncate text-xs">{{ $l->path }}</td><td class="text-xs text-slate-500">{{ $l->details ? json_encode($l->details) : '' }}</td></tr>
@empty<tr><td colspan="7" class="py-10 text-center text-slate-400">No security events.</td></tr>@endforelse
</tbody></table></div>
<div class="mt-4">{{ $logs->links() }}</div>
@endsection
