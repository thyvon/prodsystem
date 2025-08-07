<?php

namespace App\Services;

use App\Models\Building;
use Illuminate\Http\Request;

class BuildingService
{
    public function getBuildings(Request $request)
    {
        try {
            $query = Building::query();

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
            $buildings = $query->paginate($limit);

            $data = collect($buildings->items())->map(function ($building) {
                return [
                    'id' => $building->id,
                    'name' => $building->name,
                    'short_name' => $building->short_name,
                    'created_at' => $building->created_at,
                    'updated_at' => $building->updated_at,
                ];
            });

            return response()->json([
                'data' => $data,
                'recordsTotal' => $buildings->total(),
                'recordsFiltered' => $buildings->total(),
                'draw' => intval($request->input('draw')),
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch buildings.',
                'errors' => [$e->getMessage()],
            ], 500);
        }
    }
}