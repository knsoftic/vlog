<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Post;
use App\Services\SeoService;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function __construct(protected SeoService $seo)
    {
    }

    public function index(Request $request)
    {
        $request->attributes->set('page_meta', ['page_type' => 'listing', 'post_id' => null, 'title' => 'Categories']);
        $categories = Category::active()->topLevel()->withCount(['posts' => fn ($q) => $q->visible()])->with('children')->orderBy('sort_order')->orderBy('name')->get();
        $meta = $this->seo->meta(['title' => $this->seo->title('Categories'), 'description' => 'Browse all topics on '.setting('site.name').'.', 'canonical' => route('categories')]);
        return view('frontend.categories', compact('categories', 'meta'));
    }

    public function show(Request $request, string $slug)
    {
        $category = Category::active()->where('slug', $slug)->with(['children' => fn ($q) => $q->active(), 'parent'])->firstOrFail();
        $request->attributes->set('page_meta', ['page_type' => 'category', 'post_id' => null, 'title' => $category->name]);
        $ids = array_merge([$category->id], $category->children->pluck('id')->all());
        $posts = Post::visible()->where(fn ($q) => $q->whereIn('category_id', $ids)->orWhereIn('subcategory_id', $ids))
            ->with(['category', 'author'])->orderByDesc('published_at')->paginate(max(6, (int) setting('site.posts_per_page', 12)));
        $meta = $this->seo->forCategory($category);
        return view('frontend.category', compact('category', 'posts', 'meta'));
    }
}
