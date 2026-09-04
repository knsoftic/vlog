@extends('layouts.admin')
@section('title', 'Media Library')
@section('content')
<div class="mb-4 grid gap-4 lg:grid-cols-3">
    <div class="lg:col-span-2" x-data="dropzone">
        <label class="dropzone" :class="over && 'dragover'" @dragover.prevent="over=true" @dragleave="over=false" @drop.prevent="over=false; files($event.dataTransfer.files)">
            <input type="file" multiple class="hidden" @change="files($event.target.files)">
            <svg class="mb-2 h-8 w-8 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M12 16V4m0 0-4 4m4-4 4 4M4 16v3a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-3"/></svg>
            <span x-show="!uploading">Drop files here or click to upload — images (JPG/PNG/WebP/AVIF ≤ {{ \App\Services\MediaService::MAX_IMAGE_MB }} MB) and videos (MP4/WebM ≤ {{ \App\Services\MediaService::MAX_VIDEO_MB }} MB)</span>
            <span x-show="uploading" x-cloak x-text="'Uploading ' + done + ' / ' + total + '…'"></span>
        </label>
        <template x-for="e in errors"><p class="mt-2 text-xs text-rose-600" x-text="e"></p></template>
    </div>
    <div class="card text-sm">
        <h2 class="card-title">Storage</h2>
        <p class="mt-2">{{ number_format($usage['files']) }} files · {{ round($usage['media_bytes'] / 1048576, 1) }} MB used</p>
        @if($usage['disk_total'])<div class="progress mt-2"><div style="width: {{ round((1 - $usage['disk_free'] / $usage['disk_total']) * 100) }}%"></div></div><p class="help">{{ round($usage['disk_free'] / 1073741824, 1) }} GB free of {{ round($usage['disk_total'] / 1073741824, 1) }} GB</p>@endif
        <p class="help">Uploads are MIME-sniffed, re-encoded and get WebP + responsive variants automatically.</p>
    </div>
</div>
<form method="get" class="mb-3 flex gap-2"><input type="search" name="s" value="{{ request('s') }}" placeholder="Search…" class="input max-w-xs"><select name="type" class="select w-32" onchange="this.form.submit()"><option value="">All</option><option value="image" @selected(request('type')==='image')>Images</option><option value="video" @selected(request('type')==='video')>Videos</option><option value="file" @selected(request('type')==='file')>Files</option></select></form>
<div class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-6">
    @forelse($items as $m)
        <div class="card p-2" x-data="{ alt: @js($m->alt), edit: false }">
            <div class="aspect-square overflow-hidden rounded-lg bg-slate-100">@if($m->type === 'image')<img src="{{ $m->thumb_url }}" alt="{{ $m->alt }}" class="h-full w-full object-cover" loading="lazy">@else<div class="flex h-full items-center justify-center text-center text-xs text-slate-500 p-2">{{ strtoupper($m->extension) }}<br>{{ $m->humanSize() }}</div>@endif</div>
            <p class="mt-2 truncate text-xs font-medium" title="{{ $m->original_name }}">{{ $m->original_name }}</p>
            <p class="text-[11px] text-slate-400">{{ $m->width ? $m->width.'×'.$m->height.' · ' : '' }}{{ $m->humanSize() }}</p>
            <div class="mt-1 flex gap-1">
                <button type="button" @click="navigator.clipboard.writeText('{{ $m->url }}')" class="btn-secondary btn-sm flex-1">Copy URL</button>
                <form method="post" action="{{ route('admin.media.destroy', $m) }}" data-confirm="Delete this file?">@csrf @method('DELETE')<button class="btn-danger btn-sm">✕</button></form>
            </div>
        </div>
    @empty<p class="col-span-full py-10 text-center text-slate-400">No media yet.</p>@endforelse
</div>
<div class="mt-4">{{ $items->links() }}</div>
@endsection
