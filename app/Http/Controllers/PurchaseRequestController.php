<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Imports\PurchaseItemImport;
use Maatwebsite\Excel\Facades\Excel;

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

    public function getEditData(PurchaseRequest $purchaseRequest): JsonResponse
    {
        try {
            $this->authorize('update', $purchaseRequest);

            $purchaseRequest->load([
                'items.campuses',
                'items.departments',
                'approvals.responder', // include responder user
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
                        'product_id' => $i->product_id,
                        'product_code' => $i->product->item_code,
                        'product_description' => $i->product->product->name . ' - ' . $i->product->description,
                        'unit_name' => $i->product->product->unit->name,
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
                        'url' => $f->url, // make sure your DocumentRelation model has getUrlAttribute
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


    public function importItems(Request $request): JsonResponse
    {
        $this->authorize('create', PurchaseRequest::class);

        $validated = $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:2048',
        ]);

        try {
            $import = new PurchaseItemImport();
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
                'message' => 'Purchase items data parsed successfully.',
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
                'message' => 'Failed to parse purchase items',
                'errors' => [$e->getMessage()],
            ], 500);
        }
    }

    // ====================
    // Store Purchase Request
    // ====================
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', [PurchaseRequest::class]);
        $user = Auth::user();

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
            'items.*.budget_code_id' => 'nullable',
            'approvals' => 'required|array|min:1',
            'approvals.*.user_id' => 'required|exists:users,id',
            'approvals.*.request_type' => 'required|string|in:approve,initial',
        ]);

        $sharePoint = new SharePointService($user);

        try {
            return DB::transaction(function () use ($validated, $request, $sharePoint, $user) {

                $referenceNo = $this->generateReferenceNo();
                $folderPath = $this->getSharePointFolderPath($referenceNo);

                // --------------------
                // Create Purchase Request first
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
                // Handle uploaded files
                // --------------------
                $files = $request->file('file');
                $files = is_array($files) ? $files : [$files];
                $uploadedFiles = [];
                $counter = 1;

                foreach ($files as $file) {
                    if (!$file) continue;

                    $extension = $file->getClientOriginalExtension();
                    $fileName = "{$referenceNo}-{$counter}.{$extension}";

                    // if upload fails, throw exception so DB rolls back
                    $result = $sharePoint->uploadFile(
                        $file,
                        $folderPath,
                        ['Title' => uniqid()],
                        $fileName,
                        self::CUSTOM_DRIVE_ID
                    );

                    if (!$result) {
                        throw new \Exception("Failed to upload file: {$fileName}");
                    }

                    $uploadedFiles[] = $result;
                    $counter++;
                }
                $this->storeDocuments($purchaseRequest, $uploadedFiles);

                // --------------------
                // Prepare and store items
                // --------------------
                foreach ($validated['items'] as $item) {
                    $totalPrice = $item['quantity'] * $item['unit_price'];
                    $totalPriceUsd = ($item['currency'] ?? null) === 'KHR' && !empty($item['exchange_rate'])
                        ? $totalPrice / $item['exchange_rate']
                        : $totalPrice;

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

                    $campusCount = count($item['campus_ids']);
                    $departmentCount = count($item['department_ids']);
                    $perCampusUsd = $totalPriceUsd / $campusCount;
                    $perDepartmentUsd = $totalPriceUsd / $departmentCount;

                    $campusPivotData = array_fill_keys($item['campus_ids'], ['total_usd' => $perCampusUsd]);
                    $purchaseRequestItem->campuses()->sync($campusPivotData);

                    $departmentPivotData = array_fill_keys($item['department_ids'], ['total_usd' => $perDepartmentUsd]);
                    $purchaseRequestItem->departments()->sync($departmentPivotData);
                }

                // --------------------
                // Store approvals
                // --------------------
                $this->storeApprovals($purchaseRequest, $validated['approvals']);

                // All succeeded, return
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

    public function update(Request $request, PurchaseRequest $purchaseRequest): JsonResponse
    {
        $this->authorize('update', $purchaseRequest);
        $user = Auth::user();

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
            'existing_file_ids' => 'nullable|array', // IDs of files to keep
            'existing_file_ids.*' => 'integer|exists:purchase_request_files,id',
        ]);

        $sharePoint = new SharePointService($user);

        try {
            return DB::transaction(function () use ($validated, $request, $sharePoint, $purchaseRequest, $user) {

                // --------------------
                // Update purchase request main fields
                // --------------------
                $purchaseRequest->update([
                    'deadline_date' => $validated['deadline_date'] ?? null,
                    'purpose' => $validated['purpose'],
                    'is_urgent' => $validated['is_urgent'],
                ]);

                // --------------------
                // Handle files
                // --------------------
                $existingFileIds = $validated['existing_file_ids'] ?? [];
                $filesToDelete = $purchaseRequest->files()->whereNotIn('id', $existingFileIds)->get();

                foreach ($filesToDelete as $file) {
                    $sharePoint->deleteFile($file->sharepoint_drive_id, $file->sharepoint_file_id);
                    $file->delete();
                }

                if ($request->hasFile('file')) {
                    $newFiles = is_array($request->file('file')) ? $request->file('file') : [$request->file('file')];
                    $counter = $purchaseRequest->files()->count() + 1;
                    $folderPath = $this->getSharePointFolderPath($purchaseRequest->reference_no);

                    foreach ($newFiles as $file) {
                        if (!$file) continue;

                        $extension = $file->getClientOriginalExtension();
                        $fileName = "{$purchaseRequest->reference_no}-{$counter}.{$extension}";

                        $result = $sharePoint->uploadFile(
                            $file,
                            $folderPath,
                            ['Title' => uniqid()],
                            $fileName,
                            self::CUSTOM_DRIVE_ID
                        );

                        if (!$result) {
                            throw new \Exception("Failed to upload file: {$fileName}");
                        }

                        $this->storeDocuments($purchaseRequest, [$result]);
                        $counter++;
                    }
                }

                // --------------------
                // Smart update items
                // --------------------
                $existingItemIds = $purchaseRequest->items()->pluck('id')->toArray();
                $submittedItemIds = collect($validated['items'])->pluck('id')->filter()->toArray();

                // Delete items removed in the request
                $itemsToDelete = array_diff($existingItemIds, $submittedItemIds);
                PurchaseRequestItem::destroy($itemsToDelete);

                foreach ($validated['items'] as $item) {
                    $totalPrice = $item['quantity'] * $item['unit_price'];
                    $totalPriceUsd = ($item['currency'] ?? null) === 'KHR' && !empty($item['exchange_rate'])
                        ? $totalPrice / $item['exchange_rate']
                        : $totalPrice;

                    if (!empty($item['id'])) {
                        $itemModel = PurchaseRequestItem::find($item['id']);
                        $itemModel->update([
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
                    } else {
                        $itemModel = PurchaseRequestItem::create([
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
                    }

                    $campusCount = count($item['campus_ids']);
                    $departmentCount = count($item['department_ids']);
                    $perCampusUsd = $totalPriceUsd / $campusCount;
                    $perDepartmentUsd = $totalPriceUsd / $departmentCount;

                    $itemModel->campuses()->sync(array_fill_keys($item['campus_ids'], ['total_usd' => $perCampusUsd]));
                    $itemModel->departments()->sync(array_fill_keys($item['department_ids'], ['total_usd' => $perDepartmentUsd]));
                }

                // --------------------
                // Replace approvals
                // --------------------
                $purchaseRequest->approvals()->delete();
                $this->storeApprovals($purchaseRequest, $validated['approvals']);

                return response()->json([
                    'message' => 'Purchase request updated successfully.',
                    'data' => $purchaseRequest->load('items', 'approvals.responder', 'files'),
                ]);
            });
        } catch (\Exception $e) {
            Log::error('Failed to update purchase request', ['error' => $e->getMessage()]);
            return response()->json([
                'message' => 'Failed to update purchase request.',
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

            try {
                $existingApproval = $this->approvalService->updateApproval($approvalPayload);

                // Safe check for success key
                $isExistingPending = is_array($existingApproval) && !empty($existingApproval['success']) && $existingApproval['success'] === true;

                if (!$isExistingPending) {
                    $this->approvalService->storeApproval($approvalPayload);
                }

            } catch (\Throwable $e) {
                Log::warning('Approval save failed', [
                    'purchase_request_id' => $purchaseRequest->id,
                    'user_id' => $approval['user_id'],
                    'error' => $e->getMessage(),
                ]);
                // continue to next approval without breaking the transaction
            }
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

    protected function storeDocuments(PurchaseRequest $purchaseRequest, array $uploadedFiles)
    {
        foreach ($uploadedFiles as $file) {
            $purchaseRequest->files()->create([
                'document_name' => 'Purchase Request Document',
                'document_reference' => $purchaseRequest->reference_no,
                'sharepoint_file_id' => $file['id'],
                'sharepoint_file_name' => $file['name'],
                'sharepoint_drive_id' => self::CUSTOM_DRIVE_ID,
            ]);
        }
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
