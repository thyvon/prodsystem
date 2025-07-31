<?php

namespace App\Http\Controllers;

use App\Models\MainStockBeginning;
use App\Models\StockBeginning;
use App\Models\Warehouse;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\JsonResponse;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\StockBeginningsExport;
use App\Imports\StockBeginningsImport;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Barryvdh\DomPDF\Facade\Pdf;

class StockBeginningController extends Controller
{
    // Constants for sort columns and default values
    private const ALLOWED_SORT_COLUMNS = ['reference_no', 'beginning_date', 'created_at', 'updated_at', 'created_by', 'updated_by'];
    private const DEFAULT_SORT_COLUMN = 'created_at';
    private const DEFAULT_SORT_DIRECTION = 'desc';
    private const DEFAULT_LIMIT = 10;
    private const MAX_LIMIT = 1000;
    private const DATE_FORMAT = 'Y-m-d';

    protected $approvalController;

    public function __construct(ApprovalController $approvalController)
    {
        $this->approvalController = $approvalController;
    }

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
     * Display a single main stock beginning with its line items and approvals for printing.
     *
     * @param MainStockBeginning $mainStockBeginning
     * @return \Illuminate\View\View
     */
    public function show(MainStockBeginning $mainStockBeginning)
    {
        $this->authorize('view', $mainStockBeginning);

        try {
            // Load related data including approvals and responders
            $mainStockBeginning->load([
                'stockBeginnings.productVariant.product.unit',
                'warehouse.building.campus',
                'createdBy',
                'updatedBy',
                'approvals.responder',
                'responders'
            ]);

            return view('Inventory.stockBeginning.show', [
                'mainStockBeginning' => $mainStockBeginning,
                'totalQuantity' => round($mainStockBeginning->stockBeginnings->sum('quantity'), 4),
                'totalValue' => round($mainStockBeginning->stockBeginnings->sum('total_value'), 4),
                'approvals' => $mainStockBeginning->approvals->map(function ($approval) {
                    return [
                        'id' => $approval->id,
                        'request_type' => $approval->request_type,
                        'approval_status' => $approval->approval_status,
                        'responder_name' => $approval->responder->name ?? 'N/A',
                        'comment' => $approval->comment,
                        'created_at' => $approval->created_at?->toDateTimeString(),
                        'updated_at' => $approval->updated_at?->toDateTimeString(),
                    ];
                })->toArray(),
                'responders' => $mainStockBeginning->responders->map(function ($responder) {
                    return [
                        'id' => $responder->pivot->id,
                        'user_id' => $responder->id,
                        'request_type' => $responder->pivot->request_type,
                        'name' => $responder->name,
                    ];
                })->toArray(),
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching stock beginning for display', [
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
     * Generate a PDF for a single main stock beginning.
     *
     * @param MainStockBeginning $mainStockBeginning
     * @return \Illuminate\View\View
     */
    public function generatePdf(MainStockBeginning $mainStockBeginning)
    {
        $this->authorize('view', $mainStockBeginning);

        try {
            // Load related data
            $mainStockBeginning->load([
                'stockBeginnings.productVariant.product.unit',
                'warehouse.building.campus',
                'createdBy',
                'updatedBy',
                'approvals.responder',
                'responders'
            ]);

            // Clean old temp files (older than 15 minutes)
            foreach (glob(public_path('temp/temp_*.pdf')) as $file) {
                if (filemtime($file) < now()->subMinutes(15)->getTimestamp()) {
                    unlink($file);
                }
            }

            // Prepare data for PDF
            $data = [
                'mainStockBeginning' => $mainStockBeginning,
                'totalQuantity' => round($mainStockBeginning->stockBeginnings->sum('quantity'), 4),
                'totalValue' => round($mainStockBeginning->stockBeginnings->sum('total_value'), 4),
                'approvals' => $mainStockBeginning->approvals->map(function ($approval) {
                    return [
                        'id' => $approval->id,
                        'request_type' => $approval->request_type,
                        'approval_status' => $approval->approval_status,
                        'responder_name' => $approval->responder->name ?? 'N/A',
                        'comment' => $approval->comment,
                        'created_at' => $approval->created_at?->toDateTimeString(),
                        'updated_at' => $approval->updated_at?->toDateTimeString(),
                    ];
                })->toArray(),
                'responders' => $mainStockBeginning->responders->map(function ($responder) {
                    return [
                        'id' => $responder->pivot->id,
                        'user_id' => $responder->id,
                        'request_type' => $responder->pivot->request_type,
                        'name' => $responder->name,
                    ];
                })->toArray(),
            ];

            // Generate PDF
            $pdf = Pdf::loadView('Inventory.stockBeginning.pdf', $data);

            // Generate unique filename with timestamp
            $filename = 'temp_' . time() . '_' . Str::random(6) . '.pdf';
            $filePath = public_path('temp/' . $filename);

            // Save PDF file to public/temp
            $pdf->save($filePath);

            // Return the PDF viewer view
            return view('pdf.pdfviewer', ['pdfFile' => "temp/$filename"]);
        } catch (\Exception $e) {
            Log::error('Error generating stock beginning PDF', [
                'error_message' => $e->getMessage(),
                'stock_beginning_id' => $mainStockBeginning->id,
            ]);
            return response()->view('errors.500', [
                'message' => 'Failed to generate PDF',
                'error' => $e->getMessage(),
            ], 500);
        }
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
            $this->stockBeginningValidationRules(),
            [
                'responders' => 'required|array|min:1',
                'responders.*.user_id' => 'required|exists:users,id',
                'responders.*.request_type' => 'required|string|in:review,check,approve',
            ]
        ))->validate();

        // Validate that each responder has the appropriate permission
        foreach ($validated['responders'] as $responder) {
            $user = User::findOrFail($responder['user_id']);
            $permission = "mainStockBeginning.{$responder['request_type']}";
            if (!$user->hasPermissionTo($permission)) {
                return response()->json([
                    'message' => "User ID {$responder['user_id']} does not have permission for {$responder['request_type']}.",
                ], 403);
            }
        }

        try {
            return DB::transaction(function () use ($validated) {
                $referenceNo = $this->generateReferenceNo($validated['warehouse_id'], $validated['beginning_date']);

                $mainStockBeginning = MainStockBeginning::create([
                    'reference_no' => $referenceNo,
                    'warehouse_id' => $validated['warehouse_id'],
                    'beginning_date' => $validated['beginning_date'],
                    'created_by' => auth()->id() ?? 1,
                    'status' => 'pending',
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

                $this->assignResponders($mainStockBeginning, $validated['responders']);
                $this->initializeApprovals($mainStockBeginning);

                return response()->json([
                    'message' => 'Stock beginning created successfully.',
                    'data' => $mainStockBeginning->load('stockBeginnings', 'approvals', 'responders'),
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
            // Load related data including approvals and responders
            $mainStockBeginning->load([
                'stockBeginnings.productVariant.product.unit',
                'warehouse.building.campus',
                'approvals.responder',
                'responders'
            ]);

            // Prepare data for the Vue form
            $stockBeginningData = [
                'id' => $mainStockBeginning->id,
                'reference_no' => $mainStockBeginning->reference_no,
                'warehouse_id' => $mainStockBeginning->warehouse_id,
                'beginning_date' => $mainStockBeginning->beginning_date,
                'items' => $mainStockBeginning->stockBeginnings->map(function ($item) {
                    return [
                        'id' => $item->id,
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
                'responders' => $mainStockBeginning->responders->map(function ($responder) {
                    return [
                        'id' => $responder->pivot->id,
                        'user_id' => $responder->id,
                        'request_type' => $responder->pivot->request_type,
                    ];
                })->toArray(),
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
            $this->stockBeginningValidationRules(),
            [
                'responders' => 'required|array|min:1',
                'responders.*.user_id' => 'required|exists:users,id',
                'responders.*.request_type' => 'required|string|in:review,check,approve',
            ]
        ))->validate();

        // Validate that each responder has the appropriate permission
        foreach ($validated['responders'] as $responder) {
            $user = User::findOrFail($responder['user_id']);
            $permission = "mainStockBeginning.{$responder['request_type']}";
            if (!$user->hasPermissionTo($permission)) {
                return response()->json([
                    'message' => "User ID {$responder['user_id']} does not have permission for {$responder['request_type']}.",
                ], 403);
            }
        }

        try {
            return DB::transaction(function () use ($validated, $mainStockBeginning) {
                // Update the main stock beginning header
                $mainStockBeginning->update([
                    'warehouse_id' => $validated['warehouse_id'],
                    'beginning_date' => $validated['beginning_date'],
                    'updated_by' => auth()->id() ?? 1,
                ]);

                // Update responders
                $this->assignResponders($mainStockBeginning, $validated['responders']);

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
                    'data' => $mainStockBeginning->load('stockBeginnings', 'approvals', 'responders'),
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
     * Retrieve users with specific approval permissions for a request type.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getUsersForApproval(Request $request): JsonResponse
    {
        $this->authorize('create', MainStockBeginning::class);

        $validated = $request->validate([
            'request_type' => 'required|string|in:review,check,approve',
        ]);

        try {
            $permission = "mainStockBeginning.{$validated['request_type']}";
            $users = User::whereHas('permissions', function ($query) use ($permission) {
                $query->where('name', $permission);
            })->select('id', 'name')->get();

            return response()->json([
                'message' => 'Users fetched successfully.',
                'data' => $users,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Failed to fetch users for approval', [
                'error_message' => $e->getMessage(),
                'request_type' => $validated['request_type'],
            ]);
            return response()->json([
                'message' => 'Failed to fetch users for approval',
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

        $data = $mainStockBeginnings->getCollection()->map(function ($mainStockBeginning) {
            return [
                'id' => $mainStockBeginning->id,
                'reference_no' => $mainStockBeginning->reference_no,
                'beginning_date' => $mainStockBeginning->beginning_date ?? null,
                'warehouse_name' => $mainStockBeginning->warehouse->name ?? null,
                'campus_name' => $mainStockBeginning->warehouse->building->campus->short_name ?? null,
                'building_name' => $mainStockBeginning->warehouse->building->short_name ?? null,
                'quantity' => round($mainStockBeginning->stockBeginnings->sum('quantity'), 4),
                'total_value' => round($mainStockBeginning->stockBeginnings->sum('total_value'), 4),
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

        $validated = $request->validate([
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

    /**
     * Submit an approval action (approve or reject) for a stock beginning.
     *
     * @param Request $request
     * @param int $documentId
     * @return JsonResponse
     */
    public function submitApproval(Request $request, $documentId): JsonResponse
    {
        $this->authorize('update', MainStockBeginning::class);

        $validated = $request->validate([
            'request_type' => 'required|string|in:review,check,approve',
            'action' => 'required|string|in:approve,reject',
            'comment' => 'nullable|string|max:1000',
        ]);

        $method = $validated['action'] === 'approve' ? 'confirmApproval' : 'rejectApproval';
        $result = $this->approvalController->$method($request, MainStockBeginning::class, $documentId, $validated['request_type']);

        return response()->json([
            'message' => $result['message'],
            'approval' => $result['approval'] ?? null,
        ], $result['success'] ? 200 : 400);
    }

    /**
     * Reassign a responder for a specific request type.
     *
     * @param Request $request
     * @param int $documentId
     * @return JsonResponse
     */
    public function reassignResponder(Request $request, $documentId): JsonResponse
    {
        $this->authorize('update', MainStockBeginning::class);

        $validated = $request->validate([
            'request_type' => 'required|string|in:review,check,approve',
            'new_user_id' => 'required|exists:users,id',
            'comment' => 'nullable|string|max:1000',
        ]);

        // Validate that the new user has the appropriate permission
        $user = User::findOrFail($validated['new_user_id']);
        $permission = "mainStockBeginning.{$validated['request_type']}";
        if (!$user->hasPermissionTo($permission)) {
            return response()->json([
                'message' => "User ID {$validated['new_user_id']} does not have permission for {$validated['request_type']}.",
            ], 403);
        }

        $result = $this->approvalController->reassignResponder($request, MainStockBeginning::class, $documentId, $validated['request_type']);

        return response()->json([
            'message' => $result['message'],
        ], $result['success'] ? 200 : 400);
    }

    /**
     * List approvals for a specific stock beginning.
     *
     * @param Request $request
     * @param int $documentId
     * @return JsonResponse
     */
    public function listApprovals(Request $request, $documentId): JsonResponse
    {
        $mainStockBeginning = MainStockBeginning::findOrFail($documentId);
        $this->authorize('view', $mainStockBeginning);

        $result = $this->approvalController->listApprovals($request, MainStockBeginning::class, $documentId);

        return response()->json([
            'message' => $result['message'],
            'approvals' => $result['approvals'] ?? null,
        ], $result['success'] ? 200 : 403);
    }

    /**
     * Assign responders to a stock beginning.
     *
     * @param MainStockBeginning $mainStockBeginning
     * @param array $responders
     * @return void
     */
    protected function assignResponders(MainStockBeginning $mainStockBeginning, array $responders)
    {
        $syncData = [];
        foreach ($responders as $responder) {
            $syncData[$responder['user_id']] = ['request_type' => $responder['request_type']];
        }
        $mainStockBeginning->responders()->sync($syncData);
    }

    /**
     * Initialize approvals for a stock beginning.
     *
     * @param MainStockBeginning $mainStockBeginning
     * @return void
     */
    protected function initializeApprovals(MainStockBeginning $mainStockBeginning)
    {
        foreach ($mainStockBeginning->responders as $responder) {
            $approvalData = [
                'approvable_type' => MainStockBeginning::class,
                'approvable_id' => $mainStockBeginning->id,
                'document_name' => 'Stock Beginning',
                'request_type' => $responder->pivot->request_type,
                'approval_status' => 'pending',
                'ordinal' => $this->getOrdinalForRequestType($responder->pivot->request_type),
                'requester_id' => $mainStockBeginning->created_by,
                'responder_id' => $responder->id,
            ];

            $this->approvalController->storeApproval($approvalData);
        }
    }

    /**
     * Get ordinal for a request type.
     *
     * @param string $requestType
     * @return int
     */
    protected function getOrdinalForRequestType($requestType)
    {
        $ordinals = ['review' => 1, 'check' => 2, 'approve' => 3];
        return $ordinals[$requestType] ?? 1;
    }
}