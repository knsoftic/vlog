<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;

class AuthController extends Controller
{
    public function __construct(protected AuditLogger $audit)
    {
    }

    public function showLogin()
    {
        if (Auth::check()) {
            return redirect()->route('admin.dashboard');
        }
        return view('admin.auth.login');
    }

    public function login(Request $request)
    {
        $data = $request->validate(['email' => 'required|email', 'password' => 'required|string', 'remember' => 'nullable|boolean']);
        $email = strtolower($data['email']);
        $ipKey = 'login:ip:'.sha1((string) $request->ip());
        if (RateLimiter::tooManyAttempts($ipKey, 20)) {
            $this->audit->security('rate_limited', 'warning', ['scope' => 'login'], $email);
            return back()->withErrors(['email' => 'Too many login attempts from this network. Try again in a few minutes.'])->onlyInput('email');
        }
        RateLimiter::hit($ipKey, 900);

        $user = User::where('email', $email)->first();
        $maxAttempts = max(3, (int) setting('security.max_login_attempts', 5));
        $lockMinutes = max(1, (int) setting('security.lockout_minutes', 15));

        if ($user && $user->isLocked()) {
            $this->audit->security('locked', 'warning', ['until' => $user->locked_until->toDateTimeString()], $email, $user->id);
            return back()->withErrors(['email' => 'This account is temporarily locked. Try again at '.$user->locked_until->format('H:i').'.'])->onlyInput('email');
        }

        if (! $user || ! $user->is_active || ! Auth::attempt(['email' => $email, 'password' => $data['password']], (bool) ($data['remember'] ?? false))) {
            if ($user) {
                $user->failed_login_attempts++;
                if ($user->failed_login_attempts >= $maxAttempts) {
                    $user->locked_until = now()->addMinutes($lockMinutes);
                    $user->failed_login_attempts = 0;
                }
                $user->save();
            }
            $this->audit->security('failed_login', 'warning', ['reason' => $user ? ($user->is_active ? 'bad password' : 'inactive') : 'unknown email'], $email, $user?->id);
            $this->audit->log('failed_login', 'auth', null, 'Failed login attempt for '.$email);
            return back()->withErrors(['email' => 'These credentials do not match our records.'])->onlyInput('email');
        }

        $request->session()->regenerate();
        RateLimiter::clear($ipKey);
        $user->update(['failed_login_attempts' => 0, 'locked_until' => null, 'last_login_at' => now(), 'last_login_ip' => $this->audit->ipForStorage($request->ip())]);
        $this->audit->security('login', 'info', [], $email, $user->id);
        $this->audit->log('login', 'auth', $user, 'Signed in');
        return redirect()->intended(route('admin.dashboard'));
    }

    public function logout(Request $request)
    {
        $user = Auth::user();
        if ($user) {
            $this->audit->log('logout', 'auth', $user, 'Signed out');
            $this->audit->security('logout', 'info', [], $user->email, $user->id);
        }
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('admin.login');
    }
}
