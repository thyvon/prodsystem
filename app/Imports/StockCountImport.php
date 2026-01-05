<?php

namespace App\Imports;

use App\Models\ProductVariant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class StockCountImport implements ToCollection, WithHeadingRow
{
    private $data;

    public function __construct()
    {
        $this->data = [
            'items' => [],
            'errors' => [],
        ];
    }

    public function collection(Collection $rows): void
    {
        if ($rows->isEmpty()) {
            $this->data['errors'][] = 'Excel file is empty or has no valid rows.';
            return;
        }

        // Collect product codes and preload products
        $productCodes = $rows->pluck('product_code')->unique()->filter()->values();
        $products = ProductVariant::whereIn('item_code', $productCodes)->get()->keyBy('item_code');

        $processed = [];

        foreach ($rows as $index => $row) {

            // Trim values
            $rowData = array_map(
                fn($v) => is_string($v) ? trim($v) : $v,
                $row->toArray()
            );

            // Convert to numbers
            $rowData['counted_quantity'] = (float)($rowData['counted_quantity'] ?? 0);
            $rowData['unit_price'] = isset($rowData['unit_price']) ? (float)$rowData['unit_price'] : 0;

            // Validate
            $validator = Validator::make($rowData, [
                'product_code'     => 'required|string',
                'counted_quantity' => 'required|numeric|min:0',
                'unit_price'       => 'required|numeric|min:0',
                'remark'           => 'nullable|string|max:1000',
            ]);

            if ($validator->fails()) {
                $this->data['errors'][] = "Row " . ($index + 2) . ": " .
                    json_encode($validator->errors()->toArray());
                continue;
            }

            $productCode = $rowData['product_code'];
            $product = $products[$productCode] ?? null;

            if (!$product) {
                $this->data['errors'][] =
                    "Row " . ($index + 2) . ": Product not found for code: $productCode";
                continue;
            }

            // Merge duplicate product_code
            if (isset($processed[$productCode])) {
                $existing = &$this->data['items'][$processed[$productCode]];
                $existing['counted_quantity'] += $rowData['counted_quantity'];
                // Optional: if unit_price differs, you can choose to update or ignore
                $existing['unit_price'] = $rowData['unit_price']; 
            } else {
                $this->data['items'][] = [
                    'product_id'       => $product->id,
                    'product_code'     => $product->item_code,
                    'product_name'     => $product->product_name,
                    'description'      => $product->product->name . ' ' . $product->description,
                    'unit_name'        => $product->product->unit->name,
                    'unit_price'       => $rowData['unit_price'],
                    'counted_quantity' => $rowData['counted_quantity'],
                    'remark'           => $rowData['remark'] ?? null,
                ];

                $processed[$productCode] = count($this->data['items']) - 1;
            }
        }

        if (empty($this->data['items']) && empty($this->data['errors'])) {
            $this->data['errors'][] = 'No valid rows processed.';
        }
    }

    public function getData(): array
    {
        return $this->data;
    }
}
