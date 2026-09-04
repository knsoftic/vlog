<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Post;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CommentController extends Controller
{
    public function __construct(protected AuditLogger $audit)
    {
    }

    public function index(Request $request)
    {
        $status = in_array($request->status, ['pending', 'approved', 'spam'], true) ? $request->status : 'pending';
        $comments = Comment::with('post:id,title,slug,type')->where('status', $status)->latest()->paginate(30)->withQueryString();
        $counts = Comment::selectRaw('status, COUNT(*) c')->groupBy('status')->pluck('c', 'status');
        return view('admin.comments.index', compact('comments', 'status', 'counts'));
    }

    public function update(Request $request, Comment $comment)
    {
        $data = $request->validate(['status' => ['required', Rule::in(['pending', 'approved', 'spam'])]]);
        $comment->update($data);
        Post::whereKey($comment->post_id)->update(['comments_count' => Comment::where('post_id', $comment->post_id)->where('status', 'approved')->count()]);
        $this->audit->log('comment_moderated', 'comments', $comment, 'Comment #'.$comment->id.' → '.$data['status']);
        return back()->with('success', 'Comment '.$data['status'].'.');
    }

    public function destroy(Comment $comment)
    {
        $postId = $comment->post_id;
        $comment->delete();
        Post::whereKey($postId)->update(['comments_count' => Comment::where('post_id', $postId)->where('status', 'approved')->count()]);
        return back()->with('success', 'Comment deleted.');
    }
}
