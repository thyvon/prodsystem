<?php

namespace App\Services;

use App\Models\Position;
use Illuminate\Http\Request;

class PositionService
{
    /**
     * Fetch positions with search, sort, and pagination capabilities.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPositions(Request $request)
    {
        try {
            $query = Position::query();

            // Search filter
            if ($search = $request->input('search')) {
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                      ->orWhere('short_title', 'like', "%{$search}%");
                });
            }

            // Sorting
            $allowedSortColumns = ['title', 'short_title', 'is_active', 'created_at', 'updated_at'];
            $sortColumn = $request->input('sortColumn', 'created_at');
            $sortDirection = $request->input('sortDirection', 'desc');

            $sortColumn = in_array($sortColumn, $allowedSortColumns) ? $sortColumn : 'created_at';
            $sortDirection = in_array(strtolower($sortDirection), ['asc', 'desc']) ? $sortDirection : 'desc';

            $query->orderBy($sortColumn, $sortDirection);

            // Pagination
            $limit = max(1, min(1000, intval($request->input('limit', 10))));
            $positions = $query->paginate($limit);

            // Transform data to include necessary fields
            $data = collect($positions->items())->map(function ($position) {
                return [
                    'id' => $position->id,
                    'title' => $position->title,
                    'short_title' => $position->short_title,
                    'is_active' => $position->is_active,
                    'created_at' => $position->created_at,
                    'updated_at' => $position->updated_at,
                ];
            });

            return response()->json([
                'data' => $data,
                'recordsTotal' => $positions->total(),
                'recordsFiltered' => $positions->total(),
                'draw' => intval($request->input('draw')),
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch positions.',
                'errors' => [$e->getMessage()],
            ], 500);
        }
    }
}