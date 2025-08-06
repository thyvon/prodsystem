<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;

class UserService
{
    public function getUsers(Request $request)
    {
        try {
            $query = User::query()->with(['roles', 'departments', 'campus', 'positions']);

            if ($search = $request->input('search')) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            }

            $allowedSortColumns = ['name', 'email', 'created_at', 'updated_at'];
            $sortColumn = $request->input('sortColumn', 'created_at');
            $sortDirection = $request->input('sortDirection', 'desc');

            $sortColumn = in_array($sortColumn, $allowedSortColumns) ? $sortColumn : 'created_at';
            $sortDirection = in_array(strtolower($sortDirection), ['asc', 'desc']) ? $sortDirection : 'desc';

            $query->orderBy($sortColumn, $sortDirection);

            $limit = max(1, min(1000, intval($request->input('limit', 10))));
            $users = $query->paginate($limit);

            $data = collect($users->items())->map(function ($user) {
                $user->role = $user->roles->pluck('name')->implode(', ');
                $user->default_department = $user->defaultDepartment() ? $user->defaultDepartment()->name : null;
                $user->default_campus = $user->defaultCampus() ? $user->defaultCampus()->name : null;
                $user->default_position = $user->defaultPosition() ? $user->defaultPosition()->title : null;
                return $user;
            });

            return response()->json([
                'data' => $data,
                'recordsTotal' => $users->total(),
                'recordsFiltered' => $users->total(),
                'draw' => intval($request->input('draw')),
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch users.',
                'errors' => [$e->getMessage()],
            ], 500);
        }
    }
}
?>