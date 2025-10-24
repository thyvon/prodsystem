<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\User;
use App\Services\SharePointService;
use App\Services\ApprovalService;
use App\Services\ProductService;
use App\Services\CampusService;
use App\Services\DepartmentService;

class PurchaseRequestController extends Controller
{
    protected ApprovalService $approvalService;
    protected ProductService $productService;
    protected CampusService $campusService;
    protected DepartmentService $departmentService;
    private const CUSTOM_DRIVE_ID = 'b!M8DPdNUo-UW5SA5DQoh6WBOHI8g_WM1GqHrcuxe8NjqK7G8JZp38SZIzeDteW3fZ';

    public function __construct(
        ApprovalService $approvalService,
        ProductService $productService,
        CampusService $campusService,
        DepartmentService $departmentService
    )
    {
        $this->approvalService = $approvalService;
        $this->productService = $productService;
        $this->campusService = $campusService;
        $this->departmentService = $departmentService;
    }
    // ====================
    // Index & Form Views
    // ====================
    public function index(): View
    {
        $this->authorize('viewAny', PurchaseRequest::class);
        return view('purchase-requests.index');
    }

    public function form(?PurchaseRequest $purchaseRequest = null): View
    {
        $this->authorize($purchaseRequest ? 'update' : 'create', [PurchaseRequest::class, $purchaseRequest]);

        $user = Auth::user();

        // Map user data to labeled fields
        $requester = [
            'Requester'  => $user->name,
            'Position'   => $user->defaultPosition()->title,
            'Card ID'    => $user->card_number,
            'Department' => $user->defaultDepartment()->name,
            'Cellphone'  => $user->phone,
            'Ext'        => $user->ext,
        ];

        // Get default department and campus
        $userDefaultDepartment = $user->defaultDepartment()->select('id', 'short_name')->first();
        $userDefaultCampus = $user->defaultCampus()->select('id', 'short_name')->first();

        return view('purchase-requests.form', compact('purchaseRequest', 'requester', 'userDefaultDepartment', 'userDefaultCampus'));
    }


    // ====================
    // Store Purchase Request
    // ====================
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', [PurchaseRequest::class]);
        $user = Auth::user();

        // --------------------
        // Validate Request
        // --------------------
        $validated = $request->validate([
            'deadline_date' => 'nullable|date|after_or_equal:request_date',
            'purpose' => 'required|string',
            'is_urgent' => 'required|boolean',

            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:product_variants,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.description' => 'nullable|string|max:500',
            'items.*.currency' => 'nullable|string|max:10',
            'items.*.exchange_rate' => 'nullable|numeric|min:0',
            'items.*.campus_ids' => 'required|array|min:1',
            'items.*.campus_ids.*' => 'required|exists:campus,id',
            'items.*.department_ids' => 'required|array|min:1',
            'items.*.department_ids.*' => 'required|exists:departments,id',
            'items.*.budget_code_id' => 'nullable|exists:budget_items,id',

            'approvals' => 'required|array|min:1',
            'approvals.*.user_id' => 'required|exists:users,id',
            'approvals.*.request_type' => 'required|string|in:approve,initial',
        ]);

        $sharePoint = new SharePointService($user);

        try {
            return DB::transaction(function () use ($validated, $request, $sharePoint, $user) {

                // --------------------
                // Generate reference & folder path
                // --------------------
                $referenceNo = $this->generateReferenceNo();
                $folderPath = $this->getSharePointFolderPath($referenceNo);

                // --------------------
                // Handle uploaded files (single or multiple)
                // --------------------
                $files = $request->file('file');
                $files = is_array($files) ? $files : [$files];
                $uploadedFiles = [];
                $counter = 1;

                foreach ($files as $file) {
                    if (!$file) continue;

                    $extension = $file->getClientOriginalExtension();
                    $fileName = "{$referenceNo}-{$counter}.{$extension}";

                    $uploadedFiles[] = $sharePoint->uploadFile(
                        $file,
                        $folderPath,
                        ['Title' => uniqid()],
                        $fileName,
                        self::CUSTOM_DRIVE_ID
                    );

                    $counter++;
                }

                // --------------------
                // Create Purchase Request
                // --------------------
                $purchaseRequest = PurchaseRequest::create([
                    'reference_no' => $referenceNo,
                    'request_date' => now()->format('Y-m-d'),
                    'deadline_date' => $validated['deadline_date'] ?? null,
                    'purpose' => $validated['purpose'],
                    'is_urgent' => $validated['is_urgent'],
                    'created_by' => $user->id,
                    'position_id' => $user->defaultPosition()->id,
                ]);

                // --------------------
                // Prepare items
                // --------------------
                foreach ($validated['items'] as $item) {
                    $totalPrice = $item['quantity'] * $item['unit_price'];
                    $totalPriceUsd = ($item['currency'] ?? null) === 'KHR' && !empty($item['exchange_rate'])
                        ? $totalPrice / $item['exchange_rate']
                        : $totalPrice;

                    // Create the item
                    $purchaseRequestItem = PurchaseRequestItem::create([
                        'purchase_request_id' => $purchaseRequest->id,
                        'product_id' => $item['product_id'],
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['unit_price'],
                        'total_price' => $totalPrice,
                        'currency' => $item['currency'] ?? null,
                        'exchange_rate' => $item['exchange_rate'] ?? null,
                        'total_price_usd' => $totalPriceUsd,
                        'description' => $item['description'] ?? null,
                        'budget_code_id' => $item['budget_code_id'] ?? null,
                    ]);

                    // Calculate distributed amounts
                    $campusCount = count($item['campus_ids']);
                    $departmentCount = count($item['department_ids']);
                    $perCampusUsd = $totalPriceUsd / $campusCount;
                    $perDepartmentUsd = $totalPriceUsd / $departmentCount;

                    // Sync campuses with pivot data
                    $campusPivotData = array_fill_keys($item['campus_ids'], ['total_usd' => $perCampusUsd]);
                    $purchaseRequestItem->campuses()->sync($campusPivotData);

                    // Sync departments with pivot data
                    $departmentPivotData = array_fill_keys($item['department_ids'], ['total_usd' => $perDepartmentUsd]);
                    $purchaseRequestItem->departments()->sync($departmentPivotData);
                }

                // --------------------
                // Store approvals
                // --------------------
                $this->storeApprovals($purchaseRequest, $validated['approvals']);

                return response()->json([
                    'message' => 'Purchase request created successfully.',
                    'data' => $purchaseRequest->load('items', 'approvals.responder'),
                ], 201);
            });
        } catch (\Exception $e) {
            Log::error('Failed to create purchase request', ['error' => $e->getMessage()]);
            return response()->json([
                'message' => 'Failed to create purchase request.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // ====================
    // Helpers
    // ====================
    private function getSharePointFolderPath(string $documentReference): string
    {
        $year = now()->format('Y');
        $monthNumber = now()->format('m');
        $monthName = now()->format('M');
        return "PurchaseRequest/{$year}/{$monthNumber}-{$monthName}/{$documentReference}";
    }

    private function generateReferenceNo(): string
    {
        $prefix = 'PR-' . now()->format('Ym') . '-';
        $count = PurchaseRequest::withTrashed()
            ->whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->count() + 1;

        do {
            $referenceNo = $prefix . str_pad($count, 4, '0', STR_PAD_LEFT);
            $exists = PurchaseRequest::withTrashed()->where('reference_no', $referenceNo)->exists();
            $count++;
        } while ($exists);

        return $referenceNo;
    }

    protected function storeApprovals(PurchaseRequest $purchaseRequest, array $approvals)
    {
        foreach ($approvals as $approval) {
            $this->approvalService->storeApproval([
                'approvable_type' => PurchaseRequest::class,
                'approvable_id' => $purchaseRequest->id,
                'document_name' => $purchaseRequest->document_type ?? 'Purchase Request',
                'document_reference' => $purchaseRequest->reference_no,
                'request_type' => $approval['request_type'],
                'approval_status' => 'Pending',
                'ordinal' => $this->getOrdinalForRequestType($approval['request_type']),
                'requester_id' => $purchaseRequest->created_by,
                'responder_id' => $approval['user_id'],
                'position_id' => User::find($approval['user_id'])?->defaultPosition()?->id,
            ]);
        }
    }

    protected function getOrdinalForRequestType(string $requestType): int
    {
        return match ($requestType) {
            'initial' => 1,
            'check' => 2,
            'review' => 3,
            'approve' => 4,
            'acknowledge' => 5,
            default => 1,
        };
    }

    // ====================
    // Approval Users Endpoint
    // ====================
    public function getApprovalUsers(Request $request): JsonResponse
    {
        $this->authorize('viewAny', PurchaseRequest::class);

        $validated = $request->validate([
            'request_type' => ['required', 'string', 'in:approve,initial,check,verify'],
        ]);

        $permission = "purchaseRequest.{$validated['request_type']}";
        $authUserId = $request->user()->id;

        try {
            $users = User::query()
                ->whereHas('permissions', fn($q) => $q->where('name', $permission))
                ->orWhereHas('roles.permissions', fn($q) => $q->where('name', $permission))
                ->whereNotNull('telegram_id')
                ->where('id', '!=', $authUserId)
                ->select('id', 'name', 'telegram_id', 'card_number')
                ->orderBy('name')
                ->distinct()
                ->get();

            return response()->json([
                'message' => 'Users fetched successfully.',
                'data' => $users,
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to fetch users for approval', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to fetch users for approval.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // ====================
    // Get Products
    // ====================
    public function getProducts(Request $request)
    {
        $this->authorize('viewAny', PurchaseRequest::class);
        $response = $this->productService->getStockManagedVariants($request);
        return response()->json($response);
    }


    public function getCampuses(Request $request)
    {
        $this->authorize('viewAny', PurchaseRequest::class);

        // The service already returns an array
        $response = $this->campusService->getCampuses($request);

        $items = $response['data'] ?? [];
        $filtered = collect($items)
            ->filter(fn($item) => data_get($item, 'is_active') == 1)
            ->values()
            ->all();

        $filteredResponse = [
            'data'             => $filtered,
            'recordsTotal'     => $response['recordsTotal'] ?? count($items),
            'recordsFiltered'  => count($filtered),
            'draw'             => $response['draw'] ?? null,
        ];

        return response()->json($filteredResponse);
    }

    public function getDepartments(Request $request)
    {
        $this->authorize('viewAny', PurchaseRequest::class);

        // The service already returns an array
        $response = $this->departmentService->getDepartments($request);

        $items = $response['data'] ?? [];
        $filtered = collect($items)
            ->filter(fn($item) => data_get($item, 'is_active') == 1)
            ->values()
            ->all();

        $filteredResponse = [
            'data'             => $filtered,
            'recordsTotal'     => $response['recordsTotal'] ?? count($items),
            'recordsFiltered'  => count($filtered),
            'draw'             => $response['draw'] ?? null,
        ];

        return response()->json($filteredResponse);
    }

}
