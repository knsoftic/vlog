@extends('layouts.admin')
@section('title', 'Notifications')
@section('actions')<form method="post" action="{{ route('admin.notifications.read-all') }}">@csrf<button class="btn-secondary">Mark all as read</button></form>@endsection
@section('content')
<div class="card divide-y divide-slate-100 p-0">
    @forelse($notifications as $n)
        <div class="flex items-start gap-3 p-4 {{ $n->is_read ? '' : 'bg-indigo-50/40' }}">
            <span class="mt-1.5 h-2.5 w-2.5 shrink-0 rounded-full {{ $n->severity === 'critical' ? 'bg-rose-500' : ($n->severity === 'warning' ? 'bg-amber-500' : ($n->severity === 'success' ? 'bg-emerald-500' : 'bg-indigo-500')) }}"></span>
            <div class="min-w-0 flex-1"><p class="font-medium">{{ $n->title }}</p>@if($n->message)<p class="text-sm text-slate-600">{{ $n->message }}</p>@endif<p class="mt-1 text-xs text-slate-400">{{ $n->created_at->diffForHumans() }} · {{ $n->type }}</p></div>
            <form method="post" action="{{ route('admin.notifications.read', $n) }}">@csrf<button class="btn-secondary btn-sm">{{ $n->link ? 'Open' : 'Mark read' }}</button></form>
        </div>
    @empty<p class="p-10 text-center text-slate-400">No notifications.</p>@endforelse
</div>
<div class="mt-4">{{ $notifications->links() }}</div>
@endsection
