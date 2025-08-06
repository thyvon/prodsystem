<?php

namespace App\Services;

use App\Models\Department;
use Illuminate\Http\Request;

class DepartmentService
{
    public function getDepartments(Request $request)
    {
        try {
            $query = Department::query();

            if ($search = $request->input('search')) {
                $query->where('name', 'like', "%{$search}%");
            }

            $allowedSortColumns = ['name', 'created_at', 'updated_at'];
            $sortColumn = $request->input('sortColumn', 'created_at');
            $sortDirection = $request->input('sortDirection', 'desc');

            $sortColumn = in_array($sortColumn, $allowedSortColumns) ? $sortColumn : 'created_at';
            $sortDirection = in_array(strtolower($sortDirection), ['asc', 'desc']) ? $sortDirection : 'desc';

            $query->orderBy($sortColumn, $sortDirection);

            $limit = max(1, min(1000, intval($request->input('limit', 10))));
            $departments = $query->paginate($limit);

            $data = collect($departments->items())->map(function ($department) {
                return [
                    'id' => $department->id,
                    'name' => $department->name,
                    'created_at' => $department->created_at,
                    'updated_at' => $department->updated_at,
                ];
            });

            return response()->json([
                'data' => $data,
                'recordsTotal' => $departments->total(),
                'recordsFiltered' => $departments->total(),
                'draw' => intval($request->input('draw')),
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch departments.',
                'errors' => [$e->getMessage()],
            ], 500);
        }
    }
}