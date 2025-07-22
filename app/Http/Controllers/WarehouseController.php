<?php

namespace App\Http\Controllers;

use App\Models\Warehouse;
use App\Models\Building;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class WarehouseController extends Controller
{
    /**
     * Display the warehouses index view.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        $this->authorize('viewAny', Warehouse::class);
        return view('Inventory.warehouse.index');
    }

    /**
     * Retrieve paginated warehouses with optional search and sort.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getWarehouses(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Warehouse::class);

        $query = Warehouse::query()->with('building');

        // Handle search
        if ($search = $request->input('search')) {
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
        $sortColumn = $request->input('sortColumn', 'created_at');
        $sortDirection = strtolower($request->input('sortDirection', 'desc'));

        $sortColumn = in_array($sortColumn, $allowedSortColumns) ? $sortColumn : 'created_at';
        $sortDirection = in_array($sortDirection, ['asc', 'desc']) ? $sortDirection : 'desc';

        $query->orderBy($sortColumn, $sortDirection);

        // Handle pagination
        $limit = max(1, (int) $request->input('limit', 10));
        $warehouses = $query->paginate($limit);

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
                'created_by_name' => $warehouse->created_by ? User::find($warehouse->created_by)->name : null,
                'updated_at' => $warehouse->updated_at?->toDateTimeString(),
                'updated_by' => $warehouse->updated_by,
                'deleted_at' => $warehouse->deleted_at?->toDateTimeString(),
            ];
        });

        return response()->json([
            'data' => $data,
            'recordsTotal' => $warehouses->total(),
            'recordsFiltered' => $warehouses->total(),
            'draw' => (int) $request->input('draw', 1),
        ]);
    }

    /**
     * Retrieve trashed warehouses with optional search and sort.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getTrashedWarehouses(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Warehouse::class);

        $query = Warehouse::onlyTrashed()->with('building');

        // Handle search
        if ($search = $request->input('search')) {
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
        $allowedSortColumns = ['code', 'name', 'khmer_name', 'address', 'address_khmer', 'is_active', 'created_at', 'building_id', 'created_by', 'updated_by', 'deleted_at'];
        $sortColumn = $request->input('sortColumn', 'deleted_at');
        $sortDirection = strtolower($request->input('sortDirection', 'desc'));

        $sortColumn = in_array($sortColumn, $allowedSortColumns) ? $sortColumn : 'deleted_at';
        $sortDirection = in_array($sortDirection, ['asc', 'desc']) ? $sortDirection : 'desc';

        $query->orderBy($sortColumn, $sortDirection);

        // Handle pagination
        $limit = max(1, (int) $request->input('limit', 10));
        $warehouses = $query->paginate($limit);

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
                'building_name' => $warehouse->building ? 
                    '(' . $warehouse->building->code . ')' . ' - ' . $warehouse->building->name : null,
                'created_at' => $warehouse->created_at?->toDateTimeString(),
                'created_by' => $warehouse->created_by,
                'updated_at' => $warehouse->updated_at?->toDateTimeString(),
                'updated_by' => $warehouse->updated_by,
                'deleted_at' => $warehouse->deleted_at?->toDateTimeString(),
            ];
        });

        return response()->json([
            'data' => $data,
            'recordsTotal' => $warehouses->total(),
            'recordsFiltered' => $warehouses->total(),
            'draw' => (int) $request->input('draw', 1),
        ]);
    }

    /**
     * Get validation rules for warehouse creation/update.
     *
     * @param int|null $warehouseId
     * @return array
     */
    private function warehouseValidationRules(?int $warehouseId = null): array
    {
        $rules = [
            'name' => [
                'required',
                'string',
                'max:255',
                'unique:warehouses,name' . ($warehouseId ? ',' . $warehouseId : ''),
            ],
            'khmer_name' => 'nullable|string|max:255',
            'address' => 'required|string|max:255',
            'address_khmer' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
            'building_id' => 'required|integer|exists:buildings,id',
            'is_active' => 'integer',
        ];

        // For updates, include code validation if provided
        if ($warehouseId) {
            $rules['code'] = [
                'required',
                'string',
                'max:255',
                'unique:warehouses,code,' . $warehouseId,
            ];
        }

        return $rules;
    }

    /**
     * Generate a unique warehouse code in the format WH-001, WH-002, etc.
     *
     * @return string
     */
    private function generateWarehouseCode(): string
    {
        $lastWarehouse = Warehouse::orderBy('id', 'desc')->first();
        $nextNumber = $lastWarehouse ? (int) str_replace('WH-', '', $lastWarehouse->code) + 1 : 1;
        $newCode = 'WH-' . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);

        // Ensure the generated code is unique
        while (Warehouse::where('code', $newCode)->exists()) {
            $nextNumber++;
            $newCode = 'WH-' . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
        }

        return $newCode;
    }

    /**
     * Store a new warehouse.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Warehouse::class);
        $validated = Validator::make($request->all(), $this->warehouseValidationRules())->validate();

        DB::beginTransaction();
        try {
            $warehouse = Warehouse::create([
                'code' => $this->generateWarehouseCode(),
                'name' => $validated['name'],
                'khmer_name' => $validated['khmer_name'],
                'address' => $validated['address'],
                'address_khmer' => $validated['address_khmer'],
                'description' => $validated['description'],
                'building_id' => $validated['building_id'],
                'is_active' => $validated['is_active'] ?? 1,
                'created_by' => Auth::id(),
            ]);
            DB::commit();

            return response()->json([
                'message' => 'Warehouse created successfully.',
                'data' => $warehouse
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to create warehouse',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a warehouse for editing.
     *
     * @param Warehouse $warehouse
     * @return JsonResponse
     */
    public function edit(Warehouse $warehouse): JsonResponse
    {
        $this->authorize('update', $warehouse);
        return response()->json([
            'data' => $warehouse
        ]);
    }

    /**
     * Update an existing warehouse.
     *
     * @param Request $request
     * @param Warehouse $warehouse
     * @return JsonResponse
     */
    public function update(Request $request, Warehouse $warehouse): JsonResponse
    {
        $this->authorize('update', $warehouse);
        $validated = Validator::make($request->all(), $this->warehouseValidationRules($warehouse->id))->validate();

        DB::beginTransaction();
        try {
            $warehouse->update([
                'code' => $validated['code'],
                'name' => $validated['name'],
                'khmer_name' => $validated['khmer_name'],
                'address' => $validated['address'],
                'address_khmer' => $validated['address_khmer'],
                'description' => $validated['description'],
                'building_id' => $validated['building_id'],
                'is_active' => $validated['is_active'] ?? 1,
                'updated_by' => Auth::id(),
            ]);
            DB::commit();

            return response()->json([
                'message' => 'Warehouse updated successfully.',
                'data' => $warehouse
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to update warehouse',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a warehouse (soft delete) after checking relationships.
     *
     * @param Warehouse $warehouse
     * @return JsonResponse
     */
    public function destroy(Warehouse $warehouse): JsonResponse
    {
        $this->authorize('delete', $warehouse);
        
        // Check for relationships (e.g., inventory items or transactions)
        $hasInventoryItems = $warehouse->inventoryItems()->exists();
        $hasTransactions = $warehouse->transactions()->exists();

        if ($hasInventoryItems || $hasTransactions) {
            return response()->json([
                'message' => 'Cannot delete warehouse because it has associated inventory items or transactions.'
            ], 400);
        }

        DB::beginTransaction();
        try {
            $warehouse->update(['deleted_by' => Auth::id()]);
            $warehouse->delete();
            DB::commit();
            return response()->json([
                'message' => 'Warehouse soft deleted successfully.'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to soft delete warehouse',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Restore a soft-deleted warehouse.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function restore($id): JsonResponse
    {
        $this->authorize('restore', Warehouse::class);

        $warehouse = Warehouse::onlyTrashed()->findOrFail($id);

        DB::beginTransaction();
        try {
            $warehouse->restore();
            $warehouse->update(['updated_by' => Auth::id()]);
            DB::commit();
            return response()->json([
                'message' => 'Warehouse restored successfully.',
                'data' => $warehouse
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to restore warehouse',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Permanently delete a soft-deleted warehouse after checking relationships.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function forceDelete($id): JsonResponse
    {
        $this->authorize('forceDelete', Warehouse::class);

        $warehouse = Warehouse::onlyTrashed()->findOrFail($id);

        // Check for relationships (e.g., inventory items or transactions)
        $hasInventoryItems = $warehouse->inventoryItems()->exists();
        $hasTransactions = $warehouse->transactions()->exists();

        if ($hasInventoryItems || $hasTransactions) {
            return response()->json([
                'message' => 'Cannot permanently delete warehouse because it has associated inventory items or transactions.'
            ], 400);
        }

        DB::beginTransaction();
        try {
            $warehouse->forceDelete();
            DB::commit();
            return response()->json([
                'message' => 'Warehouse permanently deleted successfully.'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to permanently delete warehouse',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}