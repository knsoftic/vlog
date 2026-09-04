@extends('layouts.admin')
@php $rk = $type === 'article' ? 'articles' : 'vlogs'; @endphp
@section('title', 'Trash · '.ucfirst($rk))
@section('actions')<a href="{{ route("admin.$rk.index") }}" class="btn-secondary">← Back</a>@endsection
@section('content')
<div class="table-wrap"><table class="table">
    <thead><tr><th>Title</th><th>Author</th><th>Deleted</th><th class="text-right">Actions</th></tr></thead>
    <tbody>
    @forelse($posts as $p)
        <tr><td class="font-medium">{{ $p->title }}</td><td class="text-slate-500">{{ $p->author?->name }}</td><td class="text-xs text-slate-500">{{ $p->deleted_at->diffForHumans() }}</td>
            <td class="text-right"><form method="post" action="{{ route("admin.$rk.restore", $p->id) }}" class="inline">@csrf<button class="btn-secondary btn-sm">Restore</button></form> <form method="post" action="{{ route("admin.$rk.force", $p->id) }}" class="inline" data-confirm="Permanently delete? This cannot be undone.">@csrf @method('DELETE')<button class="btn-danger btn-sm">Delete forever</button></form></td></tr>
    @empty<tr><td colspan="4" class="py-10 text-center text-slate-400">Trash is empty.</td></tr>@endforelse
    </tbody></table></div>
<div class="mt-4">{{ $posts->links() }}</div>
@endsection
