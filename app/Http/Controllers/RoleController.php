<?php

namespace App\Http\Controllers;

use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class RoleController extends Controller
{
    /**
     * Display a listing of the roles (web).
     */
    public function index()
    {
        return view('roles.index');
    }

    /**
     * API: Get paginated roles for datatable.
     */
    public function getRoles(Request $request)
    {
        $query = Role::query();

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('guard_name', 'like', "%{$search}%");
            });
        }

        $allowedSortColumns = ['name', 'guard_name', 'created_at', 'updated_at'];
        $sortColumn = $request->get('sortColumn', 'created_at');
        $sortDirection = $request->get('sortDirection', 'desc');

        if (!in_array($sortColumn, $allowedSortColumns)) {
            $sortColumn = 'created_at';
        }
        if (!in_array(strtolower($sortDirection), ['asc', 'desc'])) {
            $sortDirection = 'desc';
        }

        $query->orderBy($sortColumn, $sortDirection);
        $limit = intval($request->get('limit', 10));
        $roles = $query->paginate($limit);

        return response()->json([
            'data' => $roles->items(),
            'recordsTotal' => $roles->total(),
            'recordsFiltered' => $roles->total(),
            'draw' => intval($request->get('draw', 1)),
        ]);
    }

    /**
     * API: Get all permissions.
     */
    public function getPermissions()
    {
        return response()->json(Permission::all());
    }

    /**
     * API: Get a role with its permission IDs for editing.
     */
    public function getRolePermissions($roleId)
    {
        $role = Role::with('permissions')->findOrFail($roleId);

        // Log the permissions for debugging
        Log::info('Role permissions for role ID ' . $roleId, [
            'role_id' => $role->id,
            'role_name' => $role->name,
            'permission_ids' => $role->permissions->pluck('id')->toArray(),
            'permission_names' => $role->permissions->pluck('name')->toArray(),
        ]);

        return response()->json([
            'id' => $role->id,
            'name' => $role->name,
            'guard_name' => $role->guard_name,
            // Return only IDs for easier v-model binding
            'permissions' => $role->permissions->pluck('id')->toArray(),
        ]);
    }

    /**
     * API: Store a new role.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:roles,name',
            'guard_name' => 'required|string|max:255',
            'permissions' => 'nullable|array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        $role = Role::create([
            'name' => $validated['name'],
            'guard_name' => $validated['guard_name'],
        ]);

        if (!empty($validated['permissions'])) {
            $role->syncPermissions($validated['permissions']);
        }

        // Return JSON for API usage
        return response()->json(['message' => 'Role created successfully!']);
    }

    /**
     * API: Update a role.
     */
    public function update(Request $request, Role $role)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:roles,name,' . $role->id,
            'guard_name' => 'required|string|max:255',
            'permissions' => 'nullable|array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        $role->update([
            'name' => $validated['name'],
            'guard_name' => $validated['guard_name'],
        ]);

        if (isset($validated['permissions'])) {
            $role->syncPermissions($validated['permissions']);
        }

        // Return JSON for API usage
        return response()->json(['message' => 'Role updated successfully!']);
    }

    /**
     * API: Delete a role.
     */
    public function destroy(Role $role)
    {
        // Ensure the role is not assigned to any users before deletion
        if (method_exists($role, 'users') && $role->users->count() > 0) {
            return response()->json(['error' => 'Role is assigned to users and cannot be deleted.'], 400);
        }

        $role->delete();
        return response()->json(['message' => 'Role deleted successfully!']);
    }

    // --- Web methods for Blade views (optional) ---

    public function getRoleNames()
    {
        return response()->json(Role::pluck('name'));
    }

    public function create()
    {
        $permissions = Permission::all();
        return view('roles.create', compact('permissions'));
    }

    public function edit(Role $role)
    {
        $permissions = Permission::all();
        return view('roles.edit', compact('role', 'permissions'));
    }
}
