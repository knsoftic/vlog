<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Post;
use App\Services\BotDetector;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class CommentController extends Controller
{
    public function store(Request $request, Post $post, BotDetector $bots)
    {
        if (! $post->isPublished() || ! $post->allow_comments) {
            abort(404);
        }
        $key = 'comment:'.$bots->ipHash($request->ip());
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->withErrors(['content' => 'You are commenting too fast. Please wait a while.'])->withInput();
        }
        RateLimiter::hit($key, 600);
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'email' => 'nullable|email|max:190',
            'content' => 'required|string|min:3|max:3000',
            'parent_id' => 'nullable|integer|exists:comments,id',
            'website' => 'nullable|max:0', // honeypot
        ]);
        $isSpam = preg_match('/https?:\/\/[^\s]+.*https?:\/\/[^\s]+/i', $data['content']) || preg_match('/(viagra|casino|crypto giveaway|loan approval)/i', $data['content']);
        Comment::create([
            'post_id' => $post->id,
            'parent_id' => $data['parent_id'] ?? null,
            'user_id' => auth()->id(),
            'name' => strip_tags($data['name']),
            'email' => $data['email'] ?? null,
            'content' => strip_tags($data['content']),
            'status' => $isSpam ? 'spam' : 'pending',
            'ip_hash' => $bots->ipHash($request->ip()),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
        ]);
        return back()->with('success', 'Thanks! Your comment is awaiting moderation.')->withFragment('comments');
    }
}
