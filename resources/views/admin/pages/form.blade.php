@extends('layouts.admin')
@section('title', $page->exists ? 'Edit Page' : 'New Page')
@section('actions')<button form="page-form" class="btn-primary">Save</button>@endsection
@section('content')
<form id="page-form" method="post" action="{{ $page->exists ? route('admin.pages.update', $page) : route('admin.pages.store') }}" class="grid gap-5 lg:grid-cols-3">
    @csrf @if($page->exists) @method('PUT') @endif
    <div class="space-y-5 lg:col-span-2">
        <div class="card" x-data="slugger('{{ old('slug', $page->slug) }}')">
            <label class="label">Title</label><input name="title" required maxlength="255" value="{{ old('title', $page->title) }}" @input="from($event.target.value)" class="input text-lg font-semibold">
            <div class="mt-3 flex items-center gap-2 text-xs text-slate-500"><span>{{ url('/page') }}/</span><input name="slug" x-model="slug" @input="locked=true" pattern="[a-z0-9-]+" class="input w-72 py-1 text-xs" @disabled($page->is_system)>@if($page->is_system)<span class="badge-gray">fixed for legal pages</span>@endif</div>
        </div>
        <div class="card"><h2 class="card-title">Content</h2><input type="hidden" name="content" id="page-content" value="{{ old('content', $page->content) }}"><div data-editor="#page-content" class="mt-3 bg-white"></div></div>
        <div class="card grid gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2"><h2 class="card-title">SEO</h2></div>
            <div><label class="label">Meta title</label><input name="meta_title" maxlength="255" value="{{ old('meta_title', $page->meta_title) }}" class="input"></div>
            <div><label class="label">Canonical URL</label><input name="canonical_url" type="url" value="{{ old('canonical_url', $page->canonical_url) }}" class="input"></div>
            <div class="sm:col-span-2"><label class="label">Meta description</label><textarea name="meta_description" maxlength="500" rows="2" class="textarea min-h-[60px]">{{ old('meta_description', $page->meta_description) }}</textarea></div>
            <div><label class="label">Robots</label><select name="robots" class="select"><option value="">Automatic</option>@foreach(['index, follow', 'noindex, follow', 'noindex, nofollow'] as $r)<option @selected(old('robots', $page->robots) === $r)>{{ $r }}</option>@endforeach</select></div>
            <div><label class="label">OG title</label><input name="og_title" maxlength="255" value="{{ old('og_title', $page->og_title) }}" class="input"></div>
            <div class="sm:col-span-2"><label class="label">OG description</label><textarea name="og_description" maxlength="500" rows="2" class="textarea min-h-[60px]">{{ old('og_description', $page->og_description) }}</textarea></div>
            <div class="sm:col-span-2" x-data="mediaField('og_image')"><label class="label">OG image</label><input type="hidden" id="og_image" name="og_image" value="{{ old('og_image', $page->og_image) }}" data-url="{{ media_url($page->og_image) }}"><div class="flex items-center gap-3"><img x-show="url" :src="url" class="h-14 w-24 rounded object-cover ring-1 ring-slate-200"><button type="button" @click="pick()" class="btn-secondary btn-sm">Choose</button><button type="button" x-show="value" @click="clear()" class="btn-secondary btn-sm">Remove</button></div></div>
        </div>
    </div>
    <div class="card space-y-4">
        <div><label class="label">Status</label><select name="status" class="select"><option value="published" @selected(old('status', $page->status) === 'published')>Published</option><option value="draft" @selected(old('status', $page->status) === 'draft')>Draft</option></select></div>
        <div><label class="label">Template</label><select name="template" class="select"><option value="default" @selected(old('template', $page->template) === 'default')>Default</option><option value="contact" @selected(old('template', $page->template) === 'contact')>Contact (with form)</option></select></div>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="show_in_footer" value="1" class="checkbox" @checked(old('show_in_footer', $page->show_in_footer))> Show in footer</label>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="show_in_header" value="1" class="checkbox" @checked(old('show_in_header', $page->show_in_header))> Show in header (via menu)</label>
        <div><label class="label">Sort order</label><input type="number" name="sort_order" min="0" value="{{ old('sort_order', $page->sort_order) }}" class="input"></div>
        <button class="btn-primary w-full">Save page</button>
        @if($page->exists)<a href="{{ $page->url }}" target="_blank" class="btn-secondary w-full">View page</a>@endif
    </div>
</form>
@endsection
