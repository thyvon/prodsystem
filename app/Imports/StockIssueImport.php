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
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class StockIssueImport implements ToCollection, WithHeadingRow
{
    public function collection(Collection $rows)
    {
        DB::transaction(function () use ($rows) {
            $user = Auth::user();
            $stockIssuesCache = []; // cache reference_no => StockIssue model

            foreach ($rows as $index => $row) {
                $row = collect($row)->map(fn($v) => is_string($v) ? trim($v) : $v)->toArray();

                // Skip empty rows
                if (empty($row['product_code'])) continue;

                // --- Excel date fix ---
                if (!empty($row['transaction_date']) && is_numeric($row['transaction_date'])) {
                    $row['transaction_date'] = Date::excelToDateTimeObject($row['transaction_date'])->format('Y-m-d');
                }

                // --- RELATIONSHIP MAPPING ---
                $stockRequestId = !empty($row['stock_request_no'])
                    ? StockRequest::where('reference_no', $row['stock_request_no'])->value('id')
                    : null;

                $warehouseId = Warehouse::where('name', $row['warehouse_name'] ?? '')->value('id');

                // Auto-create or get requested_by user
                $requestedByName = $row['requested_by_name'] ?? null;
                $requestedById = null;

                if ($requestedByName) {
                    $parts = explode(' ', $requestedByName);
                    $firstName = strtolower($parts[0]);
                    $lastName  = strtolower(end($parts));
                    $email = $firstName . '.' . $lastName . '@mjqeducation.edu.kh';

                    $requestedBy = User::firstOrCreate(
                        ['name' => $requestedByName], // search by full name
                        [
                            'email' => $email,
                            'password' => bcrypt('password123'), // default password
                            'is_active' => 1,
                        ]
                    );
                    $requestedById = $requestedBy->id;
                }


                $productId    = ProductVariant::where('item_code', $row['product_code'] ?? '')->value('id');
                $campusId     = Campus::where('short_name', $row['campus_short_name'] ?? '')->value('id');
                $departmentId = Department::where('short_name', $row['department_short_name'] ?? '')->value('id');

                // --- VALIDATION ---
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
                    'unit_price'       => $row['unit_price'] ?? null,
                    'campus_id'        => $campusId,
                    'department_id'    => $departmentId,
                    'item_remarks'     => $row['item_remarks'] ?? null,
                ], [
                    'transaction_date' => ['required', 'date', 'date_format:Y-m-d'],
                    'transaction_type' => ['required', 'string', 'max:50'],
                    'account_code'     => ['required', 'string', 'max:50'],
                    'reference_no'     => ['nullable', 'string', 'max:50'], // allow duplicates
                    'stock_request_id' => ['nullable', 'integer', 'exists:stock_requests,id'],
                    'warehouse_id'     => ['required', 'integer', 'exists:warehouses,id'],
                    'requested_by'     => ['nullable', 'integer', 'exists:users,id'],
                    'product_id'       => ['required', 'integer', 'exists:product_variants,id'],
                    'quantity'         => ['required', 'numeric', 'min:0.0000000001'],
                    'unit_price'       => ['required', 'numeric', 'min:0'],
                    'campus_id'        => ['required', 'integer', 'exists:campus,id'],
                    'department_id'    => ['required', 'integer', 'exists:departments,id'],
                ]);

                if ($validator->fails()) {
                    throw new \Exception(
                        "Row " . ($index + 2) . " failed validation: " .
                        implode(', ', $validator->errors()->all())
                    );
                }

                $validated = $validator->validated();

                // --- GET OR CREATE STOCK ISSUE ---
                $referenceNo = $validated['reference_no'] ?? null;
                if ($referenceNo && isset($stockIssuesCache[$referenceNo])) {
                    $stockIssue = $stockIssuesCache[$referenceNo];
                } else {
                    $stockIssue = StockIssue::create([
                        'transaction_date' => $validated['transaction_date'],
                        'transaction_type' => $validated['transaction_type'],
                        'account_code'     => $validated['account_code'],
                        'stock_request_id' => $validated['stock_request_id'] ?? null,
                        'warehouse_id'     => $validated['warehouse_id'],
                        'requested_by'     => $validated['requested_by'] ?? $user?->id,
                        'remarks'          => $row['remarks'] ?? null,
                        'reference_no'     => $referenceNo,
                        'created_by'       => $user?->id ?? 1,
                        'updated_by'       => $user?->id ?? 1,
                    ]);

                    if ($referenceNo) {
                        $stockIssuesCache[$referenceNo] = $stockIssue;
                    }
                }

                // --- CREATE ITEM ---
                StockIssueItem::create([
                    'stock_issue_id' => $stockIssue->id,
                    'product_id'     => $validated['product_id'],
                    'quantity'       => number_format($validated['quantity'], 10, '.', ''),
                    'unit_price'     => number_format($validated['unit_price'], 10, '.', ''),
                    'total_price'    => bcmul($validated['quantity'], $validated['unit_price'], 10),
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
