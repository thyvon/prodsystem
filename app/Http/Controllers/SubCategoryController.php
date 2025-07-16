<?php

namespace App\Http\Controllers;

use App\Models\SubCategory;
use App\Models\MainCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\JsonResponse;

class SubCategoryController extends Controller
{
    /**
     * Display the subcategories index view.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        $this->authorize('viewAny', SubCategory::class);
        return view('Products.category.subcategory');
    }

    /**
     * Retrieve paginated subcategories with optional search and sort.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getSubCategories(Request $request): JsonResponse
    {
        $this->authorize('viewAny', SubCategory::class);

        $query = SubCategory::query()->with('mainCategory');

        // Handle search
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('short_name', 'like', "%{$search}%")
                    ->orWhereHas('mainCategory', function ($q) use ($search) {
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
        $subCategories = $query->paginate($limit);

        $data = $subCategories->getCollection()->map(function (SubCategory $subCategory) {
            return [
                'id' => $subCategory->id,
                'name' => $subCategory->name,
                'khmer_name' => $subCategory->khmer_name,
                'short_name' => $subCategory->short_name,
                'description' => $subCategory->description,
                'is_active' => (bool) $subCategory->is_active,
                'main_category_id' => $subCategory->main_category_id,
                'main_category_name' => $subCategory->mainCategory ? 
                '(' . $subCategory->mainCategory->short_name . ')' . ' - ' . $subCategory->mainCategory->name : null,
                'created_at' => $subCategory->created_at?->toDateTimeString(),
            ];
        });

        return response()->json([
            'data' => $data,
            'recordsTotal' => $subCategories->total(),
            'recordsFiltered' => $subCategories->total(),
            'draw' => (int) $request->input('draw', 1),
        ]);
    }

    /**
     * Get validation rules for subcategory creation/update.
     *
     * @param int|null $subCategoryId
     * @return array
     */
    private function subCategoryValidationRules(?int $subCategoryId = null): array
    {
        return [
            'short_name' => [
                'required',
                'string',
                'max:255',
                'unique:sub_categories,short_name' . ($subCategoryId ? ',' . $subCategoryId : ''),
            ],
            'name' => 'required|string|max:255',
            'khmer_name' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
            'main_category_id' => 'required|integer|exists:main_categories,id',
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
        $this->authorize('create', SubCategory::class);
        $validated = Validator::make($request->all(), $this->subCategoryValidationRules())->validate();

        DB::beginTransaction();
        try {
            $subCategory = SubCategory::create([
                'short_name' => $validated['short_name'],
                'name' => $validated['name'],
                'khmer_name' => $validated['khmer_name'],
                'description' => $validated['description'],
                'main_category_id' => $validated['main_category_id'],
                'is_active' => $validated['is_active'] ?? 1,
            ]);
            DB::commit();

            return response()->json([
                'message' => 'Subcategory created successfully.',
                'data' => $subCategory
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to create subcategory',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a subcategory for editing.
     *
     * @param SubCategory $subCategory
     * @return JsonResponse
     */
    public function edit(SubCategory $subCategory): JsonResponse
    {
        $this->authorize('update', $subCategory);
        return response()->json([
            'data' => $subCategory
        ]);
    }

    /**
     * Update an existing subcategory.
     *
     * @param Request $request
     * @param SubCategory $subCategory
     * @return JsonResponse
     */
    public function update(Request $request, SubCategory $subCategory): JsonResponse
    {
        $this->authorize('update', $subCategory);
        $validated = Validator::make($request->all(), $this->subCategoryValidationRules($subCategory->id))->validate();

        DB::beginTransaction();
        try {
            $subCategory->update([
                'short_name' => $validated['short_name'],
                'name' => $validated['name'],
                'khmer_name' => $validated['khmer_name'],
                'description' => $validated['description'],
                'main_category_id' => $validated['main_category_id'],
                'is_active' => $validated['is_active'] ?? 1,
            ]);
            DB::commit();

            return response()->json([
                'message' => 'Subcategory updated successfully.',
                'data' => $subCategory
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to update subcategory',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a subcategory.
     *
     * @param SubCategory $subCategory
     * @return JsonResponse
     */
    public function destroy(SubCategory $subCategory): JsonResponse
    {
        $this->authorize('delete', $subCategory);
        try {
            $subCategory->delete();
            return response()->json([
                'message' => 'Subcategory deleted successfully.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete subcategory',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}