<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;

class UserService
{
    public function getUsers(Request $request)
    {
        try {
            $query = User::query()->with([
                'roles',
                'defaultDepartment',
                'defaultCampus',
                'defaultPosition'
            ]);

            if ($search = $request->input('search')) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
                });
            }

            $allowedSortColumns = ['name', 'email', 'created_at', 'updated_at', 'is_active', 'default_department', 'default_position', 'default_campus', 'role'];
            $sortColumn = $request->input('sortColumn', 'created_at');
            $sortDirection = $request->input('sortDirection', 'desc');

            $sortColumn = in_array($sortColumn, $allowedSortColumns) ? $sortColumn : 'created_at';
            $sortDirection = in_array(strtolower($sortDirection), ['asc', 'desc']) ? $sortDirection : 'desc';

            $query->orderBy($sortColumn, $sortDirection);

            $limit = max(1, min(1000, intval($request->input('limit', 10))));
            $users = $query->paginate($limit);

            $data = collect($users->items())->map(function ($user) {
                return [
                    'id' => $user->id,
                    'profile_url' => $user->profile_url,
                    'name' => $user->name,
                    'default_department' => $user->defaultDepartment?->short_name,
                    'default_position' => $user->defaultPosition?->title,
                    'default_campus' => $user->defaultCampus?->short_name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'role' => $user->roles->pluck('name')->implode(', '),
                    'is_active' => $user->is_active,
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at,
                ];
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