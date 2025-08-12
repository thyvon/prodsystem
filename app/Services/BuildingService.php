<?php

namespace App\Services;

use App\Models\Building;
use Illuminate\Http\Request;

class BuildingService
{
    /**
     * Fetch buildings with search, sorting, and pagination.
     *
     * @param Request $request
     * @return array
     */
    public function getBuildings(Request $request): array
    {
        // Validate request input
        $validated = $request->validate([
            'search' => 'nullable|string|max:255',
            'sortColumn' => 'nullable|string|in:name,short_name,address,is_active,created_at,campus_id',
            'sortDirection' => 'nullable|string|in:asc,desc',
            'limit' => 'nullable|integer|min:1|max:100',
            'draw' => 'nullable|integer',
        ]);

        // Build the query
        $query = Building::query()->with(['campus']);

        // Handle search
        if ($search = $validated['search'] ?? $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('short_name', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%")
                    ->orWhereHas('campus', function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%");
                    });
            });
        }

        // Handle sorting
        $allowedSortColumns = ['name', 'short_name', 'address', 'is_active', 'created_at', 'campus_id'];
        $sortColumn = $validated['sortColumn'] ?? $request->input('sortColumn', 'created_at');
        $sortDirection = $validated['sortDirection'] ?? $request->input('sortDirection', 'desc');

        $sortColumn = in_array($sortColumn, $allowedSortColumns) ? $sortColumn : 'created_at';
        $sortDirection = in_array(strtolower($sortDirection), ['asc', 'desc']) ? $sortDirection : 'desc';

        $query->orderBy($sortColumn, $sortDirection);

        // Handle pagination
        $limit = max(1, min(100, (int) ($validated['limit'] ?? $request->input('limit', 10))));
        $buildings = $query->paginate($limit);

        // Transform the data
        $data = $buildings->getCollection()->map(function (Building $building) {
            return [
                'id' => $building->id,
                'short_name' => $building->short_name,
                'name' => $building->name,
                'address' => $building->address,
                'is_active' => (bool) $building->is_active,
                'campus_id' => $building->campus_id,
                'campus_name' => $building->campus ? $building->campus->name : null,
                'created_at' => $building->created_at?->toDateTimeString(),
            ];
        });

        return [
            'data' => $data->all(),
            'recordsTotal' => $buildings->total(),
            'recordsFiltered' => $buildings->total(),
            'draw' => (int) ($validated['draw'] ?? $request->input('draw', 1)),
        ];
    }
}