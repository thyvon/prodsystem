<?php

namespace App\Http\Controllers;

use App\Models\MainStockBeginning;
use App\Models\StockBeginning;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\JsonResponse;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\StockBeginningsExport;
use App\Imports\StockBeginningsImport;
use Illuminate\Support\Facades\Log;

class StockBeginningController extends Controller
{
    // Constants for sort columns and default values
    private const ALLOWED_SORT_COLUMNS = ['reference_no', 'beginning_date', 'created_at', 'updated_at', 'created_by', 'updated_by'];
    private const DEFAULT_SORT_COLUMN = 'created_at';
    private const DEFAULT_SORT_DIRECTION = 'desc';
    private const DEFAULT_LIMIT = 10;
    private const MAX_LIMIT = 1000;
    private const DATE_FORMAT = 'Y-m-d';

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
        return view('Inventory.stockBeginning.form');
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

        $validated = Validator::make($request->all(), array_merge(
            $this->mainStockBeginningValidationRules(),
            $this->stockBeginningValidationRules()
        ))->validate();

        try {
            return DB::transaction(function () use ($validated) {
                $referenceNo = $this->generateReferenceNo($validated['warehouse_id'], $validated['beginning_date']);

                $mainStockBeginning = MainStockBeginning::create([
                    'reference_no' => $referenceNo,
                    'warehouse_id' => $validated['warehouse_id'],
                    'beginning_date' => $validated['beginning_date'],
                    'created_by' => auth()->id() ?? 1,
                ]);

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

                StockBeginning::insert($items);

                return response()->json([
                    'message' => 'Stock beginning created successfully.',
                    'data' => $mainStockBeginning->load('stockBeginnings'),
                ], 201);
            });
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create stock beginning', ['error' => $e->getMessage()]);
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

        try {
            // Load related data
            $mainStockBeginning->load([
                'stockBeginnings.productVariant.product.unit',
                'warehouse.building.campus',
            ]);

            // Prepare data for the Vue form
            $stockBeginningData = [
                'id' => $mainStockBeginning->id,
                'reference_no' => $mainStockBeginning->reference_no,
                'warehouse_id' => $mainStockBeginning->warehouse_id,
                'beginning_date' => $mainStockBeginning->beginning_date,
                'items' => $mainStockBeginning->stockBeginnings->map(function ($item) {
                    return [
                        'id' => $item->id, // Include id for frontend
                        'product_id' => $item->product_id,
                        'quantity' => $item->quantity,
                        'unit_price' => $item->unit_price,
                        'total_value' => $item->total_value,
                        'remarks' => $item->remarks,
                        'item_code' => $item->productVariant->item_code ?? null,
                        'product_name' => $item->productVariant->product->name ?? null,
                        'product_khmer_name' => $item->productVariant->product->khmer_name ?? null,
                        'unit_name' => $item->productVariant->product->unit->name ?? null,
                    ];
                })->toArray(),
                'warehouse' => $mainStockBeginning->warehouse ? [
                    'id' => $mainStockBeginning->warehouse->id,
                    'name' => $mainStockBeginning->warehouse->name,
                    'building' => $mainStockBeginning->warehouse->building ? [
                        'id' => $mainStockBeginning->warehouse->building->id,
                        'short_name' => $mainStockBeginning->warehouse->building->short_name,
                        'campus' => $mainStockBeginning->warehouse->building->campus ? [
                            'id' => $mainStockBeginning->warehouse->building->campus->id,
                            'short_name' => $mainStockBeginning->warehouse->building->campus->short_name,
                        ] : null,
                    ] : null,
                ] : null,
            ];

            return view('Inventory.stockBeginning.form', [
                'mainStockBeginning' => $mainStockBeginning,
                'stockBeginningData' => $stockBeginningData,
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching stock beginning for editing', [
                'error_message' => $e->getMessage(),
                'stock_beginning_id' => $mainStockBeginning->id,
            ]);
            return response()->view('errors.500', [
                'message' => 'Failed to fetch stock beginning',
                'error' => $e->getMessage(),
            ], 500);
        }
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

        $validated = Validator::make($request->all(), array_merge(
            $this->mainStockBeginningValidationRules($mainStockBeginning->id),
            $this->stockBeginningValidationRules()
        ))->validate();

        try {
            return DB::transaction(function () use ($validated, $mainStockBeginning) {
                // Update the main stock beginning header
                $mainStockBeginning->update([
                    'warehouse_id' => $validated['warehouse_id'],
                    'beginning_date' => $validated['beginning_date'],
                    'updated_by' => auth()->id() ?? 1,
                ]);

                // Get existing line item IDs
                $existingItemIds = $mainStockBeginning->stockBeginnings->pluck('id')->toArray();
                $submittedItemIds = array_filter(array_column($validated['items'], 'id'), fn($id) => !is_null($id));

                // Delete line items not included in the request
                StockBeginning::where('main_form_id', $mainStockBeginning->id)
                    ->whereNotIn('id', $submittedItemIds)
                    ->each(function ($stockBeginning) {
                        $stockBeginning->deleted_by = auth()->id() ?? 1;
                        $stockBeginning->save();
                        $stockBeginning->delete();
                    });

                // Process each item: update existing or create new
                $itemsToInsert = [];
                foreach ($validated['items'] as $item) {
                    if (!empty($item['id']) && in_array($item['id'], $existingItemIds)) {
                        // Update existing item
                        $stockBeginning = StockBeginning::find($item['id']);
                        if ($stockBeginning) {
                            $stockBeginning->update([
                                'product_id' => $item['product_id'],
                                'quantity' => $item['quantity'],
                                'unit_price' => $item['unit_price'],
                                'total_value' => $item['quantity'] * $item['unit_price'],
                                'remarks' => $item['remarks'] ?? null,
                                'updated_by' => auth()->id() ?? 1,
                            ]);
                        }
                    } else {
                        // Prepare new item for insertion
                        $itemsToInsert[] = [
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
                    }
                }

                // Insert new items if any
                if (!empty($itemsToInsert)) {
                    StockBeginning::insert($itemsToInsert);
                }

                return response()->json([
                    'message' => 'Stock beginning updated successfully.',
                    'data' => $mainStockBeginning->load('stockBeginnings'),
                ]);
            });
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update stock beginning', ['error' => $e->getMessage()]);
            return response()->json([
                'message' => 'Failed to update stock beginning',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Retrieve paginated main stock beginnings with optional search and sort.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getStockBeginnings(Request $request): JsonResponse
    {
        $this->authorize('viewAny', MainStockBeginning::class);

        $validated = $request->validate([
            'search' => 'nullable|string|max:255',
            'sortColumn' => 'nullable|string|in:' . implode(',', self::ALLOWED_SORT_COLUMNS),
            'sortDirection' => 'nullable|string|in:asc,desc',
            'limit' => 'nullable|integer|min:1|max:' . self::MAX_LIMIT,
            'draw' => 'nullable|integer',
        ]);

        $query = MainStockBeginning::with(['warehouse', 'stockBeginnings.productVariant.product'])
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
                                ->orWhereHas('unit', function ($q3) use ($search) {
                                    $q3->where('name', 'like', "%{$search}%");
                                });
                        });
                    });
            });

        $recordsTotal = MainStockBeginning::count();
        $recordsFiltered = $query->count();

        $sortColumn = in_array($validated['sortColumn'] ?? self::DEFAULT_SORT_COLUMN, self::ALLOWED_SORT_COLUMNS)
            ? $validated['sortColumn']
            : self::DEFAULT_SORT_COLUMN;
        $sortDirection = in_array(strtolower($validated['sortDirection'] ?? self::DEFAULT_SORT_DIRECTION), ['asc', 'desc'])
            ? $validated['sortDirection']
            : self::DEFAULT_SORT_DIRECTION;

        $query->orderBy($sortColumn, $sortDirection);

        $limit = max(1, min((int) ($validated['limit'] ?? self::DEFAULT_LIMIT), self::MAX_LIMIT));
        $mainStockBeginnings = $query->paginate($limit, ['*'], 'page', $validated['page'] ?? 1);
        $totalQuantity = round($mainStockBeginnings->sum(function ($mainStockBeginning) {
            return $mainStockBeginning->stockBeginnings->sum('quantity');
        }), 4);
        $totalValue = round(
            $mainStockBeginnings->sum(function ($mainStockBeginning) {
                return $mainStockBeginning->stockBeginnings->sum('total_value');
            }),
            4
        );

        $data = $mainStockBeginnings->getCollection()->map(function ($mainStockBeginning) {
            return [
                'id' => $mainStockBeginning->id,
                'reference_no' => $mainStockBeginning->reference_no,
                'beginning_date' => $mainStockBeginning->beginning_date ?? null,
                'warehouse_name' => $mainStockBeginning->warehouse->name ?? null,
                'campus_name' => $mainStockBeginning->warehouse->building->campus->short_name ?? null,
                'building_name' => $mainStockBeginning->warehouse->building->short_name ?? null,
                'quantity' => $mainStockBeginning->stockBeginnings->sum('quantity'),
                'total_value' => $mainStockBeginning->stockBeginnings->sum('total_value'),
                'created_at' => $mainStockBeginning->created_at?->toDateTimeString(),
                'updated_at' => $mainStockBeginning->updated_at?->toDateTimeString(),
                'created_by' => $mainStockBeginning->createdBy->name ?? 'System',
                'updated_by' => $mainStockBeginning->updatedBy->name ?? 'System',
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
     * Import stock beginnings from an Excel file and return data for form population.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function import(Request $request): JsonResponse
    {
        $this->authorize('create', MainStockBeginning::class);

        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:2048',
        ]);

        try {
            $import = new StockBeginningsImport();
            Excel::import($import, $request->file('file'));

            $data = $import->getData();

            if (!empty($data['errors'])) {
                return response()->json([
                    'message' => 'Errors found in Excel file.',
                    'errors' => $data['errors'],
                ], 422);
            }

            if (empty($data['items'])) {
                return response()->json([
                    'message' => 'No valid data found in the Excel file.',
                    'errors' => ['No valid rows processed.'],
                ], 422);
            }

            return response()->json([
                'message' => 'Stock beginnings data parsed successfully.',
                'data' => [
                    'items' => $data['items'],
                ],
            ], 200);
        } catch (\Maatwebsite\Excel\Validators\ValidationException $e) {
            $errors = $e->failures()->map(function ($failure) {
                $row = $failure->row();
                $errorMessages = $failure->errors();
                return "Row {$row}: " . implode('; ', $errorMessages);
            })->toArray();

            return response()->json([
                'message' => 'Validation failed during import',
                'errors' => $errors,
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to parse stock beginnings',
                'errors' => [$e->getMessage()],
            ], 500);
        }
    }

    /**
     * Export stock beginnings to an Excel file.
     *
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function export(Request $request)
    {
        $this->authorize('viewAny', MainStockBeginning::class);

        $validated = $request->validate([
            'search' => 'nullable|string|max:255',
            'sortColumn' => 'nullable|string|in:' . implode(',', self::ALLOWED_SORT_COLUMNS),
            'sortDirection' => 'nullable|string|in:asc,desc',
        ]);

        $query = MainStockBeginning::with(['warehouse', 'stockBeginnings.productVariant.product'])
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
                                ->orWhereHas('unit', function ($q3) use ($search) {
                                    $q3->where('name', 'like', "%{$search}%");
                                });
                        });
                    });
            });

        $sortColumn = in_array($validated['sortColumn'] ?? self::DEFAULT_SORT_COLUMN, self::ALLOWED_SORT_COLUMNS)
            ? $validated['sortColumn']
            : self::DEFAULT_SORT_COLUMN;
        $sortDirection = in_array(strtolower($validated['sortDirection'] ?? self::DEFAULT_SORT_DIRECTION), ['asc', 'desc'])
            ? $validated['sortDirection']
            : self::DEFAULT_SORT_DIRECTION;

        $query->orderBy($sortColumn, $sortDirection);

        return Excel::download(new StockBeginningsExport($query), 'stock_beginnings_' . now()->format('Ymd_His') . '.xlsx');
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
            'beginning_date' => ['required', 'date', 'date_format:' . self::DATE_FORMAT],
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
            'items.*.id' => ['nullable', 'integer', 'exists:stock_beginnings,id'],
            'items.*.product_id' => ['required', 'integer', 'exists:product_variants,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.remarks' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Generate a unique reference number in format STB-short_name-mmyyyy-sequence.
     *
     * @param int $warehouseId
     * @param string $beginningDate
     * @return string
     * @throws \InvalidArgumentException If the date format is invalid or warehouse is not found.
     */
    private function generateReferenceNo(int $warehouseId, string $beginningDate): string
    {
        $warehouse = Warehouse::with('building.campus')->findOrFail($warehouseId);

        try {
            $date = \Carbon\Carbon::createFromFormat(self::DATE_FORMAT, $beginningDate);
            if (!$date || $date->format(self::DATE_FORMAT) !== $beginningDate) {
                throw new \InvalidArgumentException('Invalid date format. Expected ' . self::DATE_FORMAT . '.');
            }
        } catch (\Exception $e) {
            throw new \InvalidArgumentException('Invalid date format. Expected ' . self::DATE_FORMAT . '.', 0, $e);
        }

        $shortName = $warehouse->building?->campus?->short_name ?? 'WH';
        $monthYear = $date->format('my');

        $sequence = $this->getSequenceNumber($warehouseId, $monthYear);

        return "STB-{$shortName}-{$monthYear}-{$sequence}";
    }

    /**
     * Generate a sequence number for uniqueness, including soft-deleted records.
     *
     * @param int $warehouseId
     * @param string $monthYear
     * @return string
     */
    private function getSequenceNumber(int $warehouseId, string $monthYear): string
    {
        $count = MainStockBeginning::withTrashed()
            ->where('warehouse_id', $warehouseId)
            ->where('reference_no', 'like', "STB%{$monthYear}%")
            ->count();

        return str_pad($count + 1, 2, '0', STR_PAD_LEFT);
    }

    /**
     * Delete a main stock beginning and its associated line items.
     *
     * @param MainStockBeginning $mainStockBeginning
     * @return JsonResponse
     */
    public function destroy(MainStockBeginning $mainStockBeginning): JsonResponse
    {
        $this->authorize('delete', $mainStockBeginning);

        try {
            DB::transaction(function () use ($mainStockBeginning) {

                $userId = auth()->id() ?? 1;
                foreach ($mainStockBeginning->stockBeginnings as $stockBeginning) {
                    $stockBeginning->deleted_by = $userId;
                    $stockBeginning->save();
                }
                $mainStockBeginning->deleted_by = $userId;
                $mainStockBeginning->save();

                $mainStockBeginning->stockBeginnings()->delete();
                $mainStockBeginning->delete();
            });

            return response()->json([
                'message' => 'Stock beginning deleted successfully.',
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to delete stock beginning', ['error' => $e->getMessage(), 'id' => $mainStockBeginning->id]);
            return response()->json([
                'message' => 'Failed to delete stock beginning',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}