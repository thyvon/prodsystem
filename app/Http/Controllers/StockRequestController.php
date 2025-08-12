<?php

namespace App\Http\Controllers;

use App\Models\StockRequest;
use App\Services\StockRequestService;
use App\Services\WarehouseService;
use App\Services\CampusService;
use App\Services\ProductService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\StockRequestsExport;
use App\Imports\StockRequestsImport;
use Illuminate\Support\Facades\Validator;

class StockRequestController extends Controller
{
    // Constants for sort columns and default values
    private const ALLOWED_SORT_COLUMNS = ['request_number', 'request_date', 'created_at', 'updated_at', 'created_by', 'updated_by'];
    private const DEFAULT_SORT_COLUMN = 'created_at';
    private const DEFAULT_SORT_DIRECTION = 'desc';
    private const DEFAULT_LIMIT = 10;
    private const MAX_LIMIT = 1000;
    private const DATE_FORMAT = 'Y-m-d';

    protected $stockRequestService;
    protected $approvalController;
    protected $warehouseService;
    protected $campusService;
    protected $productService;

    public function __construct(
        StockRequestService $stockRequestService,
        ApprovalController $approvalController, // Correct namespace: App\Http\Controllers
        WarehouseService $warehouseService,
        CampusService $campusService,
        ProductService $productService
    ) {
        $this->stockRequestService = $stockRequestService;
        $this->approvalController = $approvalController;
        $this->warehouseService = $warehouseService;
        $this->campusService = $campusService;
        $this->productService = $productService;
    }

    /**
     * Display the stock requests index view.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        $this->authorize('viewAny', StockRequest::class);
        return view('Inventory.stockRequest.index');
    }

    /**
     * Show the form for creating a new stock request.
     *
     * @return \Illuminate\View\View
     */
    public function create()
    {
        $this->authorize('create', StockRequest::class);
        return view('Inventory.stockRequest.form');
    }

    /**
     * Display a single stock request with its line items and approvals for viewing.
     *
     * @param StockRequest $stockRequest
     * @return \Illuminate\View\View
     */
    public function show(StockRequest $stockRequest)
    {
        $this->authorize('view', $stockRequest);

        try {
            // Load related data including approvals
            $stockRequest->load([
                'items.product.unit',
                'warehouse.building.campus',
                'campus',
                'createdBy',
                'updatedBy',
                'approvals.responder',
            ]);

            // Check if the approval button should be shown
            $approvalButtonData = $this->stockRequestService->canShowApprovalButton($stockRequest->id);

            // Derive responders from approvals
            $responders = $stockRequest->approvals->map(function ($approval) {
                return [
                    'id' => $approval->id,
                    'user_id' => $approval->responder_id,
                    'request_type' => $approval->request_type,
                    'name' => $approval->responder->name ?? 'N/A',
                ];
            })->toArray();

            return view('Inventory.stockRequest.show', [
                'stockRequest' => $stockRequest,
                'totalQuantity' => round($stockRequest->items->sum('quantity'), 4),
                'totalValue' => round($stockRequest->items->sum('total_price'), 4),
                'approvals' => $stockRequest->approvals
                    ->sortBy('ordinal')
                    ->values()
                    ->map(function ($approval) {
                        return [
                            'id' => $approval->id,
                            'request_type' => $approval->request_type,
                            'approval_status' => $approval->approval_status,
                            'responder_name' => $approval->responder->name ?? 'N/A',
                            'ordinal' => $approval->ordinal,
                            'comment' => $approval->comment,
                            'created_at' => $approval->created_at?->toDateTimeString(),
                            'updated_at' => $approval->updated_at?->toDateTimeString(),
                            'responded_date' => $approval->responded_date,
                        ];
                    })->toArray(),
                'responders' => $responders,
                'showApprovalButton' => $approvalButtonData['showButton'],
                'approvalRequestType' => $approvalButtonData['requestType'],
                'approvalButtonData' => $approvalButtonData,
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching stock request for display', [
                'error_message' => $e->getMessage(),
                'stock_request_id' => $stockRequest->id,
            ]);
            return response()->view('errors.500', [
                'message' => 'Failed to fetch stock request',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store a new stock request with its line items and approvals.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', StockRequest::class);

        try {
            $validated = $this->stockRequestService->validateRequest($request);
            $response = $this->stockRequestService->createStockRequest($validated);

            return response()->json([
                'message' => 'Stock request created successfully.',
                'data' => $response->load('items', 'approvals.responder'),
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Failed to create stock request', ['error' => $e->getMessage()]);
            return response()->json([
                'message' => 'Failed to create stock request',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Show the form for editing an existing stock request.
     *
     * @param StockRequest $stockRequest
     * @return \Illuminate\View\View
     */
    public function edit(StockRequest $stockRequest)
    {
        $this->authorize('update', $stockRequest);

        try {
            $stockRequest->load([
                'items.product.unit',
                'warehouse.building.campus',
                'campus',
                'approvals.responder',
            ]);

            Log::debug('Approvals loaded:', $stockRequest->approvals->map(function ($approval) {
                return [
                    'id' => $approval->id,
                    'responder_id' => $approval->responder_id,
                    'responder_name' => $approval->responder->name ?? null,
                    'request_type' => $approval->request_type,
                    'approval_status' => $approval->approval_status ?? null,
                    'comment' => $approval->comment ?? null,
                    'created_at' => $approval->created_at->toDateTimeString(),
                ];
            })->toArray());

            // Prepare data for the Vue form
            $stockRequestData = [
                'id' => $stockRequest->id,
                'request_number' => $stockRequest->request_number,
                'warehouse_id' => $stockRequest->warehouse_id,
                'campus_id' => $stockRequest->campus_id,
                'request_date' => $stockRequest->request_date,
                'type' => $stockRequest->type,
                'purpose' => $stockRequest->purpose,
                'approval_status' => $stockRequest->approval_status,
                'items' => $stockRequest->items->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'product_id' => $item->product_id,
                        'quantity' => $item->quantity,
                        'average_price' => $item->average_price,
                        'total_price' => $item->total_price,
                        'remarks' => $item->remarks,
                        'item_code' => $item->product->item_code ?? null,
                        'product_name' => $item->product->name ?? null,
                        'product_khmer_name' => $item->product->khmer_name ?? null,
                        'unit_name' => $item->product->unit->name ?? null,
                    ];
                })->toArray(),
                'warehouse' => $stockRequest->warehouse ? [
                    'id' => $stockRequest->warehouse->id,
                    'name' => $stockRequest->warehouse->name,
                    'building' => $stockRequest->warehouse->building ? [
                        'id' => $stockRequest->warehouse->building->id,
                        'short_name' => $stockRequest->warehouse->building->short_name,
                        'campus' => $stockRequest->warehouse->building->campus ? [
                            'id' => $stockRequest->warehouse->building->campus->id,
                            'short_name' => $stockRequest->warehouse->building->campus->short_name,
                        ] : null,
                    ] : null,
                ] : null,
                'campus' => $stockRequest->campus ? [
                    'id' => $stockRequest->campus->id,
                    'short_name' => $stockRequest->campus->short_name,
                ] : null,
                'approvals' => $stockRequest->approvals->map(function ($approval) {
                    return [
                        'id' => $approval->id,
                        'user_id' => $approval->responder_id,
                        'request_type' => $approval->request_type,
                    ];
                })->toArray(),
            ];

            return view('Inventory.stockRequest.form', [
                'stockRequest' => $stockRequest,
                'stockRequestData' => $stockRequestData,
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching stock request for editing', [
                'error_message' => $e->getMessage(),
                'stock_request_id' => $stockRequest->id,
            ]);
            return response()->view('errors.500', [
                'message' => 'Failed to fetch stock request',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update an existing stock request and its line items.
     *
     * @param Request $request
     * @param StockRequest $stockRequest
     * @return JsonResponse
     */
    public function update(Request $request, StockRequest $stockRequest): JsonResponse
    {
        $this->authorize('update', $stockRequest);

        try {
            $validated = $this->stockRequestService->validateRequest($request, $stockRequest->id);
            $response = $this->stockRequestService->updateStockRequest($stockRequest, $validated);

            return response()->json([
                'message' => 'Stock request updated successfully.',
                'data' => $response->load('items', 'approvals.responder'),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Failed to update stock request', ['error' => $e->getMessage()]);
            return response()->json([
                'message' => 'Failed to update stock request',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a stock request and its associated line items and approvals.
     *
     * @param StockRequest $stockRequest
     * @return JsonResponse
     */
    public function destroy(StockRequest $stockRequest): JsonResponse
    {
        $this->authorize('delete', $stockRequest);

        try {
            $this->stockRequestService->deleteStockRequest($stockRequest);

            return response()->json([
                'message' => 'Stock request and related approvals deleted successfully.',
            ], 200);
        } catch (\Exception $e) {
            Log::error('Failed to delete stock request', ['error' => $e->getMessage(), 'id' => $stockRequest->id]);
            return response()->json([
                'message' => 'Failed to delete stock request',
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
        try {
            $users = $this->stockRequestService->getUsersForApproval($request);
            return response()->json([
                'message' => 'Users fetched successfully.',
                'data' => $users,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch users for approval', [
                'request_type' => $request->input('request_type'),
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'message' => 'Failed to fetch users for approval.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Retrieve paginated stock requests with optional search and sort.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getStockRequests(Request $request): JsonResponse
    {
        $this->authorize('viewAny', StockRequest::class);

        try {
            $response = $this->stockRequestService->getStockRequests($request);
            return response()->json($response);
        } catch (\Exception $e) {
            Log::error('Failed to fetch stock requests', ['error' => $e->getMessage()]);
            return response()->json([
                'message' => 'Failed to fetch stock requests',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Import stock requests from an Excel file and return data for form population.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function import(Request $request): JsonResponse
    {
        $this->authorize('create', StockRequest::class);

        try {
            $response = $this->stockRequestService->importStockRequests($request);
            return response()->json($response, $response['status'] ?? 200);
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
            Log::error('Failed to import stock requests', ['error' => $e->getMessage()]);
            return response()->json([
                'message' => 'Failed to import stock requests',
                'errors' => [$e->getMessage()],
            ], 500);
        }
    }

    /**
     * Export stock requests to an Excel file.
     *
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function export(Request $request)
    {
        $this->authorize('viewAny', StockRequest::class);

        $validated = $request->validate([
            'search' => 'nullable|string|max:255',
            'sortColumn' => 'nullable|string|in:' . implode(',', self::ALLOWED_SORT_COLUMNS),
            'sortDirection' => 'nullable|string|in:asc,desc',
        ]);

        $query = StockRequest::with(['warehouse', 'campus', 'items.product'])
            ->when($validated['search'] ?? null, function ($query, $search) {
                $query->where('request_number', 'like', "%{$search}%")
                    ->orWhere('type', 'like', "%{$search}%")
                    ->orWhere('purpose', 'like', "%{$search}%")
                    ->orWhereHas('warehouse', fn($q) => $q->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('campus', fn($q) => $q->where('short_name', 'like', "%{$search}%"))
                    ->orWhereHas('items.product', function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%")
                            ->orWhere('khmer_name', 'like', "%{$search}%")
                            ->orWhere('description', 'like', "%{$search}%")
                            ->orWhere('item_code', 'like', "%{$search}%")
                            ->orWhereHas('unit', fn($q3) => $q3->where('name', 'like', "%{$search}%"));
                    });
            });

        $sortColumn = in_array($validated['sortColumn'] ?? self::DEFAULT_SORT_COLUMN, self::ALLOWED_SORT_COLUMNS)
            ? $validated['sortColumn']
            : self::DEFAULT_SORT_COLUMN;
        $sortDirection = in_array(strtolower($validated['sortDirection'] ?? self::DEFAULT_SORT_DIRECTION), ['asc', 'desc'])
            ? $validated['sortDirection']
            : self::DEFAULT_SORT_DIRECTION;

        $query->orderBy($sortColumn, $sortDirection);

        return Excel::download(new StockRequestsExport($query), 'stock_requests_' . now()->format('Ymd_His') . '.xlsx');
    }

    /**
     * Submit an approval action (approve or reject) for a stock request.
     *
     * @param Request $request
     * @param StockRequest $stockRequest
     * @return JsonResponse
     */
    public function submitApproval(Request $request, StockRequest $stockRequest): JsonResponse
    {
        try {
            $response = $this->stockRequestService->submitApproval($request, $stockRequest);
            return response()->json($response, $response['status'] ?? 200);
        } catch (\Exception $e) {
            Log::error('Failed to submit approval', [
                'stock_request_id' => $stockRequest->id,
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'message' => 'Failed to submit approval',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reassign a responder for a specific request type.
     *
     * @param Request $request
     * @param StockRequest $stockRequest
     * @return JsonResponse
     */
    public function reassignResponder(Request $request, StockRequest $stockRequest): JsonResponse
    {
        $this->authorize('reassign', $stockRequest);

        try {
            $response = $this->stockRequestService->reassignResponder($request, $stockRequest);
            return response()->json($response, $response['status'] ?? 200);
        } catch (\Exception $e) {
            Log::error('Failed to reassign responder', [
                'stock_request_id' => $stockRequest->id,
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'message' => 'Failed to reassign responder',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * List approvals for a specific stock request.
     *
     * @param Request $request
     * @param StockRequest $stockRequest
     * @return JsonResponse
     */
    public function listApprovals(Request $request, StockRequest $stockRequest): JsonResponse
    {
        $this->authorize('view', $stockRequest);

        try {
            $response = $this->stockRequestService->listApprovals($request, $stockRequest);
            return response()->json($response, $response['status'] ?? 200);
        } catch (\Exception $e) {
            Log::error('Failed to list approvals', [
                'stock_request_id' => $stockRequest->id,
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'message' => 'Failed to list approvals',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Fetch warehouses for stock request.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getWarehouses(Request $request): JsonResponse
    {
        $this->authorize('viewAny', StockRequest::class);

        try {
            $response = $this->warehouseService->getWarehouses($request);
            return response()->json($response);
        } catch (\Exception $e) {
            Log::error('Failed to fetch warehouses', ['error' => $e->getMessage()]);
            return response()->json([
                'message' => 'Failed to fetch warehouses',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

        /**
     * Fetch campuses for stock request.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getCampuses(Request $request)
    {
        $this->authorize('viewAny', StockRequest::class);

        try {
            $response = $this->campusService->getCampuses($request);
            return response()->json($response);
        } catch (\Exception $e) {
            Log::error('Failed to fetch campuses', ['error' => $e->getMessage()]);
            return response()->json([
                'message' => 'Failed to fetch campuses',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Fetch products for stock request.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getProducts(Request $request): JsonResponse
    {
        $this->authorize('viewAny', StockRequest::class);

        try {
            $response = $this->productService->getStockManagedVariants($request);
            return response()->json($response);
        } catch (\Exception $e) {
            Log::error('Failed to fetch products', ['error' => $e->getMessage()]);
            return response()->json([
                'message' => 'Failed to fetch products',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}