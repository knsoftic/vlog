@extends('layouts.admin')
@section('title', 'Permissions')
@section('actions')<button form="perm-form" class="btn-primary">Save matrix</button>@endsection
@section('content')
<form id="perm-form" method="post" action="{{ route('admin.permissions.update') }}">@csrf @method('PUT')
<div class="table-wrap"><table class="table">
    <thead><tr><th>Permission</th>@foreach($roles as $r)<th class="text-center">{{ $r->name }}</th>@endforeach</tr></thead>
    <tbody>
    @foreach($permissions as $group => $list)
        <tr class="bg-slate-50"><td colspan="{{ $roles->count() + 1 }}" class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">{{ $group }}</td></tr>
        @foreach($list as $p)
            <tr><td><p class="font-medium">{{ $p->name }}</p><p class="text-xs text-slate-400">{{ $p->slug }}</p></td>
                @foreach($roles as $r)
                    <td class="text-center">@if($r->slug === 'super_admin')<span class="text-emerald-500">✔</span>@else<input type="checkbox" name="perm[{{ $r->id }}][{{ $p->id }}]" value="1" class="checkbox" @checked($r->permissions->contains('id', $p->id))>@endif</td>
                @endforeach
            </tr>
        @endforeach
    @endforeach
    </tbody></table></div>
<p class="help mt-3">Super Admin implicitly has every permission. Changes are recorded in the admin log.</p>
</form>
@endsection
