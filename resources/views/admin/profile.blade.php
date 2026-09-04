@extends('layouts.admin')
@section('title', 'My Profile')
@section('content')
<form method="post" action="{{ route('admin.profile') }}" class="grid gap-5 lg:grid-cols-3">@csrf @method('PUT')
    <div class="card space-y-4 lg:col-span-2">
        <div class="grid gap-4 sm:grid-cols-2"><div><label class="label">Name</label><input name="name" required value="{{ old('name', $user->name) }}" class="input"></div><div><label class="label">Email</label><input name="email" type="email" required value="{{ old('email', $user->email) }}" class="input"></div></div>
        <div><label class="label">Bio</label><textarea name="bio" rows="4" class="textarea">{{ old('bio', $user->bio) }}</textarea></div>
        <div><label class="label">Social links</label><div class="grid gap-2 sm:grid-cols-2">@foreach(['youtube', 'instagram', 'facebook', 'twitter', 'tiktok', 'website'] as $n)<input name="social_links[{{ $n }}]" type="url" placeholder="{{ ucfirst($n) }} URL" value="{{ old("social_links.$n", $user->social_links[$n] ?? '') }}" class="input">@endforeach</div></div>
        <h2 class="card-title pt-2">Change password</h2>
        <div class="grid gap-4 sm:grid-cols-3"><div><label class="label">Current password</label><input name="current_password" type="password" class="input"></div><div><label class="label">New password</label><input name="password" type="password" class="input"></div><div><label class="label">Confirm</label><input name="password_confirmation" type="password" class="input"></div></div>
        <button class="btn-primary">Save profile</button>
    </div>
    <div class="card" x-data="mediaField('avatar')"><h2 class="card-title">Avatar</h2><input type="hidden" id="avatar" name="avatar" value="{{ old('avatar', $user->avatar) }}" data-url="{{ $user->avatar ? media_url($user->avatar) : '' }}"><div class="mt-3 flex items-center gap-3"><img :src="url || '{{ $user->avatar_url }}'" class="h-16 w-16 rounded-full object-cover ring-1 ring-slate-200" alt=""><button type="button" @click="pick()" class="btn-secondary btn-sm">Choose</button><button type="button" x-show="value" @click="clear()" class="btn-secondary btn-sm">Remove</button></div><p class="help mt-3">Role: {{ $user->role?->name }} · Last login {{ $user->last_login_at?->diffForHumans() }}</p></div>
</form>
@endsection
