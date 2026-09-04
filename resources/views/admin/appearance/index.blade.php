@extends('layouts.admin')
@section('title', 'Home Sections & Menus')
@section('content')
<div class="grid gap-5 lg:grid-cols-3">
    <form method="post" action="{{ route('admin.appearance.sections') }}" class="lg:col-span-2" x-data="sortable()">@csrf @method('PUT')
        <div class="card">
            <div class="flex items-center justify-between"><h2 class="card-title">Home page sections</h2><button class="btn-primary btn-sm">Save sections</button></div>
            <p class="help">Drag to reorder. Each section can be enabled/disabled and limited; "Latest" and "Popular" can be pinned to a category.</p>
            <div x-ref="list" class="mt-4 space-y-2">
                @foreach($sections as $s)
                    <div draggable="true" data-id="{{ $s->id }}" @dragstart="start($event, {{ $loop->index }})" @dragover="over($event)" @drop="drop($event, {{ $loop->index }})" class="flex flex-wrap items-center gap-3 rounded-lg bg-slate-50 p-3 ring-1 ring-slate-200">
                        <span class="cursor-grab text-slate-300">⋮⋮</span>
                        <input type="hidden" name="sections[{{ $s->id }}][id]" value="{{ $s->id }}">
                        <label class="flex items-center gap-1.5 text-xs"><input type="checkbox" name="sections[{{ $s->id }}][enabled]" value="1" class="checkbox" @checked($s->enabled)> On</label>
                        <span class="w-24 text-xs font-semibold uppercase text-slate-400">{{ $s->key }}</span>
                        <input name="sections[{{ $s->id }}][title]" value="{{ $s->title }}" class="input w-44 py-1" placeholder="Title">
                        <input name="sections[{{ $s->id }}][subtitle]" value="{{ $s->subtitle }}" class="input min-w-40 flex-1 py-1" placeholder="Subtitle">
                        @if(!in_array($s->key, ['newsletter'], true))<input type="number" name="sections[{{ $s->id }}][limit]" value="{{ $s->setting('limit', 6) }}" min="1" max="24" class="input w-16 py-1" title="Items">@endif
                        @if(in_array($s->key, ['latest', 'popular'], true))<select name="sections[{{ $s->id }}][category_id]" class="select w-40 py-1"><option value="">Any category</option>@foreach($categories as $c)<option value="{{ $c->id }}" @selected($s->setting('category_id') == $c->id)>{{ $c->name }}</option>@endforeach</select>@endif
                    </div>
                @endforeach
            </div>
        </div>
    </form>
    <div class="space-y-5">
        @foreach([['header', 'Header menu', $header], ['footer', 'Footer menu', $footer]] as [$loc, $title, $items])
            <div class="card">
                <h2 class="card-title">{{ $title }}</h2>
                <form method="post" action="{{ route('admin.appearance.menu.update') }}" class="mt-3 space-y-2">@csrf @method('PUT')
                    @foreach($items as $it)
                        <div class="flex items-center gap-1.5"><input type="hidden" name="items[{{ $it->id }}][id]" value="{{ $it->id }}"><input type="checkbox" name="items[{{ $it->id }}][is_active]" value="1" class="checkbox" @checked($it->is_active)><input name="items[{{ $it->id }}][label]" value="{{ $it->label }}" class="input w-28 py-1"><input name="items[{{ $it->id }}][url]" value="{{ $it->url }}" class="input flex-1 py-1"><button form="del-{{ $it->id }}" class="text-rose-500" title="Remove">✕</button></div>
                    @endforeach
                    @if($items->count())<button class="btn-secondary btn-sm">Save {{ strtolower($title) }}</button>@endif
                </form>
                @foreach($items as $it)<form id="del-{{ $it->id }}" method="post" action="{{ route('admin.appearance.menu.destroy', $it) }}">@csrf @method('DELETE')</form>@endforeach
                <form method="post" action="{{ route('admin.appearance.menu.store') }}" class="mt-3 flex gap-1.5 border-t border-slate-100 pt-3">@csrf<input type="hidden" name="location" value="{{ $loc }}"><input name="label" placeholder="Label" required class="input w-28 py-1"><input name="url" placeholder="/path or https://" required class="input flex-1 py-1"><button class="btn-primary btn-sm">Add</button></form>
            </div>
        @endforeach
    </div>
</div>
@endsection
