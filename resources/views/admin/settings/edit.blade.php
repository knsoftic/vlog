@extends('layouts.admin')
@section('title', 'Settings · '.$tabs[$tab]['title'])
@section('actions')<form method="post" action="{{ route('admin.settings.cache-clear') }}">@csrf<button class="btn-secondary">Clear cache</button></form>@endsection
@section('content')
<div class="mb-4 flex flex-wrap gap-1 rounded-lg bg-slate-200/60 p-1">
    @foreach($tabs as $k => $t)<a href="{{ route('admin.settings.edit', $k) }}" class="tab {{ $tab === $k ? 'active' : '' }}">{{ $t['title'] }}</a>@endforeach
    <a href="{{ route('admin.backups.index') }}" class="tab">Backups</a>
</div>

@if($tab === 'google')
    <div class="card mb-5">
        <h2 class="card-title">Connections</h2>
        <p class="help">Create an OAuth 2.0 "Web application" client in Google Cloud Console, enable the <b>AdSense Management API</b> and <b>Google Search Console API</b>, and add this redirect URI: <code class="select-all">{{ $redirectUri }}</code></p>
        <div class="mt-3 grid gap-3 sm:grid-cols-2">
            @foreach(['adsense' => 'AdSense Management API', 'search_console' => 'Google Search Console'] as $svc => $label)
                @php $t = $tokens[$svc] ?? null; @endphp
                <div class="rounded-lg bg-slate-50 p-3 text-sm ring-1 ring-slate-200">
                    <p class="font-semibold">{{ $label }} @if($t?->isConnected())<span class="badge-green">connected</span>@else<span class="badge-gray">not connected</span>@endif</p>
                    @if($t?->isConnected())<p class="text-xs text-slate-500">{{ $t->account_label ?: $t->account_id }} · last sync {{ $t->last_synced_at?->diffForHumans() ?? 'never' }} @if($t->last_status === 'failed')· <span class="text-rose-600">{{ \Illuminate\Support\Str::limit($t->last_error, 80) }}</span>@endif</p>
                        <div class="mt-2 flex gap-2"><form method="post" action="{{ route('admin.google.sync', $svc) }}">@csrf<button class="btn-secondary btn-sm">Sync now</button></form><form method="post" action="{{ route('admin.google.disconnect', $svc) }}" data-confirm="Disconnect?">@csrf<button class="btn-secondary btn-sm">Disconnect</button></form></div>
                    @else<a href="{{ route('admin.google.connect', $svc) }}" class="btn-primary btn-sm mt-2 {{ $configured ? '' : 'pointer-events-none opacity-50' }}">Connect with Google</a>@unless($configured)<p class="help">Save Client ID & Secret first.</p>@endunless @endif
                </div>
            @endforeach
        </div>
    </div>
@endif

<form method="post" action="{{ route('admin.settings.update', $tab) }}" class="card max-w-3xl space-y-4">@csrf @method('PUT')
    @foreach($fields as $key => $def)
        @php [$label, $type] = $def; $help = $def[2] ?? null; $opts = $def[3] ?? []; $name = str_replace('.', '__', $key); $val = old($name, $values[$key]); @endphp
        <div>
            @if($type === 'bool')
                <label class="flex items-center gap-2 text-sm font-medium"><input type="checkbox" name="{{ $name }}" value="1" class="checkbox" @checked(filter_var($val, FILTER_VALIDATE_BOOLEAN))> {{ $label }}</label>
            @elseif($type === 'textarea')
                <label class="label">{{ $label }}</label><textarea name="{{ $name }}" rows="3" class="textarea">{{ $val }}</textarea>
            @elseif($type === 'select')
                <label class="label">{{ $label }}</label><select name="{{ $name }}" class="select">@foreach($opts as $ov => $ol)<option value="{{ $ov }}" @selected((string) $val === (string) $ov)>{{ $ol }}</option>@endforeach</select>
            @elseif($type === 'image')
                <label class="label">{{ $label }}</label>
                <div x-data="mediaField('{{ $name }}')"><input type="hidden" id="{{ $name }}" name="{{ $name }}" value="{{ $val }}" data-url="{{ media_url($val) }}"><div class="flex items-center gap-3"><img x-show="url" :src="url" class="h-12 max-w-40 rounded object-contain ring-1 ring-slate-200 bg-white p-1"><button type="button" @click="pick()" class="btn-secondary btn-sm">Choose</button><button type="button" x-show="value" @click="clear()" class="btn-secondary btn-sm">Remove</button></div></div>
            @elseif($type === 'color')
                <label class="label">{{ $label }}</label><div class="flex items-center gap-2"><input type="color" name="{{ $name }}" value="{{ $val ?: '#000000' }}" class="h-9 w-14 rounded border border-slate-300"><span class="text-xs text-slate-500">{{ $val }}</span></div>
            @elseif($type === 'password')
                <label class="label">{{ $label }} @if($hasSecret ?? false)<span class="badge-green">set</span>@endif</label><input type="password" name="{{ $name }}" autocomplete="new-password" class="input" placeholder="••••••••">
            @else
                <label class="label">{{ $label }}</label><input type="{{ $type }}" name="{{ $name }}" value="{{ $val }}" class="input">
            @endif
            @if($help)<p class="help">{{ $help }}</p>@endif
            @error($name)<p class="error">{{ $message }}</p>@enderror
        </div>
    @endforeach
    <button class="btn-primary">Save {{ $tabs[$tab]['title'] }}</button>
</form>
@endsection
