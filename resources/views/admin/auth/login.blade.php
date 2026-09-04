<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex, nofollow">
    <title>Sign in · {{ setting('site.name') }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/admin.css'])
</head>
<body class="flex min-h-screen items-center justify-center bg-slate-900 p-4">
    <div class="w-full max-w-sm">
        <div class="mb-6 text-center text-white">
            <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-indigo-600"><svg class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg></span>
            <h1 class="mt-3 text-xl font-bold">{{ setting('site.name') }}</h1>
            <p class="text-sm text-slate-400">Admin panel</p>
        </div>
        <form method="post" action="{{ route('admin.login') }}" class="card space-y-4">
            @csrf
            @if($errors->any())<p class="alert-error mb-0">{{ $errors->first() }}</p>@endif
            <div><label class="label" for="email">Email</label><input id="email" name="email" type="email" required autofocus value="{{ old('email') }}" class="input"></div>
            <div><label class="label" for="password">Password</label><input id="password" name="password" type="password" required class="input"></div>
            <label class="flex items-center gap-2 text-sm text-slate-600"><input type="checkbox" name="remember" value="1" class="checkbox"> Remember me</label>
            <button class="btn-primary w-full">Sign in</button>
        </form>
        <p class="mt-4 text-center text-xs text-slate-500"><a href="{{ route('home') }}" class="hover:text-slate-300">← Back to site</a></p>
    </div>
</body>
</html>
