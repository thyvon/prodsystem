<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;

class PermissionController extends Controller
{
    // Web: Blade view shell
    public function index()
    {
        return view('permissions.index');
    }

    // API: Get paginated permissions for datatable
    public function getPermissions(Request $request)
    {
        $query = Permission::query();

        if ($search = $request->get('search')) {
            $query->where('name', 'like', "%{$search}%")
                  ->orWhere('guard_name', 'like', "%{$search}%");
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
        $permissions = $query->paginate($limit);

        return response()->json([
            'data' => $permissions->items(),
            'recordsTotal' => $permissions->total(),
            'recordsFiltered' => $permissions->total(),
            'draw' => intval($request->get('draw', 1)),
        ]);
    }

    // API: Get a single permission for editing
    public function show(Permission $permission)
    {
        return response()->json($permission);
    }

    // API: Store a new permission
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:permissions,name',
            'guard_name' => 'required|string|max:255',
        ]);

        $permission = Permission::create($validated);

        return response()->json(['message' => 'Permission created successfully!', 'permission' => $permission]);
    }

    // API: Update a permission
    public function update(Request $request, Permission $permission)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:permissions,name,' . $permission->id,
            'guard_name' => 'required|string|max:255',
        ]);

        $permission->update($validated);

        return response()->json(['message' => 'Permission updated successfully!', 'permission' => $permission]);
    }

    // API: Delete a permission
    public function destroy(Permission $permission)
    {
        $permission->delete();
        return response()->json(['message' => 'Permission deleted successfully.']);
    }
}