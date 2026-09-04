<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    public function edit()
    {
        return view('admin.profile', ['user' => auth()->user()]);
    }

    public function update(Request $request, AuditLogger $audit)
    {
        $user = auth()->user();
        $data = $request->validate([
            'name' => 'required|string|max:150',
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')->ignore($user->id)],
            'avatar' => 'nullable|string|max:500',
            'bio' => 'nullable|string|max:2000',
            'social_links' => 'nullable|array',
            'social_links.*' => 'nullable|url|max:300',
            'current_password' => 'nullable|required_with:password|string',
            'password' => ['nullable', 'confirmed', Password::min(10)->letters()->numbers()],
        ]);
        if (! empty($data['password'])) {
            if (! Hash::check($data['current_password'], $user->password)) {
                return back()->withErrors(['current_password' => 'Current password is incorrect.']);
            }
            $user->password = $data['password'];
            $audit->security('password_changed', 'info', [], $user->email, $user->id);
        }
        $user->fill(collect($data)->only(['name', 'email', 'avatar', 'bio', 'social_links'])->all());
        $user->save();
        $audit->log('updated', 'profile', $user, 'Profile updated');
        return back()->with('success', 'Profile saved.');
    }
}
