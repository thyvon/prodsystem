<?php

namespace App\Http\Controllers;

use App\Models\UnitOfMeasure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\JsonResponse;

class UnitController extends Controller
{
    /**
     * Display the units index view.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        $this->authorize('viewAny', UnitOfMeasure::class);
        return view('Products.Unit.index');
    }

    /**
     * Retrieve paginated units with optional search and sort.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getUnitsOfMeasure(Request $request): JsonResponse
    {
        $this->authorize('viewAny', UnitOfMeasure::class);

        $query = UnitOfMeasure::query() ->with('parentUnit');

        // Handle search
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('short_name', 'like', "%{$search}%")
                    ->orWhereHas('parentUnit', function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%");
                    });
            });
        }

        // Handle sorting
        $allowedSortColumns = ['name', 'khmer_name', 'short_name', 'is_active', 'created_at', 'main_category_id'];
        $sortColumn = $request->input('sortColumn', 'created_at');
        $sortDirection = strtolower($request->input('sortDirection', 'desc'));

        $sortColumn = in_array($sortColumn, $allowedSortColumns) ? $sortColumn : 'created_at';
        $sortDirection = in_array($sortDirection, ['asc', 'desc']) ? $sortDirection : 'desc';

        $query->orderBy($sortColumn, $sortDirection);

        // Handle pagination
        $limit = max(1, (int) $request->input('limit', 10));
        $unitOfMeasures = $query->paginate($limit);

        $data = $unitOfMeasures->getCollection()->map(function (UnitOfMeasure $unitOfMeasure) {
            return [
                'id' => $unitOfMeasure->id,
                'name' => $unitOfMeasure->name,
                'khmer_name' => $unitOfMeasure->khmer_name,
                'short_name' => $unitOfMeasure->short_name,
                'operator' => $unitOfMeasure->operator,
                'conversion_factor' => $unitOfMeasure->conversion_factor,
                'description' => $unitOfMeasure->description,
                'is_active' => (bool) $unitOfMeasure->is_active,
                'parent_unit_id' => $unitOfMeasure->parent_unit_id,
                'parent_unit_name' => $unitOfMeasure->parentUnit ? $unitOfMeasure->parentUnit->name : null,
                'created_at' => $unitOfMeasure->created_at?->toDateTimeString(),
            ];
        });

        return response()->json([
            'data' => $data,
            'recordsTotal' => $unitOfMeasures->total(),
            'recordsFiltered' => $unitOfMeasures->total(),
            'draw' => (int) $request->input('draw', 1),
        ]);
    }

    /**
     * Get validation rules for unit creation/update.
     *
     * @param int|null $unitId
     * @return array
     */
    private function unitValidationRules(?int $unitId = null): array
    {
        return [
            'short_name' => [
                'required',
                'string',
                'max:255',
                'unique:unit_of_measures,short_name' . ($unitId ? ',' . $unitId : ''),
            ],
            'name' => 'required|string|max:255',
            'operator' => 'nullable|string|max:255',
            'conversion_factor' => 'nullable|numeric|min:0',
            'khmer_name' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
            'parent_unit_id' => 'nullable|integer|exists:unit_of_measures,id',
            'is_active' => 'integer',
        ];
    }

    /**
     * Store a new subcategory.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', UnitOfMeasure::class);
        // Ensure parent_unit_id is null if not provided
        $request->merge([
            'parent_unit_id' => $request->input('parent_unit_id') ?: null,
        ]);

        $validated = Validator::make($request->all(), $this->unitValidationRules())->validate();

        DB::beginTransaction();
        try {
            $unitOfMeasure = UnitOfMeasure::create([
                'short_name' => $validated['short_name'],
                'name' => $validated['name'],
                'khmer_name' => $validated['khmer_name'],
                'operator' => $validated['operator'],
                'conversion_factor' => $validated['conversion_factor'],
                'description' => $validated['description'],
                'parent_unit_id' => $validated['parent_unit_id'],
                'is_active' => $validated['is_active'] ?? 1,
            ]);
            DB::commit();

            return response()->json([
                'message' => 'Unit of Measure created successfully.',
                'data' => $unitOfMeasure
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to create unit of measure',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a unit of measure for editing.
     *
     * @param UnitOfMeasure $unitOfMeasure
     * @return JsonResponse
     */
    public function edit(UnitOfMeasure $unitOfMeasure): JsonResponse
    {
        $this->authorize('update', $unitOfMeasure);
        return response()->json([
            'data' => $unitOfMeasure
        ]);
    }

    /**
     * Update an existing unit of measure.
     *
     * @param Request $request
     * @param UnitOfMeasure $unitOfMeasure
     * @return JsonResponse
     */
    public function update(Request $request, UnitOfMeasure $unitOfMeasure): JsonResponse
    {
        $this->authorize('update', $unitOfMeasure);

        // Ensure parent_unit_id is null if not provided
        $request->merge([
            'parent_unit_id' => $request->input('parent_unit_id') ?: null,
        ]);

        $validated = Validator::make($request->all(), $this->unitValidationRules($unitOfMeasure->id))->validate();

        DB::beginTransaction();
        try {
            $unitOfMeasure->update([
                'short_name' => $validated['short_name'],
                'name' => $validated['name'],
                'khmer_name' => $validated['khmer_name'],
                'operator' => $validated['operator'],
                'conversion_factor' => $validated['conversion_factor'],
                'description' => $validated['description'],
                'parent_unit_id' => $validated['parent_unit_id'],
                'is_active' => $validated['is_active'] ?? 1,
            ]);
            DB::commit();

            return response()->json([
                'message' => 'Unit of Measure updated successfully.',
                'data' => $unitOfMeasure
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to update unit of measure',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a unit of measure.
     *
     * @param UnitOfMeasure $unitOfMeasure
     * @return JsonResponse
     */
    public function destroy(UnitOfMeasure $unitOfMeasure): JsonResponse
    {
        $this->authorize('delete', $unitOfMeasure);
        try {
            $unitOfMeasure->delete();
            return response()->json([
                'message' => 'Unit of Measure deleted successfully.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete unit of measure',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}