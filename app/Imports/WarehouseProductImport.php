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

                // Find existing warehouse product OR create new
                $warehouseProduct = WarehouseProduct::firstOrNew([
                    'product_id' => $productId,
                    'warehouse_id' => $warehouseId,
                ]);

                // Validate row
                $validator = Validator::make([
                    'alert_quantity'             => $row['alert_quantity'] ?? null,
                    'is_active'                  => $row['is_active'] ?? 1,
                    'order_leadtime_days'        => $row['order_leadtime_days'] ?? null,
                    'stock_out_forecast_days'    => $row['stock_out_forecast_days'] ?? null,
                    'target_inv_turnover_days'   => $row['target_inv_turnover_days'] ?? null,
                ], [
                    'alert_quantity'             => ['required', 'numeric', 'min:0'],
                    'is_active'                  => ['nullable', 'boolean'],
                    'order_leadtime_days'        => ['nullable', 'integer', 'min:0'],
                    'stock_out_forecast_days'    => ['nullable', 'integer', 'min:0'],
                    'target_inv_turnover_days'   => ['nullable', 'integer', 'min:0'],
                ]);

                if ($validator->fails()) {
                    throw new \Exception(
                        "Row " . ($index + 2) . " failed validation: " .
                        implode(', ', $validator->errors()->all())
                    );
                }

                $validated = $validator->validated();

                // Fill and save (will create new if it doesn't exist)
                // Fill attributes and save. Then ensure the related product variant is loaded
                $warehouseProduct->fill([
                    'alert_quantity'             => $validated['alert_quantity'] ?? 0,
                    'is_active'                  => $validated['is_active'] ?? 1,
                    'order_leadtime_days'        => $validated['order_leadtime_days'] ?? 0,
                    'stock_out_forecast_days'    => $validated['stock_out_forecast_days'] ?? 0,
                    'target_inv_turnover_days'   => $validated['target_inv_turnover_days'] ?? 0,
                ]);

                $warehouseProduct->save();
                // Load the related ProductVariant so consumers have the product data after create/update
                $warehouseProduct->load('variant');
            }
        });
    }
}
