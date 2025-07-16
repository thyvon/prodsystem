<?php

namespace App\Http\Controllers;

use App\Models\MainCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\JsonResponse;

class MainCategoryController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', MainCategory::class);
        return view('Products.category.maincategory');
    }
    public function getMainCategories(Request $request): JsonResponse
    {
        $this->authorize('viewAny', MainCategory::class);

        $query = MainCategory::query();

        // Handle search
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('khmer_name', 'like', "%{$search}%")
                    ->orWhere('short_name', 'like', "%{$search}%");
            });
        }

        // Handle sorting
        $allowedSortColumns = ['name', 'khmer_name', 'short_name', 'is_active', 'created_at'];
        $sortColumn = $request->input('sortColumn', 'created_at');
        $sortDirection = strtolower($request->input('sortDirection', 'desc'));

        $sortColumn = in_array($sortColumn, $allowedSortColumns) ? $sortColumn : 'created_at';
        $sortDirection = in_array($sortDirection, ['asc', 'desc']) ? $sortDirection : 'desc';

        $query->orderBy($sortColumn, $sortDirection);

        // Handle pagination
        $limit = max(1, (int) $request->input('limit', 10));
        $mainCategories = $query->paginate($limit);

        $data = $mainCategories->getCollection()->map(function (MainCategory $mainCategory) {
            return [
                'id' => $mainCategory->id,
                'name' => $mainCategory->name,
                'short_name' => $mainCategory->short_name,
                'khmer_name' => $mainCategory->khmer_name,
                'description' => $mainCategory->description,
                'is_active' => (bool) $mainCategory->is_active,
                'created_at' => $mainCategory->created_at?->toDateTimeString(),
            ];
        });

        return response()->json([
            'data' => $data,
            'recordsTotal' => $mainCategories->total(),
            'recordsFiltered' => $mainCategories->total(),
            'draw' => (int) $request->input('draw', 1),
        ]);
    }
    private function mainCategoryValidationRules(?int $mainCategoryId = null): array
    {
        return [
            'short_name' => [
                'required',
                'string',
                'max:255',
                'unique:main_categories,short_name' . ($mainCategoryId ? ',' . $mainCategoryId : ''),
            ],
            'name' => 'required|string|max:255',
            'khmer_name' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
            'is_active' => 'integer',
        ];
    }
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', MainCategory::class);
        $validated = Validator::make($request->all(), $this->mainCategoryValidationRules())->validate();

        DB::beginTransaction();
        try {
            $mainCategory = MainCategory::create([
                'short_name' => $validated['short_name'],
                'name' => $validated['name'],
                'khmer_name' => $validated['khmer_name'],
                'description' => $validated['description'],
                'is_active' => $validated['is_active'] ?? 1,
            ]);
            DB::commit();

            return response()->json([
                'message' => 'Main Category created successfully.',
                'data' => $mainCategory
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to create main category',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function edit(MainCategory $mainCategory): JsonResponse
    {
        $this->authorize('update', $mainCategory);
        return response()->json([
            'data' => $mainCategory
        ]);
    }
    public function update(Request $request, MainCategory $mainCategory): JsonResponse
    {
        $this->authorize('update', $mainCategory);
        $validated = Validator::make($request->all(), $this->mainCategoryValidationRules($mainCategory->id))->validate();

        DB::beginTransaction();
        try {
            $mainCategory->update([
                'short_name' => $validated['short_name'],
                'name' => $validated['name'],
                'khmer_name' => $validated['khmer_name'],
                'description' => $validated['description'],
                'is_active' => $validated['is_active'] ?? 1,
            ]);
            DB::commit();

            return response()->json([
                'message' => 'Main Category updated successfully.',
                'data' => $mainCategory
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to update main category',
                'error' => $e->getMessage()
            ], 500);
        }
    }
public function destroy(MainCategory $mainCategory): JsonResponse
{
    $this->authorize('delete', $mainCategory);

    try {
        $mainCategory->delete();

        return response()->json([
            'message' => 'Main Category deleted successfully.'
        ]);
    } catch (\Illuminate\Database\QueryException $e) {
        // Check if error is due to foreign key constraint
        if ($e->getCode() === '23000') {
            return response()->json([
                'message' => 'Cannot delete this Main Category because it is being used by one or more Sub Categories.'
            ], 409); // Conflict status
        }

        // Other query exceptions
        return response()->json([
            'message' => 'Failed to delete main category',
            'error' => $e->getMessage()
        ], 500);
    } catch (\Exception $e) {
        // General exception
        return response()->json([
            'message' => 'An unexpected error occurred while deleting the main category.',
            'error' => $e->getMessage()
        ], 500);
    }
}

}
