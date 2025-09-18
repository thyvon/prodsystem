<?php

namespace App\Imports;

use App\Models\ProductVariant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class StockTransfersImport implements ToCollection , WithHeadingRow
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

        // Preload all item codes
        $itemCodes = $rows->pluck('item_code')->unique()->filter()->values();
        $productVariants = ProductVariant::whereIn('item_code', $itemCodes)->get()->keyBy('item_code');

        $processedRows = [];

        foreach ($rows as $index => $row) {
            $rowData = array_map(function ($value) {
                return is_string($value) ? trim($value) : $value;
            }, $row->toArray());

            $rowData['quantity'] = (float) $rowData['quantity'];

            $validator = Validator::make($rowData, [
                'item_code' => 'required|string',
                'quantity' => 'required|numeric|min:0.01',
                'remarks' => 'nullable|string|max:1000',
            ]);

            if ($validator->fails()) {
                $this->data['errors'][] = "Row " . ($index + 2) . ": " . json_encode($validator->errors()->toArray());
                continue;
            }

            $itemCode = $rowData['item_code'];
            $product = $productVariants[$itemCode] ?? null;

            if (!$product) {
                $this->data['errors'][] = "Row " . ($index + 2) . ": Product variant not found for item code: $itemCode";
                continue;
            }

            // Sum quantity if item_code already exists
            if (isset($processedRows[$itemCode])) {
                $this->data['items'][$processedRows[$itemCode]]['quantity'] += $rowData['quantity'];
            } else {
                $this->data['items'][] = [
                    'product_id' => $product->id,
                    'item_code' => $itemCode,
                    'quantity' => $rowData['quantity'],
                    'remarks' => $rowData['remarks'] ?? null,
                ];
                // Store the index of the first occurrence
                $processedRows[$itemCode] = count($this->data['items']) - 1;
            }
        }

        if (empty($this->data['items']) && empty($this->data['errors'])) {
            $this->data['errors'][] = 'No valid rows processed.';
        }
    }


    /**
     * Get the processed data.
     */
    public function getData(): array
    {
        return $this->data;
    }
}
