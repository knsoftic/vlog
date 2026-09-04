@extends('layouts.app')

@section('content')
<article class="container-x pt-10">
    <nav class="text-xs text-slate-500" aria-label="Breadcrumb"><a href="{{ route('home') }}" class="hover:underline">Home</a> <span class="mx-1">/</span> <span>{{ $page->title }}</span></nav>
    <div class="mx-auto mt-2 grid max-w-5xl gap-10 lg:grid-cols-5">
        <div class="lg:col-span-2">
            <h1 class="text-3xl font-extrabold tracking-tight text-slate-900 sm:text-4xl">{{ $page->title }}</h1>
            <div class="prose-content mt-6">{!! $page->content !!}</div>
            @if(setting('site.email'))<p class="mt-6 text-sm text-slate-500">Email: <a href="mailto:{{ setting('site.email') }}" class="underline">{{ setting('site.email') }}</a></p>@endif
        </div>
        <div class="lg:col-span-3">
            @if(session('success'))<div class="mb-4 rounded-xl bg-emerald-50 p-4 text-sm text-emerald-800">{{ session('success') }}</div>@endif
            <form method="post" action="{{ route('contact.submit') }}" class="grid gap-4 rounded-3xl bg-slate-50 p-6 sm:grid-cols-2 sm:p-8">
                @csrf
                <div><label class="text-xs font-semibold text-slate-600" for="name">Name</label><input id="name" name="name" required maxlength="100" value="{{ old('name') }}" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm"></div>
                <div><label class="text-xs font-semibold text-slate-600" for="email">Email</label><input id="email" name="email" type="email" required maxlength="190" value="{{ old('email') }}" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm"></div>
                <div class="sm:col-span-2"><label class="text-xs font-semibold text-slate-600" for="subject">Subject</label><input id="subject" name="subject" required maxlength="150" value="{{ old('subject') }}" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm"></div>
                <div class="sm:col-span-2"><label class="text-xs font-semibold text-slate-600" for="message">Message</label><textarea id="message" name="message" required rows="6" maxlength="5000" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">{{ old('message') }}</textarea></div>
                <input name="website" tabindex="-1" autocomplete="off" class="hidden" aria-hidden="true">
                @if($errors->any())<p class="text-sm text-rose-600 sm:col-span-2">{{ $errors->first() }}</p>@endif
                <div class="sm:col-span-2"><button class="btn-primary">Send message</button></div>
            </form>
        </div>
    </div>
</article>
@endsection
