<?php

namespace App\Imports;

use App\Models\{
    StockIn,
    StockInItem,
    Supplier,
    Warehouse,
    ProductVariant,
    User
};
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class StockInImport implements ToCollection, WithHeadingRow
{
    public function collection(Collection $rows)
    {
        DB::transaction(function () use ($rows) {

            $user = Auth::user();
            $stockInCache = [];

            foreach ($rows as $index => $row) {
                $row = collect($row)->map(fn($v) => is_string($v) ? trim($v) : $v)->toArray();
                if (empty($row['product_code'])) continue;

                // Convert Excel date
                if (!empty($row['transaction_date']) && is_numeric($row['transaction_date'])) {
                    $row['transaction_date'] = Date::excelToDateTimeObject($row['transaction_date'])->format('Y-m-d');
                }

                // --- SUPPLIER LOOKUP + CREATE IF NOT FOUND ---
                $supplierName = $row['supplier_name'] ?? null;
                $supplierId = null;

                if ($supplierName) {
                    $supplierId = Supplier::where('name', $supplierName)->value('id');

                    if (!$supplierId) {
                        // Supplier does not exist → create new
                        $newSupplier = Supplier::create([
                            'name' => $supplierName,
                            'email' => null,           // optional
                            'phone' => null,           // optional
                            'address' => null,         // optional
                            'description' => null,     // optional
                            'is_active' => 1,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);

                        $supplierId = $newSupplier->id;
                    }
                }

                // Other relational lookups
                $warehouseId = Warehouse::where('name', $row['warehouse_name'] ?? '')->value('id');
                $productId   = ProductVariant::where('item_code', $row['product_code'])->value('id');

                // Lookup existing user for created_by
                $createdByName = $row['created_by_name'] ?? null;
                $createdById = $createdByName ? User::where('name', $createdByName)->value('id') : $user?->id ?? 1;

                // Validate
                $validator = Validator::make([
                    'transaction_date' => $row['transaction_date'] ?? null,
                    'transaction_type' => $row['transaction_type'] ?? null,
                    'invoice_no'       => $row['invoice_no'] ?? null,
                    'payment_terms'    => $row['payment_terms'] ?? null,
                    'reference_no'     => $row['reference_no'] ?? null,
                    'supplier_id'      => $supplierId,
                    'warehouse_id'     => $warehouseId,
                    'product_id'       => $productId,
                    'quantity'         => $row['quantity'] ?? null,
                    'unit_price'       => $row['unit_price'] ?? null,
                    'vat'              => $row['vat'] ?? 0,
                    'discount'         => $row['discount'] ?? 0,
                    'delivery_fee'     => $row['delivery_fee'] ?? 0,
                    'item_remarks'     => $row['item_remarks'] ?? null,
                ], [
                    'transaction_date' => ['required', 'date', 'date_format:Y-m-d'],
                    'transaction_type' => ['required', 'string', 'max:50'],
                    'invoice_no'       => ['nullable', 'string', 'max:100'],
                    'payment_terms'    => ['nullable', 'string', 'max:100'],
                    'reference_no'     => ['nullable', 'string', 'max:50'],
                    'supplier_id'      => ['required', 'integer', 'exists:suppliers,id'],
                    'warehouse_id'     => ['required', 'integer', 'exists:warehouses,id'],
                    'product_id'       => ['required', 'integer', 'exists:product_variants,id'],
                    'quantity'         => ['required', 'numeric', 'min:0.0000000001'],
                    'unit_price'       => ['required', 'numeric', 'min:0'],
                    'vat'              => ['numeric', 'min:0'],
                    'discount'         => ['numeric', 'min:0'],
                    'delivery_fee'     => ['numeric', 'min:0'],
                ]);

                if ($validator->fails()) {
                    throw new \Exception(
                        "Row " . ($index + 2) . " failed validation: " .
                        implode(', ', $validator->errors()->all())
                    );
                }

                $validated = $validator->validated();

                // Create or reuse Stock In main record
                $refNo = $validated['reference_no'] ?? null;

                if ($refNo && isset($stockInCache[$refNo])) {
                    $stockIn = $stockInCache[$refNo];
                } else {
                    $stockIn = StockIn::create([
                        'transaction_date' => $validated['transaction_date'],
                        'transaction_type' => $validated['transaction_type'],
                        'invoice_no'       => $validated['invoice_no'],
                        'payment_terms'    => $validated['payment_terms'],
                        'supplier_id'      => $validated['supplier_id'],
                        'warehouse_id'     => $validated['warehouse_id'],
                        'remarks'          => $row['remarks'] ?? null,
                        'reference_no'     => $refNo,
                        'created_by'       => $createdById,
                        'updated_by'       => $createdById,
                    ]);

                    if ($refNo) $stockInCache[$refNo] = $stockIn;
                }

                // Insert item
                StockInItem::create([
                    'stock_in_id'   => $stockIn->id,
                    'product_id'    => $validated['product_id'],
                    'quantity'      => number_format($validated['quantity'], 10, '.', ''),
                    'unit_price'    => number_format($validated['unit_price'], 10, '.', ''),
                    'vat'           => number_format($validated['vat'], 10, '.', ''),
                    'discount'      => number_format($validated['discount'], 10, '.', ''),
                    'delivery_fee'  => number_format($validated['delivery_fee'], 10, '.', ''),
                    'total_price'   => bcmul($validated['quantity'], $validated['unit_price'], 10),
                    'remarks'       => $row['item_remarks'] ?? null,
                    'updated_by'    => $createdById,
                    'deleted_by'    => null,
                ]);
            }
        });
    }
}
