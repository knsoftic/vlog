@extends('layouts.admin')
@section('title', 'Comments')
@section('content')
<div class="mb-4 flex gap-2">@foreach(['pending' => 'Pending', 'approved' => 'Approved', 'spam' => 'Spam'] as $k => $l)<a href="{{ route('admin.comments.index', ['status' => $k]) }}" class="tab {{ $status === $k ? 'active' : '' }}">{{ $l }} <span class="text-slate-400">{{ $counts[$k] ?? 0 }}</span></a>@endforeach</div>
<div class="table-wrap"><table class="table">
    <thead><tr><th>Comment</th><th>On</th><th>When</th><th class="text-right">Actions</th></tr></thead>
    <tbody>
    @forelse($comments as $c)
        <tr><td><p class="font-medium">{{ $c->name }} <span class="text-xs font-normal text-slate-400">{{ $c->email }}</span></p><p class="mt-1 text-sm text-slate-700">{{ $c->content }}</p></td>
            <td class="text-sm"><a href="{{ $c->post?->url }}#comments" target="_blank" class="text-indigo-700 hover:underline">{{ \Illuminate\Support\Str::limit($c->post?->title, 40) }}</a></td>
            <td class="text-xs text-slate-500">{{ $c->created_at->diffForHumans() }}</td>
            <td class="text-right whitespace-nowrap">
                @foreach(['approved' => 'Approve', 'spam' => 'Spam', 'pending' => 'Pending'] as $s => $l)@if($s !== $status)<form method="post" action="{{ route('admin.comments.update', $c) }}" class="inline">@csrf @method('PUT')<input type="hidden" name="status" value="{{ $s }}"><button class="btn-secondary btn-sm">{{ $l }}</button></form>@endif @endforeach
                <form method="post" action="{{ route('admin.comments.destroy', $c) }}" class="inline" data-confirm="Delete this comment?">@csrf @method('DELETE')<button class="btn-danger btn-sm">Delete</button></form>
            </td></tr>
    @empty<tr><td colspan="4" class="py-10 text-center text-slate-400">No {{ $status }} comments.</td></tr>@endforelse
    </tbody></table></div>
<div class="mt-4">{{ $comments->links() }}</div>
@endsection
