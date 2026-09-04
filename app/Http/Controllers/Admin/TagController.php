<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tag;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TagController extends Controller
{
    public function __construct(protected AuditLogger $audit)
    {
    }

    public function index(Request $request)
    {
        $tags = Tag::withCount('posts')->when($request->s, fn ($q) => $q->where('name', 'like', '%'.$request->s.'%'))->orderByDesc('posts_count')->orderBy('name')->paginate(40)->withQueryString();
        return view('admin.tags.index', compact('tags'));
    }

    public function store(Request $request)
    {
        $data = $request->validate(['name' => 'required|string|max:100', 'slug' => ['nullable', 'regex:/^[a-z0-9-]+$/', Rule::unique('tags', 'slug')], 'description' => 'nullable|string|max:500']);
        $tag = Tag::create($data);
        $this->audit->log('created', 'tag', $tag, 'Tag created: '.$tag->name);
        return back()->with('success', 'Tag created.');
    }

    public function update(Request $request, Tag $tag)
    {
        $data = $request->validate(['name' => 'required|string|max:100', 'slug' => ['nullable', 'regex:/^[a-z0-9-]+$/', Rule::unique('tags', 'slug')->ignore($tag->id)], 'description' => 'nullable|string|max:500']);
        $original = $tag->getOriginal();
        $tag->update($data);
        $this->audit->logModelChange('updated', 'tag', $tag, $original, 'Tag updated: '.$tag->name);
        return back()->with('success', 'Tag updated.');
    }

    public function destroy(Tag $tag)
    {
        $this->audit->log('deleted', 'tag', $tag, 'Tag deleted: '.$tag->name);
        $tag->delete();
        return back()->with('success', 'Tag deleted.');
    }
}
