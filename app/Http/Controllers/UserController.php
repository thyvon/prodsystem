<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
        // web: return the Blade + Vue “shell”
    public function index()
    {
        return view('users.index');
    }

    public function getUsers(Request $request)
    {
        $query = User::query()->with('roles'); // eager load roles

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Remove 'role' from allowedSortColumns since it's not a column
        $allowedSortColumns = ['name', 'email', 'created_at', 'updated_at'];
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
        $users = $query->paginate($limit);

        // Attach the first role name (or all roles if you want) to each user
        $data = collect($users->items())->map(function ($user) {
            $user->role = $user->roles->pluck('name')->implode(', '); // or just ->first() if you want one role
            return $user;
        });

        return response()->json([
            'data' => $data,
            'recordsTotal' => $users->total(),
            'recordsFiltered' => $users->total(),
            'draw' => intval($request->get('draw')),
        ]);
    }

    public function assignRole(Request $request, $userId)
    {
        $request->validate([
            'roles' => 'required|array',
            'roles.*' => 'string|exists:roles,name',
        ]);

        $user = User::findOrFail($userId);
        $roleNames = $request->input('roles');

        // Assign multiple roles (replaces all previous roles)
        $user->syncRoles($roleNames);

        return response()->json([
            'message' => "Roles assigned to user successfully.",
            'user' => $user->load('roles'),
        ]);
    }
}
