<?php

namespace App\Services;

use App\Models\Campus;
use Illuminate\Http\Request;

class CampusService
{
    public function getCampuses(Request $request)
    {
        try {
            $query = Campus::query();

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
            $campuses = $query->paginate($limit);

            $data = collect($campuses->items())->map(function ($campus) {
                return [
                    'id' => $campus->id,
                    'name' => $campus->name,
                    'created_at' => $campus->created_at,
                    'updated_at' => $campus->updated_at,
                ];
            });

            return response()->json([
                'data' => $data,
                'recordsTotal' => $campuses->total(),
                'recordsFiltered' => $campuses->total(),
                'draw' => intval($request->input('draw')),
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch campuses.',
                'errors' => [$e->getMessage()],
            ], 500);
        }
    }
}