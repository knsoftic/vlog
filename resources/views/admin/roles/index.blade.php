@extends('layouts.admin')
@section('title', 'Roles')
@section('actions')<a href="{{ route('admin.permissions') }}" class="btn-secondary">Permission matrix</a>@endsection
@section('content')
<div class="grid gap-5 lg:grid-cols-3">
    <div class="card lg:order-2">
        <h2 class="card-title">Create role</h2>
        <form method="post" action="{{ route('admin.roles.store') }}" class="mt-3 space-y-3">@csrf
            <div><label class="label">Name</label><input name="name" required maxlength="100" class="input"></div>
            <div><label class="label">Description</label><input name="description" maxlength="255" class="input"></div>
            <div><label class="label">Level (1–90)</label><input type="number" name="level" min="1" max="90" value="30" class="input"><p class="help">Users can only manage roles below their own level.</p></div>
            <button class="btn-primary w-full">Create</button>
        </form>
    </div>
    <div class="table-wrap lg:col-span-2"><table class="table">
        <thead><tr><th>Role</th><th>Level</th><th class="text-right">Users</th><th class="text-right">Permissions</th><th class="text-right">Actions</th></tr></thead>
        <tbody>
        @foreach($roles as $r)
            <tr x-data="{edit:false}">
                <td>
                    <div x-show="!edit"><p class="font-semibold">{{ $r->name }} @if($r->is_system)<span class="badge-gray">system</span>@endif</p><p class="text-xs text-slate-500">{{ $r->description }}</p></div>
                    <form x-show="edit" x-cloak method="post" action="{{ route('admin.roles.update', $r) }}" class="flex flex-wrap gap-2">@csrf @method('PUT')<input name="name" value="{{ $r->name }}" class="input w-36 py-1"><input name="description" value="{{ $r->description }}" class="input w-48 py-1" placeholder="Description"><input type="number" name="level" value="{{ $r->level }}" min="1" max="90" class="input w-20 py-1" @disabled($r->is_system)><button class="btn-primary btn-sm">Save</button></form>
                </td>
                <td>{{ $r->level }}</td>
                <td class="text-right">{{ $r->users_count }}</td>
                <td class="text-right">{{ $r->slug === 'super_admin' ? 'All' : $r->permissions->count() }}</td>
                <td class="text-right whitespace-nowrap"><button type="button" @click="edit=!edit" class="btn-secondary btn-sm">Edit</button> @unless($r->is_system)<form method="post" action="{{ route('admin.roles.destroy', $r) }}" class="inline" data-confirm="Delete this role?">@csrf @method('DELETE')<button class="btn-danger btn-sm">Delete</button></form>@endunless</td>
            </tr>
        @endforeach
        </tbody></table></div>
</div>
@endsection
