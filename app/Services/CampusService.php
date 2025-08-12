<?php

namespace App\Services;

use App\Models\Campus;
use Illuminate\Http\Request;

class CampusService
{
    /**
     * Fetch campuses with search, sorting, and pagination.
     *
     * @param Request $request
     * @return array
     */
    public function getCampuses(Request $request): array
    {
        // Validate request input
        $validated = $request->validate([
            'search' => 'nullable|string|max:255',
            'sortColumn' => 'nullable|string|in:name,created_at,updated_at',
            'sortDirection' => 'nullable|string|in:asc,desc',
            'limit' => 'nullable|integer|min:1|max:100',
            'draw' => 'nullable|integer',
        ]);

        // Build the query
        $query = Campus::query();

        // Handle search
        if ($search = $validated['search'] ?? $request->input('search')) {
            $query->where('name', 'like', "%{$search}%");
        }

        // Handle sorting
        $allowedSortColumns = ['name', 'created_at', 'updated_at'];
        $sortColumn = $validated['sortColumn'] ?? $request->input('sortColumn', 'created_at');
        $sortDirection = $validated['sortDirection'] ?? $request->input('sortDirection', 'desc');

        $sortColumn = in_array($sortColumn, $allowedSortColumns) ? $sortColumn : 'created_at';
        $sortDirection = in_array(strtolower($sortDirection), ['asc', 'desc']) ? $sortDirection : 'desc';

        $query->orderBy($sortColumn, $sortDirection);

        // Handle pagination
        $limit = max(1, min(100, (int) ($validated['limit'] ?? $request->input('limit', 10))));
        $campuses = $query->paginate($limit);

        // Transform the data
        $data = $campuses->getCollection()->map(function (Campus $campus) {
            return [
                'id' => $campus->id,
                'name' => $campus->name,
                'created_at' => $campus->created_at?->toDateTimeString(),
                'updated_at' => $campus->updated_at?->toDateTimeString(),
            ];
        });

        return [
            'data' => $data->all(),
            'recordsTotal' => $campuses->total(),
            'recordsFiltered' => $campuses->total(),
            'draw' => (int) ($validated['draw'] ?? $request->input('draw', 1)),
        ];
    }
}
