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
use Spatie\Browsershot\Browsershot;
use Carbon\Carbon;

use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\Approval;
use App\Models\User;
// use App\Services\SharePointService;
use App\Services\FileServerService;
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
    protected FileServerService $fileServerService;
    // private const CUSTOM_DRIVE_ID = 'b!M8DPdNUo-UW5SA5DQoh6WBOHI8g_WM1GqHrcuxe8NjqK7G8JZp38SZIzeDteW3fZ';

    private const MAX_LIMIT = 50;
    private const DEFAULT_LIMIT = 10;

    public function __construct(
        ApprovalService $approvalService,
        ProductService $productService,
        CampusService $campusService,
        DepartmentService $departmentService,
        FileServerService $fileServerService
    )
    {
        $this->approvalService = $approvalService;
        $this->productService = $productService;
        $this->campusService = $campusService;
        $this->departmentService = $departmentService;
        $this->fileServerService = $fileServerService;
    }
    // ====================
    // Index & Form Views
    // ====================


    public function index(): View
    {
        $this->authorize('viewAny', PurchaseRequest::class);
        return view('purchase-requests.index');
    }

    public function getPurchaseRequests(Request $request): JsonResponse
    {
        $this->authorize('viewAny', PurchaseRequest::class);

        $validated = $request->validate([
            'search' => 'nullable|string|max:255',
            'sortColumn' => 'nullable|string',
            'sortDirection' => 'nullable|string|in:asc,desc',
            'limit' => 'nullable|integer|min:1|max:' . self::MAX_LIMIT,
            'page' => 'nullable|integer|min:1',
            'draw' => 'nullable|integer',
        ]);

        $sortColumn = $validated['sortColumn'] ?? 'id';
        $sortDirection = $validated['sortDirection'] ?? 'desc';

        $query = PurchaseRequest::select('id', 'reference_no', 'request_date', 'deadline_date', 'purpose', 'is_urgent', 'approval_status', 'created_by', 'created_at', 'updated_at')
            ->with(['creator:id,name'])
            ->whereNull('deleted_at');

        if (!empty($validated['search'])) {
            $search = $validated['search'];
            $query->where(function ($q) use ($search) {
                $q->where('reference_no', 'like', "%{$search}%")
                ->orWhere('purpose', 'like', "%{$search}%");
            });
        }

        $query->orderBy($sortColumn, $sortDirection);

        $purchaseRequests = $query->paginate(
            $validated['limit'] ?? self::DEFAULT_LIMIT,
            ['*'],
            'page',
            $validated['page'] ?? 1
        );
        $totalRecords = PurchaseRequest::whereNull('deleted_at')->count();

        $purchaseRequestsMapped = $purchaseRequests->map(fn($purchaseRequest) => [
            'id' => $purchaseRequest->id,
            'reference_no' => $purchaseRequest->reference_no,
            'request_date' => $purchaseRequest->request_date,
            'deadline_date' => $purchaseRequest->deadline_date,
            'purpose' => $purchaseRequest->purpose,
            'is_urgent' => $purchaseRequest->is_urgent,
            'approval_status' => $purchaseRequest->approval_status,
            'creator' => $purchaseRequest->creator?->name,
            'created_at' => $purchaseRequest->created_at,
            'updated_at' => $purchaseRequest->updated_at,
            'amount_usd' => number_format($purchaseRequest->items()->sum('total_price_usd'), 2, '.', ',') . ' USD',
        ]);

        return response()->json([
            'draw' => (int) ($validated['draw'] ?? 1),
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $purchaseRequests->total(),
            'data' => $purchaseRequestsMapped,
        ]);
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

    public function show(PurchaseRequest $purchaseRequest): View
    {
        $this->authorize('view', $purchaseRequest);

        return view('purchase-requests.show', [
            'purchaseRequestId' => $purchaseRequest->id,
            'referenceNo' => $purchaseRequest->reference_no,
        ]);
    }


    public function showData(PurchaseRequest $purchaseRequest): JsonResponse
    {
        try {
            $this->authorize('update', $purchaseRequest);
            $data = $this->mapPurchaseRequestData($purchaseRequest);

            return response()->json([
                'message' => 'Purchase request retrieved successfully.',
                'data' => $data,
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


    public function viewPdf(PurchaseRequest $purchaseRequest)
    {
        $data = $this->mapPurchaseRequestData($purchaseRequest);

        $html = view('purchase-requests.printpage', [
            'purchaseRequest' => $data
        ])->render();

        return Browsershot::html($html)
            ->noSandbox()
            ->format('A4')
            ->margins(5, 3, 5, 3) // top, right, bottom, left
            ->showBackground()
            ->pdf(); // return PDF content
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
                        'name' => $f->file_name,
                        'reference' => $f->document_reference,
                        'path' => $f->path,
                        'url' => $f->url
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

        $validated = $request->validate($this->validationRules());

        try {
            return DB::transaction(function () use ($validated, $request, $user) {

                $referenceNo = $this->generateReferenceNo();

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


                $folderPath = $this->getFolderPath($purchaseRequest);

                // --------------------
                // Handle uploaded files
                // --------------------
                if ($request->hasFile('file')) {
                    $newFiles = is_array($request->file('file')) ? $request->file('file') : [$request->file('file')];
                    $counter = $purchaseRequest->files()->count() + 1;

                    foreach ($newFiles as $file) {
                        if (!$file) continue;

                        $ext = strtoupper($file->getClientOriginalExtension());
                        $name = strtoupper(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
                        $unique = strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));
                        $index = str_pad($counter, 2, '0', STR_PAD_LEFT);

                        $safeName = preg_replace('/[^A-Z0-9_\-]/', '_', $name);
                        $fileName = strtoupper("{$purchaseRequest->reference_no}-{$unique}-{$index}-{$safeName}.{$ext}");

                        $result = $this->fileServerService->uploadFile(
                            $file,
                            $folderPath,
                            $fileName
                        );

                        if (!$result) {
                            throw new \Exception("FAILED TO UPLOAD FILE: {$fileName}");
                        }

                        $this->storeDocuments($purchaseRequest, [$result]);
                        $counter++;
                    }
                }

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

        $validated = $request->validate($this->validationRules($purchaseRequest));

        try {
            return DB::transaction(function () use ($validated, $request, $purchaseRequest, $user) {

                // --------------------
                // Update main fields
                // --------------------
                $purchaseRequest->update([
                    'deadline_date' => $validated['deadline_date'] ?? null,
                    'purpose' => $validated['purpose'],
                    'is_urgent' => $validated['is_urgent'],
                ]);

                // --------------------
                // Handle file deletions
                // --------------------
                $existingFileIds = $validated['existing_file_ids'] ?? [];
                $filesToDelete = $purchaseRequest->files()->whereNotIn('id', $existingFileIds)->get();

                foreach ($filesToDelete as $file) {
                    $deleted = $this->fileServerService->deleteFile($file->path);
                    if ($deleted) {
                        $file->delete();
                    } else {
                        throw new \Exception("Failed to delete file: {$file->file_name}");
                    }
                }

                // --------------------
                // Upload new files
                // --------------------
                if ($request->hasFile('file')) {
                    $newFiles = is_array($request->file('file')) ? $request->file('file') : [$request->file('file')];
                    $folderPath = $this->getFolderPath($purchaseRequest);
                    $counter = $purchaseRequest->files()->count() + 1;

                    foreach ($newFiles as $file) {
                        if (!$file) continue;

                        $ext = strtoupper($file->getClientOriginalExtension());
                        $name = strtoupper(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
                        $unique = strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));
                        $index = str_pad($counter, 2, '0', STR_PAD_LEFT);

                        $safeName = preg_replace('/[^A-Z0-9_\-]/', '_', $name);
                        $fileName = strtoupper("{$purchaseRequest->reference_no}-{$unique}-{$index}-{$safeName}.{$ext}");

                        $result = $this->fileServerService->uploadFile($file, $folderPath, $fileName);

                        if (!$result) {
                            throw new \Exception("FAILED TO UPLOAD FILE: {$fileName}");
                        }

                        $this->storeDocuments($purchaseRequest, [$result]);
                        $counter++;
                    }
                }
                // --------------------
                // Update items (smart update)
                // --------------------
                $existingItemIds = $purchaseRequest->items()->pluck('id')->toArray();
                $submittedItemIds = collect($validated['items'])->pluck('id')->filter()->toArray();

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


    public function destroy(PurchaseRequest $purchaseRequest): JsonResponse
    {
        $this->authorize('delete', $purchaseRequest);

        try {
            DB::transaction(function () use ($purchaseRequest) {

                // --------------------
                // Delete files from FileServer
                // --------------------
                foreach ($purchaseRequest->files as $file) {
                    $deleted = $this->fileServerService->deleteFile($file->path);
                    if ($deleted) {
                        $file->delete();
                    }
                }

                // --------------------
                // Delete items and detach relations
                // --------------------
                foreach ($purchaseRequest->items as $item) {
                    $item->campuses()->detach();
                    $item->departments()->detach();
                    $item->delete();
                }

                // --------------------
                // Delete approvals
                // --------------------
                $purchaseRequest->approvals()->delete();

                // --------------------
                // Soft delete main purchase request
                // --------------------
                $purchaseRequest->delete();
            });

            return response()->json([
                'message' => 'Purchase request deleted successfully.',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to delete purchase request', [
                'id' => $purchaseRequest->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Failed to delete purchase request.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // ====================
    // Helpers
    // ====================

    private function validationRules(?PurchaseRequest $purchaseRequest = null): array
    {
        $rules = [
            'deadline_date' => 'nullable|date',
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
            'approvals.*.request_type' => 'required|string|in:approve,initial,check,verify',
            'existing_file_ids' => 'nullable|array',
            'existing_file_ids.*' => 'integer|exists:document_relations,id',
        ];

        // ✅ Only apply "after_or_equal:request_date" if creating
        if (!$purchaseRequest) {
            $rules['deadline_date'] .= '|after_or_equal:request_date';
        }

        return $rules;
    }

    private function getFolderPath(PurchaseRequest $purchaseRequest): string
    {
        $date = \Carbon\Carbon::parse($purchaseRequest->request_date);
        $year = $date->format('Y');
        $monthNumber = $date->format('m');
        $monthName = $date->format('M');

        return "PurchaseRequest/{$year}/{$monthNumber}-{$monthName}/{$purchaseRequest->reference_no}";
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
             $this->approvalService->storeApproval($approvalPayload);
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

    private function storeDocuments(PurchaseRequest $purchaseRequest, array $files)
    {
        foreach ($files as $file) {
            $purchaseRequest->files()->create([
                'document_name' => 'Purchase Request',
                'file_name' => $file['name'],
                'path' => $file['path'],
                'document_reference' => $purchaseRequest->reference_no,
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

    public function viewFile(PurchaseRequest $purchaseRequest)
    {
        $this->authorize('view', $purchaseRequest);

        $file = $purchaseRequest->files()->first();

        if (!$file || !$file->path) {
            abort(404, "File not found.");
        }

        try {
            // Use injected service
            return $this->fileServerService->streamFile($file->path);
        } catch (\Throwable $e) {
            Log::error("File stream failed: " . $e->getMessage());
            abort(404, "File not found or access denied.");
        }
    }

    private function canShowApprovalButton(int $documentId): array
    {
        try {
            $userId = auth()->id();
            if (!$userId) {
                return $this->approvalButtonResponse('User not authenticated.');
            }

            $approvals = Approval::where([
                'approvable_type' => PurchaseRequest::class,
                'approvable_id'   => $documentId,
            ])->orderBy('ordinal')->orderBy('id')->get();

            if ($approvals->isEmpty()) {
                return $this->approvalButtonResponse('No approvals configured.');
            }

            // Find the first pending approval for the current user
            $currentApproval = $approvals->firstWhere(function($a) use ($userId) {
                return $a->approval_status === 'Pending' && $a->responder_id === $userId;
            });

            if (!$currentApproval) {
                return $this->approvalButtonResponse('No pending approval assigned to current user.');
            }

            // Check all previous approvals (lower OR same ordinal but lower id)
            $previousApprovals = $approvals->filter(function($a) use ($currentApproval) {
                return ($a->ordinal < $currentApproval->ordinal) || 
                    ($a->ordinal === $currentApproval->ordinal && $a->id < $currentApproval->id);
            });

            // Block if any previous approval is Rejected
            if ($previousApprovals->contains(fn($a) => $a->approval_status === 'Rejected')) {
                return $this->approvalButtonResponse('A previous approval was rejected.');
            }

            // Block if any previous approval is Returned
            if ($previousApprovals->contains(fn($a) => $a->approval_status === 'Returned')) {
                return $this->approvalButtonResponse('A previous approval was returned.');
            }

            // Block if any previous approval is still Pending
            if ($previousApprovals->contains(fn($a) => $a->approval_status === 'Pending')) {
                return $this->approvalButtonResponse('Previous approval steps are not completed.');
            }

            return [
                'message' => 'Approval button available.',
                'showButton' => true,
                'requestType' => $currentApproval->request_type,
            ];

        } catch (\Exception $e) {
            Log::error('Failed to check approval button visibility', [
                'document_id' => $documentId,
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return $this->approvalButtonResponse('Failed to check approval button visibility');
        }
    }

    private function approvalButtonResponse(string $reason): array
    {
        return [
            'message' => "Approval button not available: {$reason}",
            'showButton' => false,
            'requestType' => null,
        ];
    }

    private function mapPurchaseRequestData(PurchaseRequest $purchaseRequest): array
    {
        $purchaseRequest->load([
            'items.campuses',
            'items.departments',
            'approvals.responder',
            'files',
        ]);

        $approvalButtonData = $this->canShowApprovalButton($purchaseRequest->id);

        return [
            'id' => $purchaseRequest->id,
            'deadline_date' => $purchaseRequest->deadline_date 
                ? Carbon::parse($purchaseRequest->deadline_date)->format('M d, Y') 
                : null,
            'purpose' => $purchaseRequest->purpose,
            'is_urgent' => $purchaseRequest->is_urgent,
            'creator_name' => $purchaseRequest->creator?->name,
            'creator_position' => $purchaseRequest->creator->defaultPosition()?->title,
            'creator_id_card' => $purchaseRequest->creator->card_number,
            'creator_profile_url' => $purchaseRequest->creator->profile_url,
            'creator_signature_url' => $purchaseRequest->creator->signature_url,
            'creator_department' => $purchaseRequest->creator->defaultDepartment()?->name,
            'creator_cellphone' => $purchaseRequest->creator->phone,
            'request_date' => $purchaseRequest->request_date 
                ? Carbon::parse($purchaseRequest->request_date)->format('M d, Y') 
                : null,
            'approval_status' => $purchaseRequest->approval_status,
            'reference_no' => $purchaseRequest->reference_no,
            'total_value_usd' => $purchaseRequest->items->where('currency', 'USD')->sum('total_price'),
            'total_value_khr' => $purchaseRequest->items->where('currency', 'KHR')->sum('total_price'),

            'items' => $purchaseRequest->items->map(fn($i) => [
                'product_id' => $i->product_id,
                'product_code' => $i->product->item_code,
                'product_description' => collect([optional($i->product->product)->name, $i->product->description, $i->description])->filter()->join(' '),
                'unit_name' => $i->product->product->unit->name,
                'quantity' => $i->quantity,
                'unit_price' => $i->unit_price,
                'currency' => $i->currency,
                'exchange_rate' => $i->exchange_rate,
                'campus_ids' => $i->campuses->pluck('id')->toArray(),
                'department_ids' => $i->departments->pluck('id')->toArray(),
                'campus_short_names' => $i->campuses->pluck('short_name')->implode(', '),
                'division_short_names' => $i->departments
                ->map(fn($d) => $d->division?->short_name)
                ->filter()
                ->unique()
                ->implode(', '),
                'department_short_names' => $i->departments->pluck('short_name')->implode(', '),
                'budget_code_ref' => $i->budgetCode->reference_no,
                'total_price' => $i->total_price,
                'total_price_usd' => $i->total_price_usd,
                'total_price_khr' => ($i->currency === 'KHR' && !empty($i->exchange_rate)) ? $i->total_price : null,
            ]),

            'approvals' => $this->approvalService->mapApprovals($purchaseRequest->approvals),
            'approval_button_data' => $approvalButtonData,

            'files' => $purchaseRequest->files->map(fn($f) => [
                'id' => $f->id,
                'name' => $f->file_name,
                'reference' => $f->document_reference,
                'url' => $f->url
            ]),
        ];
    }

}
