<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Services\AuditLogger;
use App\Services\SeoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

class PageController extends Controller
{
    public function __construct(protected SeoService $seo)
    {
    }

    public function show(Request $request, string $slug)
    {
        $page = Page::published()->where('slug', $slug)->firstOrFail();
        $request->attributes->set('page_meta', ['page_type' => 'page', 'post_id' => null, 'title' => $page->title]);
        $meta = $this->seo->forPage($page);
        Page::whereKey($page->id)->increment('views_count');
        return view($page->template === 'contact' ? 'frontend.contact' : 'frontend.page', compact('page', 'meta'));
    }

    public function contactSubmit(Request $request)
    {
        $key = 'contact:'.sha1((string) $request->ip());
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->withErrors(['message' => 'Too many messages sent. Please try again later.'])->withInput();
        }
        RateLimiter::hit($key, 3600);
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'email' => 'required|email|max:190',
            'subject' => 'required|string|max:150',
            'message' => 'required|string|max:5000',
            'website' => 'nullable|max:0', // honeypot
        ]);
        $to = setting('mail.contact_recipient') ?: setting('site.email');
        if ($to) {
            try {
                Mail::raw("From: {$data['name']} <{$data['email']}>\nSubject: {$data['subject']}\n\n{$data['message']}", function ($m) use ($to, $data) {
                    $m->to($to)->subject('[Contact] '.$data['subject'])->replyTo($data['email'], $data['name']);
                });
            } catch (\Throwable $e) {
                report($e);
            }
        }
        app(AuditLogger::class)->security('contact_form', 'info', ['subject' => $data['subject']], $data['email']);
        return back()->with('success', 'Thanks! Your message has been sent.');
    }
}
