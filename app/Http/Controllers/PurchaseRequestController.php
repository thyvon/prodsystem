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

class PurchaseRequestController extends Controller
{
    protected ApprovalService $approvalService;
    protected ProductService $productService;
    private const CUSTOM_DRIVE_ID = 'b!M8DPdNUo-UW5SA5DQoh6WBOHI8g_WM1GqHrcuxe8NjqK7G8JZp38SZIzeDteW3fZ';

    public function __construct(
        ApprovalService $approvalService,
        ProductService $productService
        )
    {
        $this->approvalService = $approvalService;
        $this->productService = $productService;
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

        return view('purchase-requests.form', compact('purchaseRequest', 'requester'));
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
            'reference_no' => 'required|string|unique:purchase_requests,reference_no',
            'request_date' => 'required|date',
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
            'items.*.campus_id' => 'nullable|exists:campus,id',
            'items.*.department_id' => 'nullable|exists:departments,id',
            'items.*.division_id' => 'nullable|exists:divisions,id',
            'items.*.budget_code_id' => 'nullable|exists:budget_items,id',

            'created_by' => 'required|exists:users,id',
            'position_id' => 'required|exists:positions,id',
            'updated_by' => 'nullable|exists:users,id',
            'deleted_by' => 'nullable|exists:users,id',

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
                        SharePointService::CUSTOM_DRIVE_ID
                    );

                    $counter++;
                }

                // --------------------
                // Create Purchase Request
                // --------------------
                $purchaseRequest = PurchaseRequest::create([
                    'reference_no' => $referenceNo,
                    'request_date' => $validated['request_date'],
                    'deadline_date' => $validated['deadline_date'] ?? null,
                    'purpose' => $validated['purpose'],
                    'is_urgent' => $validated['is_urgent'],
                    'created_by' => $user->id,
                    'position_id' => $user->current_position_id,
                ]);

                // --------------------
                // Prepare items
                // --------------------
                $itemsData = array_map(function ($item) use ($purchaseRequest) {
                    $totalPrice = $item['quantity'] * $item['unit_price'];
                    $totalPriceUsd = ($item['currency'] ?? null) === 'KHR' && !empty($item['exchange_rate'])
                        ? $totalPrice / $item['exchange_rate']
                        : $totalPrice;

                    return [
                        'purchase_request_id' => $purchaseRequest->id,
                        'product_id' => $item['product_id'],
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['unit_price'],
                        'total_price' => $totalPrice,
                        'currency' => $item['currency'] ?? null,
                        'exchange_rate' => $item['exchange_rate'] ?? null,
                        'total_price_usd' => $totalPriceUsd,
                        'description' => $item['description'] ?? null,
                        'campus_id' => $item['campus_id'] ?? null,
                        'department_id' => $item['department_id'] ?? null,
                        'division_id' => $item['division_id'] ?? null,
                        'budget_code_id' => $item['budget_code_id'] ?? null,
                    ];
                }, $validated['items']);

                PurchaseRequestItem::insert($itemsData);

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
        $authUser = $request->user();
        $isAdmin = $authUser->hasRole('admin');

        try {
            $authDepartmentIds = !$isAdmin
                ? $authUser->departments()->pluck('departments.id')->toArray()
                : [];

            $usersQuery = User::query()
                ->where(function ($query) use ($permission) {
                    $query->whereHas('permissions', fn($q) => $q->where('name', $permission))
                        ->orWhereHas('roles.permissions', fn($q) => $q->where('name', $permission));
                })
                ->whereNotNull('telegram_id')
                ->where('id', '!=', $authUser->id);

            if (!$isAdmin) {
                $usersQuery->whereHas('departments', fn($q) => $q->whereIn('departments.id', $authDepartmentIds));
            }

            $users = $usersQuery->select('id', 'name', 'telegram_id', 'card_number')->get();

            return response()->json([
                'message' => 'Users fetched successfully.',
                'data' => $users,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch users for approval', [
                'request_type' => $validated['request_type'],
                'auth_user_id' => $authUser->id,
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
        
        // Filter response to include only items where is_active = 1
        $filteredResponse = [
            'data' => collect($response['data'])->filter(function ($item) {
                return $item['is_active'] == 1;
            })->values()->all(),
            'recordsTotal' => $response['recordsTotal'],
            'recordsFiltered' => count($response['data']),
            'draw' => $response['draw'],
        ];
        
        return response()->json($filteredResponse);
    }
}
