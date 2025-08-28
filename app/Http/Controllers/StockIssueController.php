<?php

namespace App\Http\Controllers;

use App\Models\StockIssue;
use App\Models\StockRequest;
use App\Models\StockIssueItem;
use App\Models\Approval;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Services\ApprovalService;


class StockIssueController extends Controller
{
    private const ALLOWED_SORT_COLUMNS = ['request_date', 'request_number', 'created_at', 'updated_at', 'created_by', 'updated_by'];
    private const DEFAULT_SORT_COLUMN = 'created_at';
    private const DEFAULT_SORT_DIRECTION = 'desc';
    private const DEFAULT_LIMIT = 10;
    private const MAX_LIMIT = 1000;
    private const DATE_FORMAT = 'Y-m-d';

    protected $approvalService;

    public function __construct(ApprovalService $approvalService)
    {
        $this->approvalService = $approvalService;
    }

    public function index()
    {
        $this->authorize('viewAny', StockIssue::class);
        return view('Inventory.stockIssue.index');
    }

    public function create()
    {
        $this->authorize('create', StockIssue::class);
        $stockRequest = StockRequest::with(['StockRequestItems.product', 'warehouse'])
        ->where('approval_status', 'Approved')
        ->get();
        return view('Inventory.stockIssue.create', compact('stockRequest'));
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', StockIssue::class);

        // Validate the request
        $validated = Validator::make($request->all(), array_merge(
            $this->stockIssueValidationRule(),
            $this->stockIssueItemValidationRule(),
            [
                'approvals' => 'required|array',
                'approvals.*.id' => 'required|exists:users,id',
                'approvals.*.status' => 'required|in:approved,rejected',
            ]
        ))->validate();

        // Fetch the StockRequest and verify its approval status
        $stockRequest = StockRequest::with('stockRequestItems')->findOrFail($validated['stock_request_id']);

        // Check approval permissions
        foreach ($validated['approvals'] as $approval) {
            $user = User::findOrFail($approval['id']);
            $permission = "stockIssue.{$approval['request_type']}";
            if (!$user->hasPermissionTo($permission)) {
                return response()->json([
                    'message' => "User ID: {$user->id} does not have permission to {$permission}",
                ], 403);
            }
        }

        try {
            return DB::transaction(function () use ($validated, $stockRequest) {
                // Generate reference number using StockRequest's warehouse_id
                $referenceNo = $this->generateReferenceNo($stockRequest->warehouse_id, $validated['transaction_date']);
                $userPosition = auth()->user()->defaultPosition();

                // Create StockIssue using warehouse_id from StockRequest
                $stockIssue = StockIssue::create([
                    'transaction_date' => $validated['transaction_date'],
                    'reference_no' => $referenceNo,
                    'stock_request_id' => $validated['stock_request_id'],
                    'warehouse_id' => $stockRequest->warehouse->id,
                    'remarks' => $validated['remarks'] ?? null,
                    'created_by' => auth()->id() ?? 1,
                    'position_id' => $userPosition->id,
                    'approval_status' => 'Pending',
                ]);

                // Validate and prepare items
                $items = array_map(function ($item) use ($stockIssue, $stockRequest) {
                    return [
                        'stock_issue_id' => $stockIssue->id,
                        'stock_request_item_id' => $item['stock_request_item_id'],
                        'product_id' => $item['product_id'],
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['unit_price'],
                        'remarks' => $item['remarks'] ?? null,
                        'created_by' => auth()->id() ?? 1,
                    ];
                }, $validated['items']);

                StockIssueItem::insert($items);
                $this->storeApprovals($stockIssue, $validated['approvals']);

                return response()->json([
                    'message' => 'Stock Issue created successfully.',
                    'data' => $stockIssue->load('stockIssueItems', 'approvals.responder', 'stockRequest'),
                ], 201);
            });
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create stock issue.', ['error' => $e->getMessage()]);
            return response()->json([
                'message' => 'Failed to create stock issue',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function edit(StockIssue $stockIssue)
    {
        $this->authorize('update', $stockIssue);

        try{
            $user = auth()->user();
            $stockIssue = $stockIssue->load([
                'stockIssueItems.productVariant.product.unit',
                'warehouse',
                'approvals.responder'
            ]);

            $stockIssueData = [
                'id' => $stockIssue->id,
                'stock_request_id' => $stockIssue->stock_request_id,
                'transaction_date' => $stockIssue->transaction_date,
                'reference_no' => $stockIssue->reference_no,
                'warehouse_id' => $stockIssue->warehouse_id,
                'remarks' => $stockIssue->remarks,
                'created_by' => $stockIssue->createdBy,
                'position_id' => $stockIssue->position_id,
                'approval_status' => $stockIssue->approval_status,
                'items' => $stockIssue->stockIssueItems->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'stock_request_item_id' => $item->stock_request_item_id,
                        'product_id' => $item->product_id,
                        'product_name' => $item->productVariant->product->name,
                        'variant' => $item->productVariant->variant_name,
                        'unit' => $item->productVariant->product->unit->name,
                        'quantity' => $item->quantity,
                        'unit_price' => $item->unit_price,
                        'remarks' => $item->remarks,
                    ];
                })->toArray(),

                'approvals' => $stockIssue->approvals->map(function ($approval) {
                    return [
                        'id' => $approval->id,
                        'user_id' => $approval->responder_id,
                        'position_id' => $approval->position_id,
                        'request_type' => $approval->request_type,
                    ];
                })->toArray(),
            ];

            return view('Inventory.stockIssue.form',compact(
                'stockIssue',
                'stockIssueData'
            ));

        }catch (\Exception $e) {
            Log::error('Error fetching stock issue for editing', [
                'error_message' => $e->getMessage(),
                'stock_issue_id' => $stockIssue->id,
            ]);

            return response()->view('errors.500', [
                'message' => 'Failed to fetch stock issue',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function update(Request $request, StockIssue $stockIssue): JsonResponse
    {
        $this->authorize('update', $stockIssue);

        // Validate the request
        $validated = Validator::make($request->all(), array_merge(
            $this->stockIssueValidationRule(),
            $this->stockIssueItemValidationRule(),
            [
                'approvals' => 'required|array',
                'approvals.*.id' => 'required|exists:users,id',
                'approvals.*.status' => 'required|in:approved,rejected',
            ]
        ))->validate();

        // Fetch the StockRequest and verify its approval status
        $stockRequest = StockRequest::with('stockRequestItems')->findOrFail($validated['stock_request_id']);

        // Check approval permissions
        foreach ($validated['approvals'] as $approval) {
            $user = User::findOrFail($approval['id']);
            $permission = "stockIssue.{$approval['request_type']}";
            if (!$user->hasPermissionTo($permission)) {
                return response()->json([
                    'message' => "User ID: {$user->id} does not have permission to {$permission}",
                ], 403);
            }
        }

        try {
            return DB::transaction(function () use ($validated, $stockIssue, $stockRequest) {
                $userPosition = auth()->user()->defaultPosition();
                if (!$userPosition) {
                    return response()->json([
                        'message' => 'No default position assigned to this user.',
                    ], 404);
                }
                // Update StockIssue details
                $stockIssue->update([
                    'transaction_date' => $validated['transaction_date'],
                    'remarks' => $validated['remarks'] ?? null,
                    'updated_by' => auth()->id() ?? 1,
                    'approval_status' => 'Pending',
                ]);

                // Remove existing items and insert new ones
                StockIssueItem::where('stock_issue_id', $stockIssue->id)->delete();

                $items = array_map(function ($item) use ($stockIssue) {
                    return [
                        'stock_issue_id' => $stockIssue->id,
                        'stock_request_item_id' => $item['stock_request_item_id'],
                        'product_id' => $item['product_id'],
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['unit_price'],
                        'remarks' => $item['remarks'] ?? null,
                        'created_by' => auth()->id() ?? 1,
                    ];
                }, $validated['items']);

                StockIssueItem::insert($items);
            });

        } catch (\Exception $e) {
            Log::error('Error updating stock issue', [
                'error_message' => $e->getMessage(),
                'stock_issue_id' => $stockIssue->id,
            ]);

            return response()->json([
                'message' => 'Failed to update stock issue',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
};
