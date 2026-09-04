<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    public function __construct(protected AuditLogger $audit)
    {
    }

    public function index()
    {
        $categories = Category::with('children')->topLevel()->withCount('posts')->orderBy('sort_order')->orderBy('name')->get();
        return view('admin.categories.index', compact('categories'));
    }

    public function create()
    {
        return view('admin.categories.form', ['category' => new Category(['status' => 'active']), 'parents' => Category::topLevel()->orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $c = Category::create($data);
        $this->flush();
        $this->audit->log('created', 'category', $c, 'Category created: '.$c->name, null, $data);
        return redirect()->route('admin.categories.index')->with('success', 'Category created.');
    }

    public function edit(Category $category)
    {
        return view('admin.categories.form', ['category' => $category, 'parents' => Category::topLevel()->where('id', '!=', $category->id)->orderBy('name')->get()]);
    }

    public function update(Request $request, Category $category)
    {
        $data = $this->validated($request, $category);
        if (! empty($data['parent_id']) && (int) $data['parent_id'] === $category->id) {
            $data['parent_id'] = null;
        }
        $original = $category->getOriginal();
        $category->update($data);
        $this->flush();
        $this->audit->logModelChange('updated', 'category', $category, $original, 'Category updated: '.$category->name);
        return redirect()->route('admin.categories.index')->with('success', 'Category updated.');
    }

    public function destroy(Category $category)
    {
        $this->audit->log('deleted', 'category', $category, 'Category deleted: '.$category->name, $category->only(['name', 'slug']), null);
        $category->delete();
        $this->flush();
        return back()->with('success', 'Category deleted. Posts were kept (uncategorised).');
    }

    public function reorder(Request $request)
    {
        $ids = $request->validate(['order' => 'required|array', 'order.*' => 'integer'])['order'];
        foreach ($ids as $i => $id) {
            Category::whereKey($id)->update(['sort_order' => $i]);
        }
        $this->flush();
        return response()->json(['ok' => true]);
    }

    protected function validated(Request $request, ?Category $c = null): array
    {
        return $request->validate([
            'name' => 'required|string|max:150',
            'slug' => ['nullable', 'string', 'max:180', 'regex:/^[a-z0-9-]+$/', Rule::unique('categories', 'slug')->ignore($c?->id)],
            'parent_id' => 'nullable|integer|exists:categories,id',
            'description' => 'nullable|string|max:2000',
            'image' => 'nullable|string|max:500',
            'seo_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'sort_order' => 'nullable|integer|min:0',
        ]);
    }

    protected function flush(): void
    {
        Cache::forget('site.nav');
        Cache::forget('home.sections');
        Cache::forget('sitemap.xml');
    }
}
