@extends('layouts.admin')
@section('title', 'Broken Links')
@section('actions')<form method="post" action="{{ route('admin.seo.broken-links.run') }}">@csrf<button class="btn-primary">Run check now</button></form>@endsection
@section('content')
<p class="alert-info">Outbound links in published content are checked weekly (and on demand). Last run: {{ $lastRun ? $lastRun->started_at->diffForHumans().' — '.$lastRun->status.($lastRun->message ? ' · '.$lastRun->message : '') : 'never' }}.</p>
<div class="mb-3 flex gap-2"><a href="{{ route('admin.seo.broken-links') }}" class="tab {{ !request('resolved') ? 'active' : '' }}">Open</a><a href="{{ route('admin.seo.broken-links', ['resolved' => 1]) }}" class="tab {{ request('resolved') ? 'active' : '' }}">Resolved</a></div>
<div class="table-wrap"><table class="table"><thead><tr><th>In post</th><th>Target URL</th><th>Status</th><th>Checked</th><th class="text-right">Actions</th></tr></thead><tbody>
@forelse($links as $l)
    <tr><td>@if($l->post)<a href="{{ route('admin.'.($l->post->type === 'vlog' ? 'vlogs' : 'articles').'.edit', $l->post) }}" class="font-medium text-indigo-700 hover:underline">{{ $l->post->title }}</a>@else —@endif</td><td class="max-w-md truncate font-mono text-xs" title="{{ $l->target_url }}"><a href="{{ $l->target_url }}" target="_blank" rel="noopener">{{ $l->target_url }}</a></td><td>{{ $l->status_code ? '<span class="badge-red">'.$l->status_code.'</span>' : '<span class="badge-yellow">'.($l->error ?: 'unreachable').'</span>' }}</td><td class="text-xs text-slate-500">{{ $l->checked_at?->diffForHumans() }}</td><td class="text-right">@unless($l->is_resolved)<form method="post" action="{{ route('admin.seo.broken-links.resolve', $l) }}">@csrf<button class="btn-secondary btn-sm">Mark resolved</button></form>@endunless</td></tr>
@empty<tr><td colspan="5" class="py-10 text-center text-slate-400">No broken links found.</td></tr>@endforelse
</tbody></table></div>
<div class="mt-4">{{ $links->links() }}</div>
@endsection
