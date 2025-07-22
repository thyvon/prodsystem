<?php

namespace App\Http\Controllers;

use App\Models\MainStockBeginning;
use App\Models\StockBeginning;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Http\JsonResponse;

class StockBeginningController extends Controller
{
    /**
     * Display the stock beginnings index view.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        $this->authorize('viewAny', MainStockBeginning::class);
        return view('Inventory.stockBeginning.index');
    }

    /**
     * Show the form for creating a new main stock beginning.
     *
     * @return \Illuminate\View\View
     */
    public function create()
    {
        $this->authorize('create', MainStockBeginning::class);
        return view('Inventory.stockBeginning.create');
    }

    /**
     * Store a new main stock beginning with its line items.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', MainStockBeginning::class);

        // Validate the incoming request
        $validated = Validator::make($request->all(), array_merge(
            $this->mainStockBeginningValidationRules(),
            $this->stockBeginningValidationRules()
        ))->validate();

        try {
            return DB::transaction(function () use ($validated) {
                // Create a record in main_stock_beginnings
                $mainStockBeginning = MainStockBeginning::create([
                    'reference_no' => $validated['reference_no'],
                    'warehouse_id' => $validated['warehouse_id'],
                    'beginning_date' => $validated['beginning_date'],
                    'created_by' => auth()->id() ?? 1,
                ]);

                // Prepare line items for bulk insert
                $items = array_map(function ($item) use ($mainStockBeginning) {
                    return [
                        'main_form_id' => $mainStockBeginning->id,
                        'product_id' => $item['product_id'],
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['unit_price'],
                        'total_value' => $item['quantity'] * $item['unit_price'],
                        'remarks' => $item['remarks'] ?? null,
                        'created_by' => auth()->id() ?? 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }, $validated['items']);

                // Bulk insert line items into stock_beginnings
                StockBeginning::insert($items);

                return response()->json([
                    'message' => 'Stock beginning created successfully.',
                    'data' => $mainStockBeginning->load('stockBeginnings'),
                ], 201);
            });
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to create stock beginning',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Show the form for editing an existing main stock beginning.
     *
     * @param MainStockBeginning $mainStockBeginning
     * @return \Illuminate\View\View
     */
    public function edit(MainStockBeginning $mainStockBeginning)
    {
        $this->authorize('update', $mainStockBeginning);
        return view('Inventory.stockBeginning.edit', compact('mainStockBeginning'));
    }

    /**
     * Update an existing main stock beginning and its line items.
     *
     * @param Request $request
     * @param MainStockBeginning $mainStockBeginning
     * @return JsonResponse
     */
    public function update(Request $request, MainStockBeginning $mainStockBeginning): JsonResponse
    {
        $this->authorize('update', $mainStockBeginning);

        // Validate the incoming request
        $validated = Validator::make($request->all(), array_merge(
            $this->mainStockBeginningValidationRules($mainStockBeginning->id),
            $this->stockBeginningValidationRules()
        ))->validate();

        try {
            return DB::transaction(function () use ($validated, $mainStockBeginning) {
                // Update main_stock_beginnings record
                $mainStockBeginning->update([
                    'reference_no' => $validated['reference_no'],
                    'warehouse_id' => $validated['warehouse_id'],
                    'beginning_date' => $validated['beginning_date'],
                    'updated_by' => auth()->id() ?? 1,
                ]);

                // Delete existing line items
                $mainStockBeginning->stockBeginnings()->delete();

                // Prepare new line items for bulk insert
                $items = array_map(function ($item) use ($mainStockBeginning) {
                    return [
                        'main_form_id' => $mainStockBeginning->id,
                        'product_id' => $item['product_id'],
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['unit_price'],
                        'total_value' => $item['quantity'] * $item['unit_price'],
                        'remarks' => $item['remarks'] ?? null,
                        'created_by' => auth()->id() ?? 1,
                        'updated_by' => auth()->id() ?? 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }, $validated['items']);

                // Bulk insert new line items
                StockBeginning::insert($items);

                return response()->json([
                    'message' => 'Stock beginning updated successfully.',
                    'data' => $mainStockBeginning->load('stockBeginnings'),
                ]);
            });
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to update stock beginning',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Retrieve paginated main stock beginnings with optional search, sort, and trashed filter.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getStockBeginnings(Request $request): JsonResponse
    {
        $this->authorize('viewAny', MainStockBeginning::class);

        $validated = $request->validate([
            'search' => 'nullable|string|max:255',
            'sortColumn' => 'nullable|string|in:reference_no,beginning_date,created_at,updated_at,deleted_at',
            'sortDirection' => 'nullable|string|in:asc,desc',
            'limit' => 'nullable|integer|min:1|max:100',
            'draw' => 'nullable|integer',
            'withTrashed' => 'nullable',
            'page' => 'nullable|integer|min:1'
        ]);

        $query = MainStockBeginning::with(['warehouse', 'stockBeginnings.productVariant.product'])
            ->when($validated['withTrashed'] ?? false, function ($query) {
                return $query->withTrashed();
            })
            ->when($validated['search'] ?? null, function ($query, $search) {
                $query->where('reference_no', 'like', "%{$search}%")
                    ->orWhereHas('warehouse', function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('stockBeginnings.productVariant.product', function ($q) use ($search) {
                        $q->where(function ($q2) use ($search) {
                            $q2->where('name', 'like', "%{$search}%")
                                ->orWhere('khmer_name', 'like', "%{$search}%")
                                ->orWhere('description', 'like', "%{$search}%")
                                ->orWhere('item_code', 'like', "%{$search}%")
                                ->orWhereHas('category', function ($q3) use ($search) {
                                    $q3->where('name', 'like', "%{$search}%");
                                })
                                ->orWhereHas('subCategory', function ($q3) use ($search) {
                                    $q3->where('name', 'like', "%{$search}%");
                                })
                                ->orWhereHas('unit', function ($q3) use ($search) {
                                    $q3->where('name', 'like', "%{$search}%");
                                });
                        });
                    });
            });

        $recordsTotal = MainStockBeginning::count();
        $recordsFiltered = $query->count();

        $sortColumn = $validated['sortColumn'] ?? 'created_at';
        $sortDirection = $validated['sortDirection'] ?? 'desc';
        $allowedSortColumns = ['reference_no', 'beginning_date', 'created_at', 'updated_at', 'deleted_at'];
        $sortColumn = in_array($sortColumn, $allowedSortColumns) ? $sortColumn : 'created_at';
        $sortDirection = in_array(strtolower($sortDirection), ['asc', 'desc']) ? $sortDirection : 'desc';

        $query->orderBy($sortColumn, $sortDirection);

        $limit = max(1, min(100, (int) ($validated['limit'] ?? 10)));
        $mainStockBeginnings = $query->paginate($limit, ['*'], 'page', $validated['page'] ?? 1);

        $data = $mainStockBeginnings->getCollection()->map(function ($mainStockBeginning) {
            return [
                'id' => $mainStockBeginning->id,
                'reference_no' => $mainStockBeginning->reference_no,
                'beginning_date' => $mainStockBeginning->beginning_date ?? null,
                'warehouse_name' => $mainStockBeginning->warehouse->name ?? null,
                'created_at' => $mainStockBeginning->created_at?->toDateTimeString(),
                'updated_at' => $mainStockBeginning->updated_at?->toDateTimeString(),
                'deleted_at' => $mainStockBeginning->deleted_at?->toDateTimeString(),
                'items' => $mainStockBeginning->stockBeginnings->map(function ($stockBeginning) {
                    return [
                        'id' => $stockBeginning->id,
                        'product_id' => $stockBeginning->product_id,
                        'item_code' => $stockBeginning->productVariant->item_code ?? null,
                        'quantity' => $stockBeginning->quantity,
                        'unit_price' => $stockBeginning->unit_price,
                        'total_value' => $stockBeginning->total_value,
                        'remarks' => $stockBeginning->remarks,
                        'product_name' => $stockBeginning->productVariant->product->name ?? null,
                        'product_khmer_name' => $stockBeginning->productVariant->product->khmer_name ?? null,
                        'category_name' => $stockBeginning->productVariant->product->category->name ?? null,
                        'sub_category_name' => $stockBeginning->productVariant->product->subCategory->name ?? null,
                        'unit_name' => $stockBeginning->productVariant->product->unit->name ?? null,
                    ];
                })->toArray(),
            ];
        });

        return response()->json([
            'data' => $data->all(),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'draw' => (int) ($validated['draw'] ?? 1),
        ]);
    }

    /**
     * Get validation rules for main stock beginning creation/update.
     *
     * @param int|null $mainStockBeginningId
     * @return array
     */
    private function mainStockBeginningValidationRules(?int $mainStockBeginningId = null): array
    {
        return [
            'beginning_date' => ['required', 'date', 'date_format:Y-m-d'],
            'reference_no' => [
                'required',
                'string',
                'max:255',
                Rule::unique('main_stock_beginnings', 'reference_no')->ignore($mainStockBeginningId),
            ],
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
        ];
    }

    /**
     * Get validation rules for stock beginning line items.
     *
     * @return array
     */
    private function stockBeginningValidationRules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:product_variants,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.remarks' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Soft delete a main stock beginning and its line items.
     *
     * @param MainStockBeginning $mainStockBeginning
     * @return JsonResponse
     */
    public function destroy(MainStockBeginning $mainStockBeginning): JsonResponse
    {
        $this->authorize('delete', $mainStockBeginning);

        try {
            return DB::transaction(function () use ($mainStockBeginning) {
                $mainStockBeginning->update(['deleted_by' => auth()->id() ?? 1]);
                $mainStockBeginning->stockBeginnings()->update(['deleted_by' => auth()->id() ?? 1]);
                $mainStockBeginning->stockBeginnings()->delete();
                $mainStockBeginning->delete();

                return response()->json([
                    'message' => 'Stock beginning soft deleted successfully.',
                ]);
            });
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to soft delete stock beginning',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Restore a soft-deleted main stock beginning and its line items.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function restore($id): JsonResponse
    {
        $this->authorize('restore', MainStockBeginning::class);

        try {
            return DB::transaction(function () use ($id) {
                $mainStockBeginning = MainStockBeginning::onlyTrashed()->findOrFail($id);
                $mainStockBeginning->update(['deleted_by' => null]);
                $mainStockBeginning->stockBeginnings()->onlyTrashed()->update(['deleted_by' => null]);
                $mainStockBeginning->stockBeginnings()->onlyTrashed()->restore();
                $mainStockBeginning->restore();

                return response()->json([
                    'message' => 'Stock beginning restored successfully.',
                    'data' => $mainStockBeginning->load('stockBeginnings'),
                ]);
            });
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to restore stock beginning',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get trashed main stock beginnings.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getTrashed(Request $request): JsonResponse
    {
        $this->authorize('viewAny', MainStockBeginning::class);

        $validated = $request->validate([
            'search' => 'nullable|string|max:255',
            'sortColumn' => 'nullable|string|in:reference_no,beginning_date,created_at,updated_at,deleted_at',
            'sortDirection' => 'nullable|string|in:asc,desc',
            'limit' => 'nullable|integer|min:1|max:100',
            'draw' => 'nullable|integer',
            'page' => 'nullable|integer|min:1',
        ]);

        $query = MainStockBeginning::onlyTrashed()
            ->with(['warehouse', 'stockBeginnings.productVariant.product'])
            ->when($validated['search'] ?? null, function ($query, $search) {
                $query->where('reference_no', 'like', "%{$search}%")
                    ->orWhereHas('warehouse', function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('stockBeginnings.productVariant.product', function ($q) use ($search) {
                        $q->where(function ($q2) use ($search) {
                            $q2->where('name', 'like', "%{$search}%")
                                ->orWhere('khmer_name', 'like', "%{$search}%")
                                ->orWhere('description', 'like', "%{$search}%")
                                ->orWhere('item_code', 'like', "%{$search}%")
                                ->orWhereHas('category', function ($q3) use ($search) {
                                    $q3->where('name', 'like', "%{$search}%");
                                })
                                ->orWhereHas('subCategory', function ($q3) use ($search) {
                                    $q3->where('name', 'like', "%{$search}%");
                                })
                                ->orWhereHas('unit', function ($q3) use ($search) {
                                    $q3->where('name', 'like', "%{$search}%");
                                });
                        });
                    });
            });

        $recordsTotal = MainStockBeginning::onlyTrashed()->count();
        $recordsFiltered = $query->count();

        $sortColumn = $validated['sortColumn'] ?? 'deleted_at';
        $sortDirection = $validated['sortDirection'] ?? 'desc';
        $allowedSortColumns = ['reference_no', 'beginning_date', 'created_at', 'updated_at', 'deleted_at'];
        $sortColumn = in_array($sortColumn, $allowedSortColumns) ? $sortColumn : 'deleted_at';
        $sortDirection = in_array(strtolower($sortDirection), ['asc', 'desc']) ? $sortDirection : 'desc';

        $query->orderBy($sortColumn, $sortDirection);

        $limit = max(1, min(100, (int) ($validated['limit'] ?? 10)));
        $mainStockBeginnings = $query->paginate($limit, ['*'], 'page', $validated['page'] ?? 1);

        $data = $mainStockBeginnings->getCollection()->map(function ($mainStockBeginning) {
            return [
                'id' => $mainStockBeginning->id,
                'reference_no' => $mainStockBeginning->reference_no,
                'beginning_date' => $mainStockBeginning->beginning_date?->toDateString(),
                'warehouse_name' => $mainStockBeginning->warehouse->name ?? null,
                'created_at' => $mainStockBeginning->created_at?->toDateTimeString(),
                'updated_at' => $mainStockBeginning->updated_at?->toDateTimeString(),
                'deleted_at' => $mainStockBeginning->deleted_at?->toDateTimeString(),
                'items' => $mainStockBeginning->stockBeginnings->map(function ($stockBeginning) {
                    return [
                        'id' => $stockBeginning->id,
                        'product_id' => $stockBeginning->product_id,
                        'item_code' => $stockBeginning->productVariant->item_code ?? null,
                        'quantity' => $stockBeginning->quantity,
                        'unit_price' => $stockBeginning->unit_price,
                        'total_value' => $stockBeginning->total_value,
                        'remarks' => $stockBeginning->remarks,
                        'product_name' => $stockBeginning->productVariant->product->name ?? null,
                        'product_khmer_name' => $stockBeginning->productVariant->product->khmer_name ?? null,
                        'category_name' => $stockBeginning->productVariant->product->category->name ?? null,
                        'sub_category_name' => $stockBeginning->productVariant->product->subCategory->name ?? null,
                        'unit_name' => $stockBeginning->productVariant->product->unit->name ?? null,
                    ];
                })->toArray(),
            ];
        });

        return response()->json([
            'data' => $data->all(),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'draw' => (int) ($validated['draw'] ?? 1),
        ]);
    }
}