@extends('layouts.admin')
@section('title', 'Redirects')
@section('content')
<div class="grid gap-5 lg:grid-cols-3">
    <div class="space-y-5">
        <div class="card"><h2 class="card-title">Add redirect</h2>
            <form method="post" action="{{ route('admin.seo.redirects.store') }}" class="mt-3 space-y-3">@csrf
                <div><label class="label">From path</label><input name="from_path" required value="{{ old('from_path', request('from')) }}" placeholder="/old-url" class="input"></div>
                <div><label class="label">To URL / path</label><input name="to_path" required placeholder="/vlog/new-slug or https://…" class="input"></div>
                <div><label class="label">Type</label><select name="status_code" class="select"><option value="301">301 Permanent</option><option value="302">302 Temporary</option><option value="308">308 Permanent (keep method)</option><option value="307">307 Temporary (keep method)</option></select></div>
                <button class="btn-primary w-full">Save redirect</button>
            </form></div>
        <div class="card"><h2 class="card-title">Recent 404s (30 days)</h2><ul class="mt-3 space-y-1 text-xs">@forelse($top404 as $e)<li class="flex items-center justify-between gap-2"><span class="truncate" title="{{ $e->url }}">{{ parse_url($e->url, PHP_URL_PATH) }}</span><span class="shrink-0"><b>{{ $e->occurrences }}</b> <a href="{{ route('admin.seo.redirects', ['from' => parse_url($e->url, PHP_URL_PATH)]) }}" class="text-indigo-600">redirect</a></span></li>@empty<li class="text-slate-400">No 404s logged.</li>@endforelse</ul></div>
    </div>
    <div class="lg:col-span-2">
        <form method="get" class="mb-3"><input type="search" name="s" value="{{ request('s') }}" placeholder="Search…" class="input max-w-xs"></form>
        <div class="table-wrap"><table class="table"><thead><tr><th>From</th><th>To</th><th>Type</th><th class="text-right">Hits</th><th>Active</th><th class="text-right">Actions</th></tr></thead><tbody>
        @forelse($redirects as $r)
            <tr x-data="{edit:false}">
                <td colspan="5" x-show="edit" x-cloak><form method="post" action="{{ route('admin.seo.redirects.update', $r) }}" class="flex flex-wrap gap-2">@csrf @method('PUT')<input name="from_path" value="{{ $r->from_path }}" class="input w-44 py-1"><input name="to_path" value="{{ $r->to_path }}" class="input w-56 py-1"><select name="status_code" class="select w-24 py-1">@foreach([301, 302, 307, 308] as $c)<option @selected($r->status_code == $c)>{{ $c }}</option>@endforeach</select><label class="flex items-center gap-1 text-xs"><input type="checkbox" name="is_active" value="1" class="checkbox" @checked($r->is_active)> Active</label><button class="btn-primary btn-sm">Save</button></form></td>
                <td x-show="!edit" class="font-mono text-xs">{{ $r->from_path }}</td><td x-show="!edit" class="max-w-xs truncate font-mono text-xs">{{ $r->to_path }}</td><td x-show="!edit">{{ $r->status_code }}</td><td x-show="!edit" class="text-right">{{ $r->hits }}</td><td x-show="!edit">{{ $r->is_active ? '✔' : '—' }}</td>
                <td class="text-right whitespace-nowrap"><button type="button" @click="edit=!edit" class="btn-secondary btn-sm">Edit</button> <form method="post" action="{{ route('admin.seo.redirects.destroy', $r) }}" class="inline" data-confirm="Delete redirect?">@csrf @method('DELETE')<button class="btn-danger btn-sm">Delete</button></form></td>
            </tr>
        @empty<tr><td colspan="6" class="py-10 text-center text-slate-400">No redirects yet.</td></tr>@endforelse
        </tbody></table></div>
        <div class="mt-4">{{ $redirects->links() }}</div>
    </div>
</div>
@endsection
