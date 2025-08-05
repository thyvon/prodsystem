<?php

namespace App\Services;

use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class WarehouseService
{
    /**
     * Fetch warehouses with search, sorting, and pagination.
     *
     * @param Request $request
     * @return array
     */
    public function getWarehouses(Request $request): array
    {
        // Validate request input
        $validated = $request->validate([
            'search' => 'nullable|string|max:255',
            'sortColumn' => 'nullable|string|in:code,name,khmer_name,address,address_khmer,is_active,created_at,building_id,created_by,updated_by',
            'sortDirection' => 'nullable|string|in:asc,desc',
            'limit' => 'nullable|integer|min:1|max:100',
            'draw' => 'nullable|integer',
        ]);

        // Build the query
        $query = Warehouse::query()->with(['building']);

        // Handle search
        if ($search = $validated['search'] ?? $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('khmer_name', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%")
                    ->orWhere('address_khmer', 'like', "%{$search}%")
                    ->orWhereHas('building', function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%");
                    });
            });
        }

        // Handle sorting
        $allowedSortColumns = ['code', 'name', 'khmer_name', 'address', 'address_khmer', 'is_active', 'created_at', 'building_id', 'created_by', 'updated_by'];
        $sortColumn = $validated['sortColumn'] ?? $request->input('sortColumn', 'created_at');
        $sortDirection = $validated['sortDirection'] ?? $request->input('sortDirection', 'desc');

        $sortColumn = in_array($sortColumn, $allowedSortColumns) ? $sortColumn : 'created_at';
        $sortDirection = in_array(strtolower($sortDirection), ['asc', 'desc']) ? $sortDirection : 'desc';

        $query->orderBy($sortColumn, $sortDirection);

        // Handle pagination
        $limit = max(1, min(100, (int) ($validated['limit'] ?? $request->input('limit', 10))));
        $warehouses = $query->paginate($limit);

        // Transform the data
        $data = $warehouses->getCollection()->map(function (Warehouse $warehouse) {
            return [
                'id' => $warehouse->id,
                'code' => $warehouse->code,
                'name' => $warehouse->name,
                'khmer_name' => $warehouse->khmer_name,
                'address' => $warehouse->address,
                'address_khmer' => $warehouse->address_khmer,
                'description' => $warehouse->description,
                'is_active' => (bool) $warehouse->is_active,
                'building_id' => $warehouse->building_id,
                'building_name' => $warehouse->building ? $warehouse->building->short_name : null,
                'created_at' => $warehouse->created_at?->toDateTimeString(),
                'created_by' => $warehouse->created_by,
                'created_by_name' => $warehouse->createdBy ? $warehouse->createdBy->name : null,
                'updated_at' => $warehouse->updated_at?->toDateTimeString(),
                'updated_by' => $warehouse->updated_by,
                'deleted_at' => $warehouse->deleted_at?->toDateTimeString(),
            ];
        });

        return [
            'data' => $data->all(),
            'recordsTotal' => $warehouses->total(),
            'recordsFiltered' => $warehouses->total(),
            'draw' => (int) ($validated['draw'] ?? $request->input('draw', 1)),
        ];
    }
}