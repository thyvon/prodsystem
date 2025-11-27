<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\ApprovalService;

use App\Models\StockCount;
use App\Models\StockCountItem;

class StockCountController extends Controller
{

    protected $approvalService;

    public function __construct(
        ApprovalService $approvalService,
    ) {
        $this->middleware('auth'); // Ensure authentication for all methods
        $this->approvalService = $approvalService;
    }
    public function store(Request $request): JsonResponse
    {
        $validated = Validator::make(
            $request->all(),
            array_merge(
                $this->stockCountValidationRules(),
                $this->stockCountItemValidationRules(),
                [
                    'approvals' => 'required|array|min:1',
                    'approvals.*.user_id' => 'required|exists:users,id',
                    'approvals.*.request_type' => 'required|string|in:approve,initial',
                ]
            )
        )->validate();

        // Validate approval permissions
        foreach ($validated['approvals'] as $approval) {
            $user = User::find($approval['user_id']);
            $permission = "stockCount.{$approval['request_type']}";
            if (!$user->hasPermissionTo($permission)) {
                return response()->json([
                    'message' => "User ID {$approval['user_id']} does not have permission for {$approval['request_type']}.",
                ], 403);
            }
        }

        try {
            return DB::transaction(function () use ($validated) {

                // Generate reference number
                $referenceNo = $this->generateReferenceNo(
                    $validated['warehouse_id'],
                    $validated['transaction_date']
                );

                // Creator position
                $userPosition = auth()->user()->defaultPosition();
                if (!$userPosition) {
                    return response()->json(['message' => 'User does not have a default position set.'], 404);
                }

                // Create stock count
                $stockCount = StockCount::create([
                    'transaction_date' => $validated['transaction_date'],
                    'reference_no' => $referenceNo,
                    'warehouse_id' => $validated['warehouse_id'],
                    'remarks' => $validated['remarks'] ?? null,
                    'approval_status' => 'Pending',
                    'created_by' => auth()->id(),
                ]);

                // Create each stock count item using Eloquent create()
                foreach ($validated['items'] as $item) {
                    $stockCount->items()->create([
                        'product_id'        => $item['product_id'],
                        'ending_quantity'   => $item['ending_quantity'],
                        'counted_quantity'  => $item['counted_quantity'],
                        'remarks'           => $item['remarks'] ?? null,
                        'created_by'        => auth()->id(),
                    ]);
                }

                // Save approvals
                $this->storeApprovals($stockCount, $validated['approvals']);

                return response()->json([
                    'message' => 'Stock count created successfully.',
                    'data' => $stockCount->load('items.product', 'approvals.responder'),
                ], 201);
            });

        } catch (\Exception $e) {
            Log::error('Failed to create stock count', ['error' => $e->getMessage()]);
            return response()->json([
                'message' => 'Failed to create stock count.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    private function stockCountValidationRules()
    {
        return [
            'transaction_date' => 'required|date',
            'warehouse_id' => 'required|exists:warehouses,id',
            'remarks' => 'nullable|string|max:500',
        ];
    }

    private function stockCountItemValidationRules()
    {
        return [
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:product_variants,id',
            'items.*.ending_quantity' => 'required|numeric|min:0',
            'items.*.counted_quantity' => 'required|numeric|min:0',
            'items.*.remarks' => 'nullable|string|max:255',
        ];
    }

    private function generateReferenceNo(int $warehouseId, string $transactionDate): string
    {
        $warehouse = Warehouse::with('building.campus')->findOrFail($warehouseId);

        try {
            $date = \Carbon\Carbon::createFromFormat(self::DATE_FORMAT, $transactionDate);
            if (!$date || $date->format(self::DATE_FORMAT) !== $transactionDate) {
                throw new \InvalidArgumentException('Invalid date format. Expected ' . self::DATE_FORMAT . '.');
            }
        } catch (\Exception $e) {
            throw new \InvalidArgumentException('Invalid date format. Expected ' . self::DATE_FORMAT . '.', 0, $e);
        }

        $shortName = $warehouse->building?->campus?->short_name ?? 'WH';
        $monthYear = $date->format('my');
        $sequence = $this->getSequenceNumber($shortName, $monthYear);
        return "STC-{$shortName}-{$monthYear}-{$sequence}";
    }

    protected function storeApprovals(StockCount $stockCount, array $approvals)
    {
        foreach ($approvals as $approval) {
            $approvalData = [
                'approvable_type' => StockCount::class,
                'approvable_id' => $stockCount->id,
                'document_name' => 'Stock Count',
                'document_reference' => $stockCount->reference_no,
                'request_type' => $approval['request_type'],
                'approval_status' => 'Pending',
                'ordinal' => $this->getOrdinalForRequestType($approval['request_type']),
                'requester_id' => $stockCount->created_by,
                'responder_id' => $approval['user_id'],
                'position_id' => User::find($approval['user_id'])?->defaultPosition()?->id,
            ];
            $this->approvalService->storeApproval($approvalData);
        }
    }
}
