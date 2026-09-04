<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Services\AuditLogger;
use App\Services\MediaService;
use Illuminate\Http\Request;

class MediaController extends Controller
{
    public function __construct(protected MediaService $media, protected AuditLogger $audit)
    {
    }

    public function index(Request $request)
    {
        $q = Media::with('user')->latest();
        if ($request->filled('type')) {
            $q->where('type', $request->type);
        }
        if ($request->filled('s')) {
            $q->where(fn ($w) => $w->where('original_name', 'like', '%'.$request->s.'%')->orWhere('alt', 'like', '%'.$request->s.'%')->orWhere('title', 'like', '%'.$request->s.'%'));
        }
        $items = $q->paginate(36)->withQueryString();
        if ($request->expectsJson()) {
            return response()->json($items);
        }
        $usage = $this->media->storageUsage();
        return view('admin.media.index', compact('items', 'usage'));
    }

    public function store(Request $request)
    {
        $request->validate(['file' => 'required|file|max:'.(MediaService::MAX_VIDEO_MB * 1024), 'alt' => 'nullable|string|max:255']);
        try {
            $m = $this->media->upload($request->file('file'), auth()->id(), $request->input('alt'));
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        $this->audit->log('uploaded', 'media', $m, 'Uploaded '.$m->original_name.' ('.$m->humanSize().')');
        return response()->json(['media' => $m, 'url' => $m->url, 'path' => $m->path, 'id' => $m->id, 'thumb' => $m->thumb_url]);
    }

    public function update(Request $request, Media $media)
    {
        $data = $request->validate(['alt' => 'nullable|string|max:255', 'title' => 'nullable|string|max:255']);
        $media->update($data);
        return response()->json(['ok' => true]);
    }

    public function destroy(Media $media)
    {
        $this->audit->log('deleted', 'media', $media, 'Deleted media '.$media->original_name);
        $this->media->delete($media);
        return request()->expectsJson() ? response()->json(['ok' => true]) : back()->with('success', 'Media deleted.');
    }
}
