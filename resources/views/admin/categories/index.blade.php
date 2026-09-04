@extends('layouts.admin')
@section('title', 'Categories')
@section('actions')<a href="{{ route('admin.categories.create') }}" class="btn-primary">+ New Category</a>@endsection
@section('content')
<div class="table-wrap"><table class="table">
    <thead><tr><th class="w-12"></th><th>Name</th><th>Slug</th><th>Status</th><th class="text-right">Posts</th><th class="text-right">Actions</th></tr></thead>
    <tbody>
    @forelse($categories as $c)
        <tr>
            <td><div class="h-9 w-9 overflow-hidden rounded bg-slate-100">@if($c->image_url)<img src="{{ $c->image_url }}" class="h-full w-full object-cover" alt="">@endif</div></td>
            <td><a href="{{ route('admin.categories.edit', $c) }}" class="font-semibold text-slate-900 hover:text-indigo-700">{{ $c->name }}</a>@if($c->description)<p class="line-clamp-1 text-xs text-slate-400">{{ $c->description }}</p>@endif</td>
            <td class="text-xs text-slate-500">/category/{{ $c->slug }}</td>
            <td><span class="{{ $c->status === 'active' ? 'badge-green' : 'badge-gray' }}">{{ ucfirst($c->status) }}</span></td>
            <td class="text-right">{{ $c->posts_count }}</td>
            <td class="text-right whitespace-nowrap"><a href="{{ $c->url }}" target="_blank" class="btn-secondary btn-sm">View</a> <a href="{{ route('admin.categories.edit', $c) }}" class="btn-secondary btn-sm">Edit</a> <form method="post" action="{{ route('admin.categories.destroy', $c) }}" class="inline" data-confirm="Delete this category? Posts will be kept.">@csrf @method('DELETE')<button class="btn-danger btn-sm">Delete</button></form></td>
        </tr>
        @foreach($c->children as $s)
            <tr class="bg-slate-50/50">
                <td></td>
                <td class="pl-8"><span class="text-slate-400">└</span> <a href="{{ route('admin.categories.edit', $s) }}" class="font-medium text-slate-800 hover:text-indigo-700">{{ $s->name }}</a></td>
                <td class="text-xs text-slate-500">/category/{{ $s->slug }}</td>
                <td><span class="{{ $s->status === 'active' ? 'badge-green' : 'badge-gray' }}">{{ ucfirst($s->status) }}</span></td>
                <td class="text-right">{{ $s->posts()->count() }}</td>
                <td class="text-right whitespace-nowrap"><a href="{{ route('admin.categories.edit', $s) }}" class="btn-secondary btn-sm">Edit</a> <form method="post" action="{{ route('admin.categories.destroy', $s) }}" class="inline" data-confirm="Delete this subcategory?">@csrf @method('DELETE')<button class="btn-danger btn-sm">Delete</button></form></td>
            </tr>
        @endforeach
    @empty<tr><td colspan="6" class="py-10 text-center text-slate-400">No categories yet.</td></tr>@endforelse
    </tbody></table></div>
@endsection
