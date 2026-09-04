<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContentDaily;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    public function __construct(protected AuditLogger $audit)
    {
    }

    /** kind: admins (all roles) | authors (author role listing with stats) */
    public function index(Request $request)
    {
        $kind = str_contains($request->route()->getName(), 'authors') ? 'authors' : 'admins';
        $q = User::with('role')->withCount(['posts', 'publishedPosts']);
        if ($kind === 'authors') {
            $q->whereHas('publishedPosts')->orWhereHas('role', fn ($r) => $r->where('slug', 'author'));
        }
        if ($request->filled('s')) {
            $q->where(fn ($w) => $w->where('name', 'like', '%'.$request->s.'%')->orWhere('email', 'like', '%'.$request->s.'%'));
        }
        $users = $q->orderBy('name')->paginate(25)->withQueryString();
        $stats = [];
        if ($kind === 'authors') {
            foreach ($users as $u) {
                $views = (int) $u->posts()->sum('views_count');
                $eng = ContentDaily::whereIn('post_id', $u->posts()->select('id'))->selectRaw('SUM(engagement_time) e, SUM(views) v')->first();
                $stats[$u->id] = ['views' => $views, 'video_views' => (int) $u->posts()->sum('video_plays_count'), 'avg_engagement' => $eng && $eng->v > 0 ? (int) ($eng->e / $eng->v) : 0];
            }
        }
        return view('admin.users.index', compact('users', 'kind', 'stats'));
    }

    public function create()
    {
        return view('admin.users.form', ['user' => new User(['is_active' => true]), 'roles' => $this->assignableRoles()]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['password'] = $data['password'];
        $user = User::create($data);
        $this->audit->log('created', 'user', $user, 'User created: '.$user->email, null, $this->audit->redact($data));
        return redirect()->route('admin.users.index')->with('success', 'User created.');
    }

    public function edit(User $user)
    {
        return view('admin.users.form', ['user' => $user, 'roles' => $this->assignableRoles()]);
    }

    public function update(Request $request, User $user)
    {
        $this->guardRole($user);
        $data = $this->validated($request, $user);
        if (empty($data['password'])) {
            unset($data['password']);
        }
        $original = $user->getOriginal();
        $roleChanged = isset($data['role_id']) && (int) $data['role_id'] !== (int) $user->role_id;
        if ($user->id === auth()->id()) {
            unset($data['role_id'], $data['is_active']); // cannot demote/deactivate yourself
        }
        $user->update($data);
        $this->audit->logModelChange($roleChanged ? 'role_changed' : 'updated', 'user', $user, $original, ($roleChanged ? 'Role changed for ' : 'User updated: ').$user->email);
        return redirect()->route('admin.users.index')->with('success', 'User updated.');
    }

    public function destroy(User $user)
    {
        $this->guardRole($user);
        if ($user->id === auth()->id()) {
            return back()->withErrors(['user' => 'You cannot delete your own account.']);
        }
        if ($user->isSuperAdmin() && User::whereHas('role', fn ($r) => $r->where('slug', 'super_admin'))->count() <= 1) {
            return back()->withErrors(['user' => 'At least one Super Admin must remain.']);
        }
        $this->audit->log('deleted', 'user', $user, 'User deleted: '.$user->email, $user->only(['name', 'email', 'role_id']), null);
        $user->delete();
        return redirect()->route('admin.users.index')->with('success', 'User deleted. Their posts were kept.');
    }

    protected function assignableRoles()
    {
        $me = auth()->user();
        return Role::orderByDesc('level')->get()->filter(fn ($r) => $me->isSuperAdmin() || $r->level < ($me->role->level ?? 0));
    }

    protected function guardRole(User $target): void
    {
        $me = auth()->user();
        if ($me->isSuperAdmin() || $target->id === $me->id) {
            return;
        }
        if (($target->role->level ?? 0) >= ($me->role->level ?? 0)) {
            abort(403, 'You cannot manage users with an equal or higher role.');
        }
    }

    protected function validated(Request $request, ?User $user = null): array
    {
        $roleIds = $this->assignableRoles()->pluck('id')->all();
        if ($user && $user->id === auth()->id()) {
            $roleIds[] = $user->role_id;
        }
        return $request->validate([
            'name' => 'required|string|max:150',
            'slug' => ['nullable', 'regex:/^[a-z0-9-]+$/', Rule::unique('users', 'slug')->ignore($user?->id)],
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')->ignore($user?->id)],
            'password' => [$user ? 'nullable' : 'required', 'confirmed', Password::min(10)->letters()->numbers()],
            'role_id' => ['required', Rule::in($roleIds)],
            'avatar' => 'nullable|string|max:500',
            'bio' => 'nullable|string|max:2000',
            'social_links' => 'nullable|array',
            'social_links.*' => 'nullable|url|max:300',
            'is_active' => 'nullable|boolean',
        ]) + ['is_active' => (bool) $request->boolean('is_active')];
    }
}
