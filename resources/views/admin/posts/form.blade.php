@extends('layouts.admin')
@php $rk = $type === 'article' ? 'articles' : 'vlogs'; $canPublish = auth()->user()->hasPermission('posts.publish'); @endphp
@section('title', ($post->exists ? 'Edit ' : 'New ').ucfirst($type))
@section('actions')
    @if($post->exists)<a href="{{ route('admin.posts.preview', $post) }}" target="_blank" class="btn-secondary">Preview</a><a href="{{ route('admin.posts.analytics', $post) }}" class="btn-secondary">Analytics</a>@endif
    <button form="post-form" class="btn-primary">Save</button>
@endsection

@section('content')
<form id="post-form" method="post" action="{{ $post->exists ? route("admin.$rk.update", $post) : route("admin.$rk.store") }}" class="grid gap-5 xl:grid-cols-3" x-data="{ video: '{{ old('video_type', $post->video_type) }}', status: '{{ old('status', $post->status) }}', tab: 'content' }">
    @csrf @if($post->exists) @method('PUT') @endif
    <div class="space-y-5 xl:col-span-2">
        <div class="card" x-data="slugger('{{ old('slug', $post->slug) }}')">
            <label class="label" for="title">Title</label>
            <input id="title" name="title" required maxlength="255" value="{{ old('title', $post->title) }}" @input="from($event.target.value)" class="input text-lg font-semibold" placeholder="An honest, descriptive title">
            <div class="mt-3 flex items-center gap-2 text-xs text-slate-500"><span>{{ url('/'.$type) }}/</span><input name="slug" x-model="slug" @input="locked = true" pattern="[a-z0-9-]+" class="input w-72 py-1 text-xs" placeholder="auto-generated"></div>
            <div class="mt-3"><label class="label" for="excerpt">Short description</label><textarea id="excerpt" name="excerpt" rows="3" maxlength="2000" class="textarea min-h-[70px]" placeholder="One or two sentences shown in listings and search results.">{{ old('excerpt', $post->excerpt) }}</textarea></div>
        </div>

        <div class="card">
            <h2 class="card-title">Video</h2>
            <div class="mt-3 grid gap-3 sm:grid-cols-5">
                @foreach(['none' => 'No video', 'youtube' => 'YouTube', 'vimeo' => 'Vimeo', 'self_hosted' => 'Self-hosted', 'external' => 'External embed'] as $k => $l)
                    <label class="flex cursor-pointer items-center gap-2 rounded-lg px-3 py-2 text-sm ring-1 ring-slate-200" :class="video === '{{ $k }}' && 'bg-indigo-50 ring-indigo-400'"><input type="radio" name="video_type" value="{{ $k }}" x-model="video" class="checkbox"> {{ $l }}</label>
                @endforeach
            </div>
            <div x-show="video === 'youtube' || video === 'vimeo'" class="mt-3"><label class="label">Video URL</label><input name="video_url" value="{{ old('video_url', $post->video_url) }}" class="input" placeholder="https://www.youtube.com/watch?v=… or https://vimeo.com/…"><p class="help">Thumbnail is pulled from YouTube automatically if no featured image is set.</p></div>
            <div x-show="video === 'self_hosted'" class="mt-3" x-data="mediaField('video_media_path', 'video')">
                <label class="label">Video file (MP4/WebM, max {{ \App\Services\MediaService::MAX_VIDEO_MB }} MB)</label>
                <input type="hidden" id="video_media_path" value="{{ old('video_media_path', $post->videoMedia?->path) }}" data-url="{{ $post->videoMedia?->url }}">
                <input type="hidden" name="video_media_id" x-ref="mediaId" value="{{ old('video_media_id', $post->video_media_id) }}">
                <div class="flex items-center gap-2"><span class="truncate text-sm text-slate-600" x-text="value || 'No file selected'"></span><button type="button" @click="pick()" class="btn-secondary btn-sm">Choose / upload</button><button type="button" x-show="value" @click="clear(); $refs.mediaId.value=''" class="btn-secondary btn-sm">Remove</button></div>
                <p class="help">Served with HTML5 video and byte-range streaming from /storage. For large libraries put /storage behind a CDN (Settings → Performance).</p>
            </div>
            <div x-show="video === 'external'" class="mt-3"><label class="label">Embed code (HTTPS &lt;iframe&gt; only)</label><textarea name="video_embed" rows="3" class="textarea min-h-[70px] font-mono text-xs">{{ old('video_embed', $post->video_embed) }}</textarea></div>
            <div x-show="video !== 'none'" class="mt-3 w-48"><label class="label">Duration (seconds, optional)</label><input type="number" name="video_duration" min="0" value="{{ old('video_duration', $post->video_duration) }}" class="input"><p class="help">Filled automatically after the first play if left empty.</p></div>
        </div>

        <div class="card">
            <h2 class="card-title">{{ $type === 'vlog' ? 'Description / Article' : 'Article content' }}</h2>
            <p class="help mb-2">Original, meaningful content matters for readers, search and AdSense review. Thin pages (under 100 words without video) are automatically set to noindex and show no ads.</p>
            <input type="hidden" name="content" id="content-input" value="{{ old('content', $post->content) }}">
            <div data-editor="#content-input" class="bg-white"></div>
        </div>

        <div class="card" x-data="{ t: 'seo' }">
            <div class="flex gap-1 rounded-lg bg-slate-100 p-1 text-sm"><button type="button" class="tab flex-1" :class="t==='seo' && 'active'" @click="t='seo'">SEO</button><button type="button" class="tab flex-1" :class="t==='social' && 'active'" @click="t='social'">Social / Open Graph</button><button type="button" class="tab flex-1" :class="t==='advanced' && 'active'" @click="t='advanced'">Advanced</button></div>
            <div x-show="t==='seo'" class="mt-4 grid gap-4">
                <div><label class="label">SEO title <span class="font-normal text-slate-400">(≤ 60 chars recommended)</span></label><input name="seo_title" maxlength="255" value="{{ old('seo_title', $post->seo_title) }}" class="input" placeholder="Defaults to the title"></div>
                <div><label class="label">Meta description <span class="font-normal text-slate-400">(50–160 chars)</span></label><textarea name="meta_description" maxlength="500" rows="2" class="textarea min-h-[60px]">{{ old('meta_description', $post->meta_description) }}</textarea></div>
                <div class="grid gap-4 sm:grid-cols-2"><div><label class="label">Focus keyword</label><input name="focus_keyword" maxlength="150" value="{{ old('focus_keyword', $post->focus_keyword) }}" class="input"></div><div><label class="label">Canonical URL</label><input name="canonical_url" type="url" maxlength="1000" value="{{ old('canonical_url', $post->canonical_url) }}" class="input" placeholder="Leave empty for self-canonical"></div></div>
                <div class="grid gap-4 sm:grid-cols-2"><div><label class="label">Robots</label><select name="robots" class="select"><option value="" @selected(!old('robots', $post->robots))>Automatic (index when published & not thin)</option>@foreach(['index, follow', 'noindex, follow', 'noindex, nofollow'] as $r)<option @selected(old('robots', $post->robots) === $r)>{{ $r }}</option>@endforeach</select></div><div><label class="label">Twitter card</label><select name="twitter_card" class="select">@foreach(['summary_large_image', 'summary', 'player'] as $r)<option @selected(old('twitter_card', $post->twitter_card) === $r)>{{ $r }}</option>@endforeach</select></div></div>
            </div>
            <div x-show="t==='social'" x-cloak class="mt-4 grid gap-4">
                <div><label class="label">OG title</label><input name="og_title" maxlength="255" value="{{ old('og_title', $post->og_title) }}" class="input"></div>
                <div><label class="label">OG description</label><textarea name="og_description" maxlength="500" rows="2" class="textarea min-h-[60px]">{{ old('og_description', $post->og_description) }}</textarea></div>
                <div x-data="mediaField('og_image')"><label class="label">OG image (1200×630)</label><input type="hidden" id="og_image" name="og_image" value="{{ old('og_image', $post->og_image) }}" data-url="{{ $post->og_image_url }}"><div class="flex items-center gap-3"><img x-show="url" :src="url" class="h-14 w-24 rounded object-cover ring-1 ring-slate-200"><button type="button" @click="pick()" class="btn-secondary btn-sm">Choose</button><button type="button" x-show="value" @click="clear()" class="btn-secondary btn-sm">Remove</button></div></div>
            </div>
            <div x-show="t==='advanced'" x-cloak class="mt-4 grid gap-4">
                <p class="text-sm text-slate-500">Structured data (Article / VideoObject / BreadcrumbList) is generated automatically from the visible content. No ratings or reviews are ever added.</p>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="allow_comments" value="1" class="checkbox" @checked(old('allow_comments', $post->allow_comments))> Allow comments</label>
            </div>
        </div>
    </div>

    <div class="space-y-5">
        <div class="card">
            <h2 class="card-title">Publish</h2>
            <div class="mt-3"><label class="label">Status</label>
                <select name="status" x-model="status" class="select">
                    <option value="draft">Draft</option>
                    <option value="published" @disabled(!$canPublish)>Published</option>
                    <option value="scheduled" @disabled(!$canPublish)>Scheduled</option>
                    <option value="unpublished" @disabled(!$canPublish)>Unpublished</option>
                </select>
                @unless($canPublish)<p class="help">Your role can save drafts; an editor will publish.</p>@endunless
            </div>
            <div class="mt-3" x-show="status === 'scheduled'"><label class="label">Schedule date & time</label><input type="datetime-local" name="scheduled_at" value="{{ old('scheduled_at', $post->scheduled_at?->format('Y-m-d\TH:i')) }}" class="input"><p class="help">Published automatically by the scheduler.</p></div>
            <div class="mt-3" x-show="status === 'published'"><label class="label">Publish date</label><input type="datetime-local" name="published_at" value="{{ old('published_at', $post->published_at?->format('Y-m-d\TH:i')) }}" class="input"><p class="help">Leave empty to use now.</p></div>
            <div class="mt-3"><label class="label">Author</label><select name="author_id" class="select" @disabled(!auth()->user()->hasPermission('posts.edit_any'))>@foreach($authors as $a)<option value="{{ $a->id }}" @selected(old('author_id', $post->author_id ?? auth()->id()) == $a->id)>{{ $a->name }}</option>@endforeach</select></div>
            <div class="mt-3 space-y-2 text-sm">
                <label class="flex items-center gap-2"><input type="checkbox" name="is_featured" value="1" class="checkbox" @checked(old('is_featured', $post->is_featured))> Featured</label>
                <label class="flex items-center gap-2"><input type="checkbox" name="is_trending" value="1" class="checkbox" @checked(old('is_trending', $post->is_trending))> Trending</label>
                <label class="flex items-center gap-2"><input type="checkbox" name="is_recommended" value="1" class="checkbox" @checked(old('is_recommended', $post->is_recommended))> Recommended</label>
            </div>
            <div class="mt-4 flex gap-2"><button class="btn-primary flex-1">Save</button>@if($post->exists && auth()->user()->hasPermission('posts.delete'))<button form="delete-form" class="btn-danger" formaction="{{ route("admin.$rk.destroy", $post) }}">Trash</button>@endif</div>
        </div>

        <div class="card">
            <h2 class="card-title">Category</h2>
            <select name="category_id" class="select mt-3"><option value="">— Select —</option>@foreach($categories as $c)<option value="{{ $c->id }}" @selected(old('category_id', $post->category_id) == $c->id)>{{ $c->name }}</option>@endforeach</select>
            <label class="label mt-3">Subcategory</label>
            <select name="subcategory_id" class="select"><option value="">— None —</option>@foreach($categories as $c)@foreach($c->children as $s)<option value="{{ $s->id }}" @selected(old('subcategory_id', $post->subcategory_id) == $s->id)>{{ $c->name }} › {{ $s->name }}</option>@endforeach @endforeach</select>
            <label class="label mt-3">Tags <span class="font-normal text-slate-400">(comma separated)</span></label>
            <input name="tags" value="{{ old('tags', $post->tags->pluck('name')->implode(', ')) }}" list="tag-list" class="input" placeholder="travel, budget, guide">
            <datalist id="tag-list">@foreach($allTags as $t)<option value="{{ $t }}">@endforeach</datalist>
        </div>

        <div class="card" x-data="mediaField('featured_image')">
            <h2 class="card-title">Featured image</h2>
            <input type="hidden" id="featured_image" name="featured_image" value="{{ old('featured_image', $post->featured_image) }}" data-url="{{ $post->featured_image ? media_url($post->featured_image) : '' }}">
            <div class="mt-3 aspect-video overflow-hidden rounded-lg bg-slate-100 ring-1 ring-slate-200"><img x-show="url" :src="url" class="h-full w-full object-cover" alt=""></div>
            <div class="mt-2 flex gap-2"><button type="button" @click="pick()" class="btn-secondary btn-sm">Choose / upload</button><button type="button" x-show="value" @click="clear()" class="btn-secondary btn-sm">Remove</button></div>
            <label class="label mt-3">Alt text</label><input name="featured_image_alt" maxlength="255" value="{{ old('featured_image_alt', $post->featured_image_alt) }}" class="input" placeholder="Describe the image">
        </div>

        <div class="card" x-data="mediaField('thumbnail')">
            <h2 class="card-title">Thumbnail <span class="font-normal text-slate-400">(optional, 16:9)</span></h2>
            <input type="hidden" id="thumbnail" name="thumbnail" value="{{ old('thumbnail', $post->thumbnail) }}" data-url="{{ $post->thumbnail ? media_url($post->thumbnail) : '' }}">
            <div class="mt-3 flex items-center gap-3"><div class="h-14 w-24 overflow-hidden rounded bg-slate-100 ring-1 ring-slate-200"><img x-show="url" :src="url" class="h-full w-full object-cover" alt=""></div><button type="button" @click="pick()" class="btn-secondary btn-sm">Choose</button><button type="button" x-show="value" @click="clear()" class="btn-secondary btn-sm">Remove</button></div>
            <p class="help">Falls back to the featured image, then the YouTube thumbnail.</p>
        </div>

        @if($quality)
        <div class="card">
            <div class="flex items-center justify-between"><h2 class="card-title">Quality checklist</h2><span class="text-sm font-bold {{ $quality['percent'] >= 80 ? 'text-emerald-600' : 'text-amber-600' }}">{{ $quality['passed'] }}/{{ $quality['total'] }}</span></div>
            <div class="progress mt-2"><div style="width: {{ $quality['percent'] }}%"></div></div>
            <ul class="mt-3 space-y-1.5 text-sm">
                @foreach($quality['checks'] as $k => $c)
                    <li class="flex items-start gap-2"><span class="{{ $c['ok'] ? 'text-emerald-500' : 'text-slate-300' }}">{{ $c['ok'] ? '✔' : '○' }}</span><span class="{{ $c['ok'] ? 'text-slate-600' : 'text-slate-800' }}">{{ $c['label'] }}</span><input type="hidden" name="quality_checklist[{{ $k }}]" value="{{ $c['ok'] ? 1 : 0 }}"></li>
                @endforeach
            </ul>
        </div>
        @endif
    </div>
</form>
@if($post->exists)<form id="delete-form" method="post" data-confirm="Move this {{ $type }} to trash?">@csrf @method('DELETE')</form>@endif
@endsection
