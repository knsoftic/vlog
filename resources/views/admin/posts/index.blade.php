@extends('layouts.admin')
@php $rk = $type === 'article' ? 'articles' : 'vlogs'; $label = $type === 'article' ? 'Articles' : 'Vlogs'; @endphp
@section('title', $label)
@section('actions')
    @permission('posts.create')<a href="{{ route("admin.$rk.create") }}" class="btn-primary">+ New {{ ucfirst($type) }}</a>@endpermission
    <a href="{{ route("admin.$rk.trash") }}" class="btn-secondary">Trash</a>
@endsection

@section('content')
<div class="mb-4 flex flex-wrap items-center gap-2">
    @foreach(['' => 'All', 'published' => 'Published', 'draft' => 'Drafts', 'scheduled' => 'Scheduled', 'unpublished' => 'Unpublished'] as $k => $l)
        <a href="{{ route("admin.$rk.index", array_filter(['status' => $k, 's' => request('s')])) }}" class="tab {{ request('status', '') === $k ? 'active' : '' }}">{{ $l }} <span class="text-slate-400">{{ $k === '' ? $counts->sum() : ($counts[$k] ?? 0) }}</span></a>
    @endforeach
    <form method="get" class="ml-auto flex gap-2">
        <input type="hidden" name="status" value="{{ request('status') }}">
        <input type="search" name="s" value="{{ request('s') }}" placeholder="Search title…" class="input w-48">
        <select name="category" class="select w-40" onchange="this.form.submit()"><option value="">All categories</option>@foreach($categories as $c)<option value="{{ $c->id }}" @selected(request('category') == $c->id)>{{ $c->name }}</option>@endforeach</select>
        <select name="author" class="select w-36" onchange="this.form.submit()"><option value="">All authors</option>@foreach($authors as $a)<option value="{{ $a->id }}" @selected(request('author') == $a->id)>{{ $a->name }}</option>@endforeach</select>
    </form>
</div>

<div class="table-wrap">
<table class="table">
    <thead><tr><th class="w-16"></th><th>Title</th><th>Category</th><th>Author</th><th>Status</th><th class="text-right">Views</th><th class="text-right">Plays</th><th>Date</th><th class="text-right">Actions</th></tr></thead>
    <tbody>
    @forelse($posts as $p)
        <tr>
            <td><div class="h-10 w-16 overflow-hidden rounded bg-slate-100">@if($p->thumbnail_url)<img src="{{ $p->thumbnail_url }}" class="h-full w-full object-cover" alt="">@endif</div></td>
            <td><a href="{{ route("admin.$rk.edit", $p) }}" class="font-medium text-slate-900 hover:text-indigo-700">{{ $p->title }}</a><p class="text-xs text-slate-400">/{{ $p->type }}/{{ $p->slug }} @if($p->is_featured)· <span class="badge-blue">Featured</span>@endif @if($p->is_trending)· <span class="badge-yellow">Trending</span>@endif</p></td>
            <td class="text-slate-500">{{ $p->category?->name ?? '—' }}</td>
            <td class="text-slate-500">{{ $p->author?->name ?? '—' }}</td>
            <td>@php $cls = ['published' => 'badge-green', 'draft' => 'badge-gray', 'scheduled' => 'badge-blue', 'unpublished' => 'badge-yellow'][$p->status] ?? 'badge-gray'; @endphp<span class="{{ $cls }}">{{ ucfirst($p->status) }}</span></td>
            <td class="text-right">{{ number_format($p->views_count) }}</td>
            <td class="text-right">{{ number_format($p->video_plays_count) }}</td>
            <td class="whitespace-nowrap text-xs text-slate-500">{{ ($p->status === 'scheduled' ? $p->scheduled_at : $p->published_at)?->format('M j, Y H:i') ?? $p->created_at->format('M j, Y') }}</td>
            <td class="text-right whitespace-nowrap">
                <a href="{{ route('admin.posts.analytics', $p) }}" class="btn-secondary btn-sm" title="Analytics">📊</a>
                <a href="{{ $p->status === 'published' ? $p->url : route('admin.posts.preview', $p) }}" target="_blank" class="btn-secondary btn-sm">Preview</a>
                <a href="{{ route("admin.$rk.edit", $p) }}" class="btn-secondary btn-sm">Edit</a>
                <div class="inline-block" x-data="{o:false}"><button @click="o=!o" class="btn-secondary btn-sm">⋯</button>
                    <div x-show="o" x-cloak @click.outside="o=false" class="absolute right-6 z-10 mt-1 w-44 rounded-lg bg-white p-1 text-left shadow-xl ring-1 ring-slate-200">
                        @permission('posts.publish')
                        @if($p->status !== 'published')<form method="post" action="{{ route("admin.$rk.status", $p) }}">@csrf<input type="hidden" name="status" value="published"><button class="block w-full rounded px-3 py-1.5 text-left text-sm hover:bg-slate-50">Publish now</button></form>@endif
                        @if($p->status === 'published')<form method="post" action="{{ route("admin.$rk.status", $p) }}">@csrf<input type="hidden" name="status" value="unpublished"><button class="block w-full rounded px-3 py-1.5 text-left text-sm hover:bg-slate-50">Unpublish</button></form>@endif
                        @endpermission
                        @permission('posts.create')<form method="post" action="{{ route("admin.$rk.duplicate", $p) }}">@csrf<button class="block w-full rounded px-3 py-1.5 text-left text-sm hover:bg-slate-50">Duplicate</button></form>@endpermission
                        @permission('posts.delete')<form method="post" action="{{ route("admin.$rk.destroy", $p) }}" data-confirm="Move this {{ $type }} to trash?">@csrf @method('DELETE')<button class="block w-full rounded px-3 py-1.5 text-left text-sm text-rose-600 hover:bg-rose-50">Trash</button></form>@endpermission
                    </div>
                </div>
            </td>
        </tr>
    @empty
        <tr><td colspan="9" class="py-10 text-center text-slate-400">No {{ strtolower($label) }} found.</td></tr>
    @endforelse
    </tbody>
</table>
</div>
<div class="mt-4">{{ $posts->links() }}</div>
@endsection
