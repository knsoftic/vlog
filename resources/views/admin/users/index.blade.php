@extends('layouts.admin')
@section('title', $kind === 'authors' ? 'Authors' : 'Admins & Users')
@section('actions')<a href="{{ route('admin.users.create') }}" class="btn-primary">+ New User</a>@endsection
@section('content')
<form method="get" class="mb-3"><input type="search" name="s" value="{{ request('s') }}" placeholder="Search name or email…" class="input max-w-xs"></form>
<div class="table-wrap"><table class="table">
    <thead><tr><th>User</th><th>Role</th><th>Status</th><th class="text-right">Posts</th>@if($kind === 'authors')<th class="text-right">Total views</th><th class="text-right">Video views</th><th class="text-right">Avg engagement</th>@else<th>Last login</th>@endif<th class="text-right">Actions</th></tr></thead>
    <tbody>
    @forelse($users as $u)
        <tr>
            <td><div class="flex items-center gap-3"><img src="{{ $u->avatar_url }}" class="h-9 w-9 rounded-full object-cover" alt=""><div><p class="font-medium">{{ $u->name }}</p><p class="text-xs text-slate-500">{{ $u->email }}</p></div></div></td>
            <td><span class="badge-blue">{{ $u->role?->name ?? '—' }}</span></td>
            <td>@if($u->isLocked())<span class="badge-red">Locked</span>@elseif($u->is_active)<span class="badge-green">Active</span>@else<span class="badge-gray">Inactive</span>@endif</td>
            <td class="text-right">{{ $u->published_posts_count }} <span class="text-xs text-slate-400">/ {{ $u->posts_count }}</span></td>
            @if($kind === 'authors')
                <td class="text-right">{{ number_format($stats[$u->id]['views'] ?? 0) }}</td><td class="text-right">{{ number_format($stats[$u->id]['video_views'] ?? 0) }}</td><td class="text-right">{{ human_duration($stats[$u->id]['avg_engagement'] ?? 0) }}</td>
            @else
                <td class="text-xs text-slate-500">{{ $u->last_login_at?->diffForHumans() ?? 'never' }}</td>
            @endif
            <td class="text-right whitespace-nowrap"><a href="{{ $u->url }}" target="_blank" class="btn-secondary btn-sm">Profile</a> <a href="{{ route('admin.users.edit', $u) }}" class="btn-secondary btn-sm">Edit</a> @if($u->id !== auth()->id())<form method="post" action="{{ route('admin.users.destroy', $u) }}" class="inline" data-confirm="Delete this user? Their posts will be kept.">@csrf @method('DELETE')<button class="btn-danger btn-sm">Delete</button></form>@endif</td>
        </tr>
    @empty<tr><td colspan="8" class="py-10 text-center text-slate-400">No users found.</td></tr>@endforelse
    </tbody></table></div>
<div class="mt-4">{{ $users->links() }}</div>
@endsection
