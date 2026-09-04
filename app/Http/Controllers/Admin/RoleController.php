<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RoleController extends Controller
{
    public function __construct(protected AuditLogger $audit)
    {
    }

    public function index()
    {
        $roles = Role::withCount('users')->with('permissions')->orderByDesc('level')->get();
        return view('admin.roles.index', compact('roles'));
    }

    public function permissions()
    {
        $roles = Role::with('permissions')->orderByDesc('level')->get();
        $permissions = Permission::orderBy('group')->orderBy('name')->get()->groupBy('group');
        return view('admin.roles.permissions', compact('roles', 'permissions'));
    }

    public function store(Request $request)
    {
        $data = $request->validate(['name' => 'required|string|max:100', 'description' => 'nullable|string|max:255', 'level' => 'required|integer|min:1|max:90']);
        $data['slug'] = Str::slug($data['name'], '_');
        if (Role::where('slug', $data['slug'])->exists()) {
            return back()->withErrors(['name' => 'A role with this name already exists.']);
        }
        $role = Role::create($data);
        $this->audit->log('created', 'role', $role, 'Role created: '.$role->name);
        return back()->with('success', 'Role created.');
    }

    public function update(Request $request, Role $role)
    {
        $data = $request->validate(['name' => 'required|string|max:100', 'description' => 'nullable|string|max:255', 'level' => 'required|integer|min:1|max:90']);
        if ($role->is_system) {
            unset($data['level']);
        }
        $original = $role->getOriginal();
        $role->update($data);
        $this->audit->logModelChange('updated', 'role', $role, $original, 'Role updated: '.$role->name);
        return back()->with('success', 'Role updated.');
    }

    public function destroy(Role $role)
    {
        if ($role->is_system) {
            return back()->withErrors(['role' => 'System roles cannot be deleted.']);
        }
        if ($role->users()->exists()) {
            return back()->withErrors(['role' => 'Reassign users before deleting this role.']);
        }
        $this->audit->log('deleted', 'role', $role, 'Role deleted: '.$role->name);
        $role->delete();
        return back()->with('success', 'Role deleted.');
    }

    public function syncPermissions(Request $request)
    {
        $matrix = $request->input('perm', []); // [role_id][permission_id] = 1
        foreach (Role::all() as $role) {
            if ($role->slug === 'super_admin') {
                continue; // implicit all
            }
            $before = $role->permissions()->pluck('slug')->all();
            $ids = array_keys(array_filter($matrix[$role->id] ?? []));
            $role->permissions()->sync($ids);
            $after = $role->permissions()->pluck('slug')->all();
            if ($before != $after) {
                $this->audit->log('permissions_changed', 'role', $role, 'Permissions changed for '.$role->name, ['permissions' => $before], ['permissions' => $after]);
            }
        }
        return back()->with('success', 'Permissions saved.');
    }
}
