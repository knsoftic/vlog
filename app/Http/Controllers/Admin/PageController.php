<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Services\AuditLogger;
use App\Services\HtmlSanitizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

class PageController extends Controller
{
    public function __construct(protected AuditLogger $audit, protected HtmlSanitizer $sanitizer)
    {
    }

    public function index()
    {
        $pages = Page::orderBy('sort_order')->orderBy('title')->get();
        return view('admin.pages.index', compact('pages'));
    }

    public function create()
    {
        return view('admin.pages.form', ['page' => new Page(['status' => 'published', 'template' => 'default', 'show_in_footer' => true])]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $page = Page::create($data);
        $this->flush();
        $this->audit->log('page_created', 'page', $page, 'Page created: '.$page->title);
        return redirect()->route('admin.pages.edit', $page)->with('success', 'Page created.');
    }

    public function edit(Page $page)
    {
        return view('admin.pages.form', compact('page'));
    }

    public function update(Request $request, Page $page)
    {
        $data = $this->validated($request, $page);
        if ($page->is_system) {
            unset($data['slug']);
        }
        $original = $page->getOriginal();
        $page->update($data);
        $this->flush();
        $this->audit->logModelChange('page_updated', 'page', $page, $original, 'Page updated: '.$page->title);
        return redirect()->route('admin.pages.edit', $page)->with('success', 'Page updated.');
    }

    public function destroy(Page $page)
    {
        if ($page->is_system) {
            return back()->withErrors(['page' => 'System pages (legal pages) cannot be deleted; unpublish them instead.']);
        }
        $this->audit->log('page_deleted', 'page', $page, 'Page deleted: '.$page->title, $page->only(['title', 'slug']), null);
        $page->delete();
        $this->flush();
        return redirect()->route('admin.pages.index')->with('success', 'Page deleted.');
    }

    protected function validated(Request $request, ?Page $page = null): array
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'slug' => ['nullable', 'regex:/^[a-z0-9-]+$/', 'max:200', Rule::unique('pages', 'slug')->ignore($page?->id)],
            'content' => 'nullable|string',
            'template' => ['required', Rule::in(['default', 'contact'])],
            'status' => ['required', Rule::in(['published', 'draft'])],
            'show_in_footer' => 'nullable|boolean',
            'show_in_header' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
            'canonical_url' => 'nullable|url|max:1000',
            'robots' => 'nullable|string|max:100',
            'og_title' => 'nullable|string|max:255',
            'og_description' => 'nullable|string|max:500',
            'og_image' => 'nullable|string|max:500',
        ]);
        $data['content'] = $this->sanitizer->clean($data['content'] ?? '');
        $data['show_in_footer'] = $request->boolean('show_in_footer');
        $data['show_in_header'] = $request->boolean('show_in_header');
        return $data;
    }

    protected function flush(): void
    {
        Cache::forget('site.nav');
        Cache::forget('sitemap.xml');
    }
}
