@extends('layouts.admin')
@section('title', 'Tags')
@section('content')
<div class="grid gap-5 lg:grid-cols-3">
    <div class="card lg:order-2">
        <h2 class="card-title">Add tag</h2>
        <form method="post" action="{{ route('admin.tags.store') }}" class="mt-3 space-y-3">@csrf
            <div><label class="label">Name</label><input name="name" required maxlength="100" class="input"></div>
            <div><label class="label">Slug (optional)</label><input name="slug" pattern="[a-z0-9-]+" class="input"></div>
            <div><label class="label">Description</label><textarea name="description" maxlength="500" rows="2" class="textarea min-h-[60px]"></textarea></div>
            <button class="btn-primary w-full">Add</button>
        </form>
    </div>
    <div class="lg:col-span-2">
        <form method="get" class="mb-3"><input type="search" name="s" value="{{ request('s') }}" placeholder="Search tags…" class="input max-w-xs"></form>
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Tag</th><th>Slug</th><th class="text-right">Posts</th><th class="text-right">Actions</th></tr></thead>
            <tbody>
            @forelse($tags as $t)
                <tr x-data="{edit:false}">
                    <td>
                        <span x-show="!edit" class="font-medium">{{ $t->name }}</span>
                        <form x-show="edit" x-cloak method="post" action="{{ route('admin.tags.update', $t) }}" class="flex gap-2">@csrf @method('PUT')<input name="name" value="{{ $t->name }}" class="input py-1"><input name="slug" value="{{ $t->slug }}" class="input py-1"><button class="btn-primary btn-sm">Save</button></form>
                    </td>
                    <td class="text-xs text-slate-500">/tag/{{ $t->slug }}</td>
                    <td class="text-right">{{ $t->posts_count }}</td>
                    <td class="text-right whitespace-nowrap"><a href="{{ $t->url }}" target="_blank" class="btn-secondary btn-sm">View</a> <button type="button" @click="edit=!edit" class="btn-secondary btn-sm">Edit</button> <form method="post" action="{{ route('admin.tags.destroy', $t) }}" class="inline" data-confirm="Delete this tag?">@csrf @method('DELETE')<button class="btn-danger btn-sm">Delete</button></form></td>
                </tr>
            @empty<tr><td colspan="4" class="py-10 text-center text-slate-400">No tags yet.</td></tr>@endforelse
            </tbody></table></div>
        <div class="mt-4">{{ $tags->links() }}</div>
    </div>
</div>
@endsection
