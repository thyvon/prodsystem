<?php

namespace App\Imports;

use App\Models\{
    WarehouseProduct,
    Warehouse,
    ProductVariant
};
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class WarehouseProductImport implements ToCollection, WithHeadingRow
{
    public function collection(Collection $rows)
    {
        DB::transaction(function () use ($rows) {

            foreach ($rows as $index => $row) {
                $row = collect($row)->map(fn($v) => is_string($v) ? trim($v) : $v)->toArray();

                // Check required fields
                if (empty($row['product_code'])) {
                    throw new \Exception("Row " . ($index + 2) . ": product_code is required.");
                }

                if (empty($row['warehouse_name'])) {
                    throw new \Exception("Row " . ($index + 2) . ": warehouse_name is required.");
                }

                // Lookup warehouse & product
                $warehouseId = Warehouse::where('name', $row['warehouse_name'])->value('id');
                $productId   = ProductVariant::where('item_code', $row['product_code'])->value('id');

                if (!$warehouseId) {
                    throw new \Exception("Row " . ($index + 2) . ": Warehouse '{$row['warehouse_name']}' not found.");
                }

                if (!$productId) {
                    throw new \Exception("Row " . ($index + 2) . ": Product '{$row['product_code']}' not found.");
                }

                // Find the warehouse product
                $warehouseProduct = WarehouseProduct::where('product_id', $productId)
                    ->where('warehouse_id', $warehouseId)
                    ->first();

                if (!$warehouseProduct) {
                    throw new \Exception("Row " . ($index + 2) . ": Warehouse product does not exist.");
                }

                // Validate row
                $validator = Validator::make([
                    'alert_quantity' => $row['alert_quantity'] ?? null,
                    'is_active'      => $row['is_active'] ?? 1,
                ], [
                    'alert_quantity' => ['required', 'numeric', 'min:0'],
                    'is_active'      => ['nullable', 'boolean'],
                ]);

                if ($validator->fails()) {
                    throw new \Exception(
                        "Row " . ($index + 2) . " failed validation: " .
                        implode(', ', $validator->errors()->all())
                    );
                }

                $validated = $validator->validated();

                // Update warehouse product
                $warehouseProduct->update([
                    'alert_quantity' => $validated['alert_quantity'],
                    'is_active'      => $validated['is_active'] ?? 1,
                ]);
            }
        });
    }
}
