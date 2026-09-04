@extends('layouts.admin')
@section('title', $category->exists ? 'Edit Category' : 'New Category')
@section('content')
<form method="post" action="{{ $category->exists ? route('admin.categories.update', $category) : route('admin.categories.store') }}" class="grid gap-5 lg:grid-cols-3">
    @csrf @if($category->exists) @method('PUT') @endif
    <div class="card space-y-4 lg:col-span-2" x-data="slugger('{{ old('slug', $category->slug) }}')">
        <div><label class="label">Name</label><input name="name" required maxlength="150" value="{{ old('name', $category->name) }}" @input="from($event.target.value)" class="input"></div>
        <div><label class="label">Slug</label><input name="slug" x-model="slug" @input="locked=true" pattern="[a-z0-9-]+" class="input" placeholder="auto"></div>
        <div><label class="label">Parent category</label><select name="parent_id" class="select"><option value="">— Top level —</option>@foreach($parents as $p)<option value="{{ $p->id }}" @selected(old('parent_id', $category->parent_id) == $p->id)>{{ $p->name }}</option>@endforeach</select></div>
        <div><label class="label">Description</label><textarea name="description" rows="3" maxlength="2000" class="textarea">{{ old('description', $category->description) }}</textarea></div>
        <div class="grid gap-4 sm:grid-cols-2"><div><label class="label">SEO title</label><input name="seo_title" maxlength="255" value="{{ old('seo_title', $category->seo_title) }}" class="input"></div><div><label class="label">Sort order</label><input type="number" name="sort_order" min="0" value="{{ old('sort_order', $category->sort_order) }}" class="input"></div></div>
        <div><label class="label">Meta description</label><textarea name="meta_description" rows="2" maxlength="500" class="textarea min-h-[60px]">{{ old('meta_description', $category->meta_description) }}</textarea></div>
    </div>
    <div class="space-y-5">
        <div class="card"><label class="label">Status</label><select name="status" class="select"><option value="active" @selected(old('status', $category->status) === 'active')>Active</option><option value="inactive" @selected(old('status', $category->status) === 'inactive')>Inactive</option></select><button class="btn-primary mt-4 w-full">Save</button></div>
        <div class="card" x-data="mediaField('image')"><h2 class="card-title">Image</h2><input type="hidden" id="image" name="image" value="{{ old('image', $category->image) }}" data-url="{{ $category->image_url }}"><div class="mt-3 aspect-video overflow-hidden rounded-lg bg-slate-100"><img x-show="url" :src="url" class="h-full w-full object-cover" alt=""></div><div class="mt-2 flex gap-2"><button type="button" @click="pick()" class="btn-secondary btn-sm">Choose</button><button type="button" x-show="value" @click="clear()" class="btn-secondary btn-sm">Remove</button></div></div>
    </div>
</form>
@endsection
