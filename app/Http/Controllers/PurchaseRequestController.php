<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\User;
use App\Imports\PurchaseItemImport;
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
    ) {
        $this->approvalService = $approvalService;
        $this->productService = $productService;
        $this->campusService = $campusService;
        $this->departmentService = $departmentService;
    }

    // ====================
    // Views
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

        $requester = [
            'Requester' => $user->name,
            'Position'  => $user->defaultPosition()->title,
            'Card ID'   => $user->card_number,
            'Department'=> $user->defaultDepartment()->name,
            'Cellphone' => $user->phone,
            'Ext'       => $user->ext,
        ];

        $userDefaultDepartment = $user->defaultDepartment()->select('id', 'short_name')->first();
        $userDefaultCampus = $user->defaultCampus()->select('id', 'short_name')->first();

        return view('purchase-requests.form', compact('purchaseRequest', 'requester', 'userDefaultDepartment', 'userDefaultCampus'));
    }

    public function getEditData(PurchaseRequest $purchaseRequest): JsonResponse
    {
        try {
            $this->authorize('update', $purchaseRequest);

            $purchaseRequest->load([
                'items.campuses',
                'items.departments',
                'approvals.responder',
                'files'
            ]);

            return response()->json([
                'message' => 'Purchase request retrieved successfully.',
                'data' => [
                    'id' => $purchaseRequest->id,
                    'deadline_date' => $purchaseRequest->deadline_date,
                    'purpose' => $purchaseRequest->purpose,
                    'is_urgent' => $purchaseRequest->is_urgent,
                    'created_by' => $purchaseRequest->created_by,
                    'position_id' => $purchaseRequest->position_id,
                    'items' => $purchaseRequest->items->map(fn($i) => [
                        'id' => $i->id,
                        'product_id' => $i->product_id,
                        'product_code' => $i->product->item_code ?? null,
                        'product_description' => ($i->product->product->name ?? '') . ' - ' . ($i->product->description ?? ''),
                        'unit_name' => $i->product->product->unit->name ?? null,
                        'quantity' => $i->quantity,
                        'unit_price' => $i->unit_price,
                        'currency' => $i->currency,
                        'exchange_rate' => $i->exchange_rate,
                        'description' => $i->description,
                        'campus_ids' => $i->campuses->pluck('id')->toArray(),
                        'department_ids' => $i->departments->pluck('id')->toArray(),
                        'budget_code_id' => $i->budget_code_id,
                    ]),
                    'approvals' => $purchaseRequest->approvals->map(fn($a) => $a->responder ? [
                        'user_id' => $a->responder->id,
                        'name' => $a->responder->name,
                        'email' => $a->responder->email,
                        'request_type' => $a->request_type,
                    ] : null),
                    'files' => $purchaseRequest->files->map(fn($f) => [
                        'id' => $f->id,
                        'name' => $f->document_name,
                        'reference' => $f->document_reference,
                        'sharepoint_file_id' => $f->sharepoint_file_id,
                        'sharepoint_file_name' => $f->sharepoint_file_name,
                        'sharepoint_drive_id' => $f->sharepoint_drive_id,
                        'url' => $f->url,
                    ]),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve purchase request', [
                'id' => $purchaseRequest->id,
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'message' => 'Failed to retrieve purchase request.',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    // ====================
    // Import Items
    // ====================
    public function importItems(Request $request): JsonResponse
    {
        $this->authorize('create', PurchaseRequest::class);
        $request->validate(['file' => 'required|file|mimes:xlsx,xls,csv|max:2048']);

        try {
            $import = new PurchaseItemImport();
            Excel::import($import, $request->file('file'));
            $data = $import->getData();

            if (!empty($data['errors'])) {
                return response()->json(['message' => 'Errors found in Excel file.', 'errors' => $data['errors']], 422);
            }
            if (empty($data['items'])) {
                return response()->json(['message' => 'No valid data found.', 'errors' => ['No valid rows processed.']], 422);
            }

            return response()->json(['message' => 'Purchase items parsed successfully.', 'data' => ['items' => $data['items']]], 200);
        } catch (\Maatwebsite\Excel\Validators\ValidationException $e) {
            $errors = $e->failures()->map(fn($f) => "Row {$f->row()}: " . implode('; ', $f->errors()))->toArray();
            return response()->json(['message' => 'Validation failed', 'errors' => $errors], 422);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to parse purchase items', 'errors' => [$e->getMessage()]], 500);
        }
    }

    // ====================
    // Store / Update
    // ====================
    public function store(Request $request): JsonResponse
    {
        return $this->savePurchaseRequest($request);
    }

    public function update(Request $request, PurchaseRequest $purchaseRequest): JsonResponse
    {
        return $this->savePurchaseRequest($request, $purchaseRequest);
    }

    protected function savePurchaseRequest(Request $request, PurchaseRequest $purchaseRequest = null): JsonResponse
    {
        $user = Auth::user();
        $sharePoint = new SharePointService($user);

        $validated = $request->validate([
            'deadline_date' => 'nullable|date|after_or_equal:request_date',
            'purpose' => 'required|string',
            'is_urgent' => 'required|boolean',
            'items' => 'required|array|min:1',
            'items.*.id' => 'nullable|exists:purchase_request_items,id',
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
            'items.*.budget_code_id' => 'nullable',
            'approvals' => 'required|array|min:1',
            'approvals.*.user_id' => 'required|exists:users,id',
            'approvals.*.request_type' => 'required|string|in:approve,initial',
            'files' => 'nullable|array',
            'files.*.id' => 'nullable|exists:purchase_request_files,id',
            'files.*.file' => 'nullable|file|mimes:xlsx,xls,csv,pdf,jpg,png|max:20480',
        ]);

        $isNew = $purchaseRequest === null;

        try {
            return DB::transaction(function () use ($validated, $sharePoint, $user, &$purchaseRequest, $isNew) {
                if ($isNew) {
                    $referenceNo = $this->generateReferenceNo();
                    $purchaseRequest = PurchaseRequest::create([
                        'reference_no' => $referenceNo,
                        'request_date' => now()->format('Y-m-d'),
                        'deadline_date' => $validated['deadline_date'] ?? null,
                        'purpose' => $validated['purpose'],
                        'is_urgent' => $validated['is_urgent'],
                        'created_by' => $user->id,
                        'position_id' => $user->defaultPosition()->id,
                    ]);
                } else {
                    $purchaseRequest->update([
                        'deadline_date' => $validated['deadline_date'] ?? null,
                        'purpose' => $validated['purpose'],
                        'is_urgent' => $validated['is_urgent'],
                    ]);
                }

                $folderPath = $this->getSharePointFolderPath($purchaseRequest->reference_no);

                // -------------------- Files --------------------
                $this->handleFiles($purchaseRequest, $validated['files'] ?? [], $sharePoint, $folderPath);

                // -------------------- Items --------------------
                $this->handleItems($purchaseRequest, $validated['items']);

                // -------------------- Approvals --------------------
                $purchaseRequest->approvals()->delete();
                $this->storeApprovals($purchaseRequest, $validated['approvals']);

                return response()->json([
                    'message' => $isNew ? 'Purchase request created successfully.' : 'Purchase request updated successfully.',
                    'data' => $purchaseRequest->load('items', 'approvals.responder', 'files'),
                ], $isNew ? 201 : 200);
            });
        } catch (\Exception $e) {
            Log::error('Failed to save purchase request', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to save purchase request.', 'error' => $e->getMessage()], 500);
        }
    }

    protected function handleFiles(PurchaseRequest $purchaseRequest, array $files, SharePointService $sharePoint, string $folderPath): void
    {
        foreach ($files as $fileData) {
            if (isset($fileData['id'], $fileData['file'])) {
                $oldFile = $purchaseRequest->files()->find($fileData['id']);
                if ($oldFile) {
                    $newFileInfo = $sharePoint->updateFile(
                        $oldFile->sharepoint_file_id,
                        $fileData['file'],
                        ['Title' => 'Updated Document'],
                        $oldFile->sharepoint_drive_id,
                        $fileData['file']->getClientOriginalName()
                    );
                    $oldFile->update(['sharepoint_file_name' => $newFileInfo['name']]);
                }
            } elseif (isset($fileData['file'])) {
                $newFileInfo = $sharePoint->uploadFile(
                    $fileData['file'],
                    $folderPath,
                    ['Title' => 'Purchase Request Document'],
                    $fileData['file']->getClientOriginalName()
                );
                $purchaseRequest->files()->create([
                    'document_name' => 'Purchase Request Document',
                    'document_reference' => $purchaseRequest->reference_no,
                    'sharepoint_file_id' => $newFileInfo['id'],
                    'sharepoint_file_name' => $newFileInfo['name'],
                    'sharepoint_drive_id' => $newFileInfo['drive_id'] ?? config('services.sharepoint.drive_id'),
                ]);
            }
        }
    }

    protected function handleItems(PurchaseRequest $purchaseRequest, array $items): void
    {
        $existingItemIds = $purchaseRequest->items()->pluck('id')->toArray();
        $newItemIds = [];

        foreach ($items as $item) {
            $totalPrice = $item['quantity'] * $item['unit_price'];
            $totalPriceUsd = ($item['currency'] ?? null) === 'KHR' && !empty($item['exchange_rate'])
                ? $totalPrice / $item['exchange_rate']
                : $totalPrice;

            $purchaseItem = $item['id'] && in_array($item['id'], $existingItemIds)
                ? $purchaseRequest->items()->find($item['id'])->update(array_merge($item, ['total_price' => $totalPrice, 'total_price_usd' => $totalPriceUsd]))
                : $purchaseRequest->items()->create(array_merge($item, ['total_price' => $totalPrice, 'total_price_usd' => $totalPriceUsd]));

            $purchaseItem = is_numeric($purchaseItem) ? $purchaseRequest->items()->find($item['id']) : $purchaseItem;
            $this->syncItemRelations($purchaseItem, $item['campus_ids'], $item['department_ids'], $totalPriceUsd);

            $newItemIds[] = $purchaseItem->id;
        }

        // Keep old items; optionally delete removed:
        // $purchaseRequest->items()->whereNotIn('id', $newItemIds)->delete();
    }

    // ====================
    // Approval Handling
    // ====================
    protected function storeApprovals(PurchaseRequest $purchaseRequest, array $approvals): void
    {
        foreach ($approvals as $approval) {
            $approvalPayload = [
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
            ];
            $existingApproval = $this->approvalService->updateApproval($approvalPayload);
            if (!$existingApproval['success']) $this->approvalService->storeApproval($approvalPayload);
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
    // Pivot Sync
    // ====================
    protected function syncItemRelations(PurchaseRequestItem $item, array $campusIds, array $departmentIds, float $totalPriceUsd): void
    {
        $item->campuses()->sync(array_fill_keys($campusIds, ['total_usd' => $totalPriceUsd / count($campusIds)]));
        $item->departments()->sync(array_fill_keys($departmentIds, ['total_usd' => $totalPriceUsd / count($departmentIds)]));
    }

    // ====================
    // Utilities
    // ====================
    protected function getSharePointFolderPath(string $documentReference): string
    {
        $year = now()->format('Y');
        $monthNumber = now()->format('m');
        $monthName = now()->format('M');
        return "PurchaseRequest/{$year}/{$monthNumber}-{$monthName}/{$documentReference}";
    }

    protected function generateReferenceNo(): string
    {
        $prefix = 'PR-' . now()->format('Ym') . '-';
        $count = PurchaseRequest::withTrashed()->whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->count() + 1;

        do {
            $referenceNo = $prefix . str_pad($count, 4, '0', STR_PAD_LEFT);
            $exists = PurchaseRequest::withTrashed()->where('reference_no', $referenceNo)->exists();
            $count++;
        } while ($exists);

        return $referenceNo;
    }

    // ====================
    // Lookup APIs
    // ====================
    public function getApprovalUsers(Request $request): JsonResponse
    {
        $this->authorize('viewAny', PurchaseRequest::class);
        $validated = $request->validate(['request_type' => 'required|string|in:approve,initial,check,verify']);
        $permission = "purchaseRequest.{$validated['request_type']}";
        $authUserId = $request->user()->id;

        $users = User::query()
            ->whereHas('permissions', fn($q) => $q->where('name', $permission))
            ->orWhereHas('roles.permissions', fn($q) => $q->where('name', $permission))
            ->whereNotNull('telegram_id')
            ->where('id', '!=', $authUserId)
            ->select('id', 'name', 'telegram_id', 'card_number')
            ->orderBy('name')
            ->get();

        return response()->json(['message' => 'Users fetched successfully.', 'data' => $users]);
    }

    public function getProducts(Request $request): JsonResponse
    {
        $this->authorize('viewAny', PurchaseRequest::class);
        return response()->json($this->productService->getStockManagedVariants($request));
    }

    public function getCampuses(Request $request): JsonResponse
    {
        $this->authorize('viewAny', PurchaseRequest::class);
        $items = collect($this->campusService->getCampuses($request)['data'] ?? [])
            ->where('is_active', 1)->values();
        return response()->json(['data' => $items, 'recordsFiltered' => $items->count()]);
    }

    public function getDepartments(Request $request): JsonResponse
    {
        $this->authorize('viewAny', PurchaseRequest::class);
        $items = collect($this->departmentService->getDepartments($request)['data'] ?? [])
            ->where('is_active', 1)->values();
        return response()->json(['data' => $items, 'recordsFiltered' => $items->count()]);
    }
}
