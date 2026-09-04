@extends('layouts.admin')
@section('title', $user->exists ? 'Edit User' : 'New User')
@section('content')
<form method="post" action="{{ $user->exists ? route('admin.users.update', $user) : route('admin.users.store') }}" class="grid gap-5 lg:grid-cols-3">
    @csrf @if($user->exists) @method('PUT') @endif
    <div class="card space-y-4 lg:col-span-2">
        <div class="grid gap-4 sm:grid-cols-2">
            <div><label class="label">Name</label><input name="name" required maxlength="150" value="{{ old('name', $user->name) }}" class="input"></div>
            <div><label class="label">Email</label><input name="email" type="email" required maxlength="190" value="{{ old('email', $user->email) }}" class="input"></div>
            <div><label class="label">{{ $user->exists ? 'New password (leave blank to keep)' : 'Password' }}</label><input name="password" type="password" autocomplete="new-password" class="input" {{ $user->exists ? '' : 'required' }}><p class="help">Min 10 characters with letters and numbers.</p></div>
            <div><label class="label">Confirm password</label><input name="password_confirmation" type="password" autocomplete="new-password" class="input"></div>
            <div><label class="label">Role</label><select name="role_id" class="select" required @disabled($user->exists && $user->id === auth()->id())>@foreach($roles as $r)<option value="{{ $r->id }}" @selected(old('role_id', $user->role_id) == $r->id)>{{ $r->name }}</option>@endforeach @if($user->exists && $user->id === auth()->id())<option value="{{ $user->role_id }}" selected>{{ $user->role?->name }}</option>@endif</select></div>
            <div><label class="label">Public author slug</label><input name="slug" pattern="[a-z0-9-]+" value="{{ old('slug', $user->slug) }}" class="input" placeholder="auto"></div>
        </div>
        <div><label class="label">Bio</label><textarea name="bio" rows="4" maxlength="2000" class="textarea">{{ old('bio', $user->bio) }}</textarea></div>
        <div><label class="label">Social links</label><div class="grid gap-2 sm:grid-cols-2">@foreach(['youtube', 'instagram', 'facebook', 'twitter', 'tiktok', 'website'] as $n)<input name="social_links[{{ $n }}]" type="url" placeholder="{{ ucfirst($n) }} URL" value="{{ old("social_links.$n", $user->social_links[$n] ?? '') }}" class="input">@endforeach</div></div>
    </div>
    <div class="space-y-5">
        <div class="card"><label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" class="checkbox" @checked(old('is_active', $user->exists ? $user->is_active : true)) @disabled($user->exists && $user->id === auth()->id())> Active</label>@if($user->isLocked())<p class="mt-2 text-xs text-rose-600">Locked until {{ $user->locked_until->format('H:i') }} after failed logins.</p>@endif<button class="btn-primary mt-4 w-full">Save</button></div>
        <div class="card" x-data="mediaField('avatar')"><h2 class="card-title">Avatar</h2><input type="hidden" id="avatar" name="avatar" value="{{ old('avatar', $user->avatar) }}" data-url="{{ $user->avatar ? media_url($user->avatar) : '' }}"><div class="mt-3 flex items-center gap-3"><img :src="url || '{{ $user->avatar_url }}'" class="h-16 w-16 rounded-full object-cover ring-1 ring-slate-200" alt=""><button type="button" @click="pick()" class="btn-secondary btn-sm">Choose</button><button type="button" x-show="value" @click="clear()" class="btn-secondary btn-sm">Remove</button></div></div>
    </div>
</form>
@endsection
