<?php

namespace App\Services;

use App\Models\Department;
use Illuminate\Http\Request;

class DepartmentService
{
    public function getDepartments(Request $request): array
    {
        try {
            $limit = max(1, min(1000, intval($request->input('limit', 10))));
            $departments = Department::paginate($limit);

            $data = $departments->map(fn($dept) => [
                'id'         => $dept->id,
                'name'       => $dept->name,
                'short_name' => $dept->short_name,
                'is_active'  => $dept->is_active,
            ]);

            return [
                'data'             => $data,
                'recordsTotal'     => $departments->total(),
                'recordsFiltered'  => $departments->total(),
                'draw'             => intval($request->input('draw', 1)),
            ];
        } catch (\Exception $e) {
            return [
                'message' => 'Failed to fetch departments.',
                'errors'  => [$e->getMessage()],
            ];
        }
    }
}
