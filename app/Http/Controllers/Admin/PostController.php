<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\HtmlSanitizer;
use App\Services\SeoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

class PostController extends Controller
{
    public function __construct(protected AuditLogger $audit, protected HtmlSanitizer $sanitizer)
    {
    }

    protected function type(Request $request): string
    {
        return $request->route()->getName() && str_contains($request->route()->getName(), 'articles') ? Post::TYPE_ARTICLE : Post::TYPE_VLOG;
    }

    public function index(Request $request)
    {
        $type = $this->type($request);
        $q = Post::where('type', $type)->with(['category', 'author'])->withCount('comments');
        if ($request->filled('status')) {
            $q->where('status', $request->status);
        }
        if ($request->filled('category')) {
            $q->where('category_id', (int) $request->category);
        }
        if ($request->filled('author')) {
            $q->where('author_id', (int) $request->author);
        }
        if ($request->filled('s')) {
            $s = '%'.$request->s.'%';
            $q->where(fn ($w) => $w->where('title', 'like', $s)->orWhere('slug', 'like', $s));
        }
        if (! auth()->user()->hasPermission('posts.edit_any')) {
            $q->where('author_id', auth()->id());
        }
        $sort = in_array($request->sort, ['title', 'views_count', 'published_at', 'created_at', 'status'], true) ? $request->sort : 'created_at';
        $posts = $q->orderBy($sort, $request->dir === 'asc' ? 'asc' : 'desc')->paginate(20)->withQueryString();
        $counts = Post::where('type', $type)->selectRaw('status, COUNT(*) c')->groupBy('status')->pluck('c', 'status');
        return view('admin.posts.index', [
            'posts' => $posts, 'type' => $type, 'counts' => $counts,
            'categories' => Category::orderBy('name')->get(), 'authors' => User::whereNotNull('role_id')->orderBy('name')->get(),
        ]);
    }

    public function create(Request $request)
    {
        $type = $this->type($request);
        $post = new Post(['type' => $type, 'status' => 'draft', 'allow_comments' => true, 'author_id' => auth()->id(), 'video_type' => $type === 'vlog' ? 'youtube' : 'none']);
        return view('admin.posts.form', $this->formData($post));
    }

    public function store(Request $request)
    {
        $type = $this->type($request);
        $data = $this->validated($request, null);
        $post = new Post;
        $post->type = $type;
        $post->created_by = auth()->id();
        $this->fill($post, $data);
        $post->save();
        $post->tags()->sync(Tag::findOrCreateByNames($this->tagNames($request)));
        $this->afterSave($post);
        $this->audit->log('created', $type, $post, ucfirst($type).' created: '.$post->title, null, $this->audit->redact($post->only(['title', 'slug', 'status', 'category_id', 'published_at'])));
        return redirect()->route("admin.{$this->routeKey($type)}.edit", $post)->with('success', ucfirst($type).' saved.');
    }

    public function edit(Request $request, Post $post)
    {
        $this->authorizeEdit($post);
        return view('admin.posts.form', $this->formData($post));
    }

    public function update(Request $request, Post $post)
    {
        $this->authorizeEdit($post);
        $data = $this->validated($request, $post);
        $original = $post->getOriginal();
        $this->fill($post, $data);
        $post->save();
        $post->tags()->sync(Tag::findOrCreateByNames($this->tagNames($request)));
        $this->afterSave($post);
        $this->audit->logModelChange('updated', $post->type, $post, $original, ucfirst($post->type).' updated: '.$post->title);
        return redirect()->route("admin.{$this->routeKey($post->type)}.edit", $post)->with('success', ucfirst($post->type).' updated.');
    }

    public function destroy(Post $post)
    {
        $this->authorizeEdit($post, 'posts.delete');
        $post->delete();
        $this->afterSave($post);
        $this->audit->log('deleted', $post->type, $post, ucfirst($post->type).' deleted: '.$post->title, $post->only(['title', 'slug', 'status']), null);
        return redirect()->route("admin.{$this->routeKey($post->type)}.index")->with('success', ucfirst($post->type).' moved to trash.');
    }

    public function duplicate(Post $post)
    {
        $this->authorizeEdit($post);
        $copy = $post->replicate(['views_count', 'unique_views_count', 'video_plays_count', 'shares_count', 'comments_count', 'published_at', 'scheduled_at']);
        $copy->title = $post->title.' (Copy)';
        $copy->slug = null;
        $copy->status = 'draft';
        $copy->created_by = auth()->id();
        $copy->save();
        $copy->tags()->sync($post->tags->pluck('id'));
        $this->audit->log('created', $post->type, $copy, 'Duplicated from #'.$post->id);
        return redirect()->route("admin.{$this->routeKey($post->type)}.edit", $copy)->with('success', 'Duplicate created as draft.');
    }

    public function status(Request $request, Post $post)
    {
        $this->authorizeEdit($post);
        $data = $request->validate(['status' => ['required', Rule::in(Post::STATUSES)], 'scheduled_at' => 'nullable|date']);
        $before = $post->only(['status', 'published_at', 'scheduled_at']);
        $post->status = $data['status'];
        if ($data['status'] === 'published' && ! $post->published_at) {
            $post->published_at = now();
        }
        if ($data['status'] === 'scheduled') {
            $post->scheduled_at = $data['scheduled_at'] ?? $post->scheduled_at ?? now()->addHour();
            $post->published_at = $post->scheduled_at;
        }
        $post->save();
        $this->afterSave($post);
        $this->audit->log('status_changed', $post->type, $post, 'Status → '.$post->status, $before, $post->only(['status', 'published_at', 'scheduled_at']));
        return back()->with('success', 'Status updated to '.$post->status.'.');
    }

    public function preview(Post $post, SeoService $seo)
    {
        $this->authorizeEdit($post);
        $post->load(['category', 'subcategory', 'author', 'tags', 'videoMedia']);
        $related = Post::published()->where('id', '!=', $post->id)->with(['category', 'author'])->orderByDesc('published_at')->limit(3)->get();
        $meta = $seo->forPost($post);
        $meta['robots'] = 'noindex, nofollow';
        $breadcrumbs = $seo->postBreadcrumbs($post);
        return view('frontend.post', ['post' => $post, 'related' => $related, 'comments' => collect(), 'meta' => $meta, 'breadcrumbs' => $breadcrumbs, 'adsAllowed' => false, 'isPreview' => true]);
    }

    public function analytics(Request $request, Post $post, \App\Services\AnalyticsService $analytics, \App\Services\SearchConsoleService $gsc)
    {
        $this->authorizeEdit($post);
        $range = \App\Support\DateRange::fromRequest($request, '30d');
        $analytics->ensureFresh();
        $totals = $analytics->contentTotals($post->id, $range->from, $range->to);
        $prev = $analytics->contentTotals($post->id, $range->prevFrom, $range->prevTo);
        $today = $analytics->contentTotals($post->id, now()->startOfDay(), now()->startOfDay());
        $d7 = $analytics->contentTotals($post->id, now()->subDays(6)->startOfDay(), now()->startOfDay());
        $d7prev = $analytics->contentTotals($post->id, now()->subDays(13)->startOfDay(), now()->subDays(7)->startOfDay());
        $d30 = $analytics->contentTotals($post->id, now()->subDays(29)->startOfDay(), now()->startOfDay());
        $month = $analytics->contentTotals($post->id, now()->startOfMonth(), now()->startOfDay());
        $lastMonth = $analytics->contentTotals($post->id, now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfMonth());
        $series = $analytics->contentSeries($post->id, $range->from, $range->to);
        $countries = $analytics->contentDimension($post->id, 'country', $range->from, $range->to);
        $devices = $analytics->contentDimension($post->id, 'device', $range->from, $range->to);
        $sources = $analytics->contentDimension($post->id, 'source', $range->from, $range->to);
        $search = $gsc->isConnected() ? $gsc->pageTotals($post->url, $range->from, $range->to) : null;
        return view('admin.posts.analytics', compact('post', 'range', 'totals', 'prev', 'today', 'd7', 'd7prev', 'd30', 'month', 'lastMonth', 'series', 'countries', 'devices', 'sources', 'search'));
    }

    public function trash(Request $request)
    {
        $type = $this->type($request);
        $posts = Post::onlyTrashed()->where('type', $type)->with('author')->latest('deleted_at')->paginate(20);
        return view('admin.posts.trash', compact('posts', 'type'));
    }

    public function restore(int $id)
    {
        $post = Post::onlyTrashed()->findOrFail($id);
        $this->authorizeEdit($post, 'posts.delete');
        $post->restore();
        $this->audit->log('restored', $post->type, $post, 'Restored from trash');
        return back()->with('success', 'Restored.');
    }

    public function forceDelete(int $id)
    {
        $post = Post::onlyTrashed()->findOrFail($id);
        $this->authorizeEdit($post, 'posts.delete');
        $this->audit->log('deleted_permanently', $post->type, $post, 'Permanently deleted: '.$post->title, $post->only(['title', 'slug']), null);
        $post->forceDelete();
        return back()->with('success', 'Permanently deleted.');
    }

    // ---- helpers ----

    protected function routeKey(string $type): string
    {
        return $type === Post::TYPE_ARTICLE ? 'articles' : 'vlogs';
    }

    protected function authorizeEdit(Post $post, string $perm = 'posts.edit'): void
    {
        $u = auth()->user();
        if ($u->hasPermission('posts.edit_any') && $u->hasPermission($perm)) {
            return;
        }
        if ($post->author_id === $u->id && $u->hasPermission($perm)) {
            return;
        }
        abort(403, 'You cannot modify this content.');
    }

    protected function formData(Post $post): array
    {
        return [
            'post' => $post,
            'type' => $post->type,
            'categories' => Category::with('children')->topLevel()->orderBy('sort_order')->orderBy('name')->get(),
            'authors' => User::whereNotNull('role_id')->where('is_active', true)->orderBy('name')->get(),
            'allTags' => Tag::orderBy('name')->pluck('name'),
            'quality' => $post->exists ? $post->qualityScore() : null,
        ];
    }

    protected function tagNames(Request $request): array
    {
        $raw = (string) $request->input('tags', '');
        return array_filter(array_map('trim', preg_split('/[,\n]/', $raw)));
    }

    protected function validated(Request $request, ?Post $post): array
    {
        $canPublish = auth()->user()->hasPermission('posts.publish');
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'seo_title' => 'nullable|string|max:255',
            'slug' => ['nullable', 'string', 'max:255', 'regex:/^[a-z0-9-]+$/', Rule::unique('posts', 'slug')->ignore($post?->id)->whereNull('deleted_at')],
            'excerpt' => 'nullable|string|max:2000',
            'content' => 'nullable|string',
            'featured_image' => 'nullable|string|max:500',
            'featured_image_alt' => 'nullable|string|max:255',
            'thumbnail' => 'nullable|string|max:500',
            'video_type' => ['required', Rule::in(['none', 'youtube', 'vimeo', 'self_hosted', 'external'])],
            'video_url' => 'nullable|string|max:1000',
            'video_embed' => 'nullable|string|max:5000',
            'video_media_id' => 'nullable|integer|exists:media,id',
            'video_duration' => 'nullable|integer|min:0',
            'category_id' => 'nullable|integer|exists:categories,id',
            'subcategory_id' => 'nullable|integer|exists:categories,id',
            'author_id' => 'nullable|integer|exists:users,id',
            'status' => ['required', Rule::in(Post::STATUSES)],
            'published_at' => 'nullable|date',
            'scheduled_at' => 'nullable|date',
            'is_featured' => 'nullable|boolean',
            'is_trending' => 'nullable|boolean',
            'is_recommended' => 'nullable|boolean',
            'allow_comments' => 'nullable|boolean',
            'canonical_url' => 'nullable|url|max:1000',
            'meta_description' => 'nullable|string|max:500',
            'focus_keyword' => 'nullable|string|max:150',
            'og_title' => 'nullable|string|max:255',
            'og_description' => 'nullable|string|max:500',
            'og_image' => 'nullable|string|max:500',
            'twitter_card' => ['nullable', Rule::in(['summary', 'summary_large_image', 'player'])],
            'robots' => 'nullable|string|max:100',
            'quality_checklist' => 'nullable|array',
        ]);
        if (! $canPublish && in_array($data['status'], ['published', 'scheduled'], true)) {
            $data['status'] = $post?->status === 'published' ? 'published' : 'draft';
        }
        if (! auth()->user()->hasPermission('posts.edit_any')) {
            $data['author_id'] = $post?->author_id ?? auth()->id();
        }
        if ($data['video_type'] === 'youtube' && ! empty($data['video_url']) && ! Post::extractYoutubeId($data['video_url'])) {
            throw \Illuminate\Validation\ValidationException::withMessages(['video_url' => 'That does not look like a valid YouTube URL.']);
        }
        if ($data['video_type'] === 'external' && ! empty($data['video_embed'])) {
            // only allow iframes from https sources; strip scripts
            if (preg_match('/<script/i', $data['video_embed']) || ! preg_match('~<iframe[^>]+src=["\']https://~i', $data['video_embed'])) {
                throw \Illuminate\Validation\ValidationException::withMessages(['video_embed' => 'Only HTTPS <iframe> embeds are allowed (no scripts).']);
            }
        }
        return $data;
    }

    protected function fill(Post $post, array $data): void
    {
        $data['content'] = $this->sanitizer->clean($data['content'] ?? '');
        $data['excerpt'] = $this->sanitizer->stripAll($data['excerpt'] ?? '');
        foreach (['is_featured', 'is_trending', 'is_recommended', 'allow_comments'] as $b) {
            $data[$b] = (bool) ($data[$b] ?? false);
        }
        if ($data['video_type'] === 'none') {
            $data['video_url'] = null;
            $data['video_embed'] = null;
            $data['video_media_id'] = null;
        }
        if ($data['status'] === 'scheduled') {
            $data['scheduled_at'] = ($data['scheduled_at'] ?? null) ?: now()->addHour();
            $data['published_at'] = $data['scheduled_at'];
        } elseif ($data['status'] === 'published') {
            $data['published_at'] = ($data['published_at'] ?? null) ?: ($post->published_at ?: now());
            $data['scheduled_at'] = null;
        }
        $post->fill($data);
    }

    protected function afterSave(Post $post): void
    {
        Cache::forget('home.sections');
        Cache::forget('sitemap.xml');
        Cache::forget('site.nav');
        Category::whereKey($post->category_id)->update(['posts_count' => Post::published()->where('category_id', $post->category_id)->count()]);
    }
}
