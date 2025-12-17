<?php

namespace App\Imports;

use App\Models\{
    StockIssue,
    StockIssueItem,
    StockRequest,
    Warehouse,
    User,
    ProductVariant,
    Campus,
    Department
};
use App\Services\StockLedgerService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class StockIssueImport implements ToCollection, WithHeadingRow
{
    protected StockLedgerService $ledgerService;

    public function __construct(StockLedgerService $ledgerService = null)
    {
        $this->ledgerService = $ledgerService ?? new StockLedgerService();
    }

    public function collection(Collection $rows)
    {
        DB::transaction(function () use ($rows) {

            $user = Auth::user();
            $stockIssuesCache = [];

            // -----------------------
            // Preload all users once
            // -----------------------
            $existingUsers = User::all()->keyBy(fn($u) => strtolower($u->email));

            foreach ($rows as $index => $row) {

                $row = collect($row)->map(fn($v) => is_string($v) ? trim($v) : $v)->toArray();

                if (empty($row['product_code'])) continue;

                // Convert Excel numeric date to Y-m-d
                if (!empty($row['transaction_date']) && is_numeric($row['transaction_date'])) {
                    $row['transaction_date'] = Date::excelToDateTimeObject($row['transaction_date'])
                        ->format('Y-m-d');
                }

                // Lookup related IDs
                $stockRequestId = !empty($row['stock_request_no'])
                    ? StockRequest::where('reference_no', $row['stock_request_no'])->value('id')
                    : null;

                $warehouseId = !empty($row['warehouse_name'])
                    ? Warehouse::where('name', $row['warehouse_name'])->value('id')
                    : null;

                $productId = ProductVariant::where('item_code', $row['product_code'])->value('id');

                $campusId = !empty($row['campus_short_name'])
                    ? Campus::where('short_name', $row['campus_short_name'])->value('id')
                    : null;

                $departmentId = !empty($row['department_short_name'])
                    ? Department::where('short_name', $row['department_short_name'])->value('id')
                    : null;

                // -----------------------
                // Optimized requested_by user creation
                // -----------------------
                $requestedByName = $row['requested_by_name'] ?? null;
                $requestedById = null;

                if ($requestedByName) {
                    // Generate email from name
                    $parts = array_values(array_filter(explode(' ', strtolower($requestedByName))));
                    $email = strtolower("{$parts[0]}.{$parts[count($parts)-1]}@mjqeducation.edu.kh");

                    if (isset($existingUsers[$email])) {
                        $requestedById = $existingUsers[$email]->id;
                    } else {
                        // Optional: double-check DB just in case
                        $existingUser = User::where('email', $email)->first();
                        if ($existingUser) {
                            $requestedById = $existingUser->id;
                            $existingUsers[$email] = $existingUser;
                        } else {
                            $newUser = User::create([
                                'name' => $requestedByName,
                                'email' => $email,
                                'password' => bcrypt('password123'),
                                'is_active' => 1,
                            ]);
                            $requestedById = $newUser->id;
                            $existingUsers[$email] = $newUser; // add to cache
                        }
                    }
                }

                // -----------------------
                // Validation
                // -----------------------
                $validator = Validator::make([
                    'transaction_date' => $row['transaction_date'] ?? null,
                    'transaction_type' => $row['transaction_type'] ?? null,
                    'account_code'     => $row['account_code'] ?? null,
                    'reference_no'     => $row['reference_no'] ?? null,
                    'stock_request_id' => $stockRequestId,
                    'warehouse_id'     => $warehouseId,
                    'requested_by'     => $requestedById,
                    'product_id'       => $productId,
                    'quantity'         => $row['quantity'] ?? null,
                    'campus_id'        => $campusId,
                    'department_id'    => $departmentId,
                ], [
                    'transaction_date' => ['required', 'date', 'date_format:Y-m-d'],
                    'transaction_type' => ['required', 'string'],
                    'account_code'     => ['required'],
                    'reference_no'     => ['nullable', 'string'],
                    'stock_request_id' => ['nullable', 'integer', 'exists:stock_requests,id'],
                    'warehouse_id'     => ['required', 'integer', 'exists:warehouses,id'],
                    'requested_by'     => ['nullable', 'integer', 'exists:users,id'],
                    'product_id'       => ['required', 'integer', 'exists:product_variants,id'],
                    'quantity'         => ['required', 'numeric', 'min:0.0000001'],
                    'campus_id'        => ['required', 'integer', 'exists:campus,id'],
                    'department_id'    => ['required', 'integer', 'exists:departments,id'],
                ]);

                if ($validator->fails()) {
                    throw new \Exception(
                        "Row " . ($index + 2) . " validation error: " .
                        implode(', ', $validator->errors()->all())
                    );
                }

                $validated = $validator->validated();

                // Get average unit price from StockLedgerService
                $unitPrice = (float) $this->ledgerService->getAvgPrice(
                    $productId,
                    $validated['transaction_date']
                );

                if ($unitPrice <= 0) {
                    $unitPrice = 0;
                }

                // -----------------------
                // StockIssue creation
                // -----------------------
                $referenceNo = $validated['reference_no'] ?? null;

                if ($referenceNo && isset($stockIssuesCache[$referenceNo])) {
                    $stockIssue = $stockIssuesCache[$referenceNo];
                } else {
                    $stockIssue = StockIssue::create([
                        'transaction_date' => $validated['transaction_date'],
                        'transaction_type' => $validated['transaction_type'],
                        'account_code'     => $validated['account_code'],
                        'stock_request_id' => $validated['stock_request_id'],
                        'warehouse_id'     => $validated['warehouse_id'],
                        'requested_by'     => $validated['requested_by'] ?? ($user?->id),
                        'remarks'          => $row['remarks'] ?? null,
                        'reference_no'     => $referenceNo,
                        'created_by'       => $user?->id ?? 1,
                        'updated_by'       => $user?->id ?? 1,
                    ]);

                    if ($referenceNo) {
                        $stockIssuesCache[$referenceNo] = $stockIssue;
                    }
                }

                // -----------------------
                // StockIssueItem creation
                // -----------------------
                $qty = (float) $validated['quantity'];
                $unitPriceExcel = isset($row['unit_price']) ? (float) $row['unit_price'] : 0;
                $total = round($qty * $unitPrice, 15);

                StockIssueItem::create([
                    'stock_issue_id' => $stockIssue->id,
                    'product_id'     => $validated['product_id'],
                    'quantity'       => $qty,
                    'unit_price'     => $unitPriceExcel,
                    'total_price'    => $total,
                    'campus_id'      => $validated['campus_id'],
                    'department_id'  => $validated['department_id'],
                    'remarks'        => $row['item_remarks'] ?? null,
                    'created_by'     => $user?->id ?? 1,
                    'updated_by'     => $user?->id ?? 1,
                ]);
            }
        });
    }
}
