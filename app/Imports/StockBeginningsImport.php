<?php

namespace App\Imports;

use App\Models\ProductVariant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class StockBeginningsImport implements ToCollection, WithHeadingRow
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
            $rowKey = md5(json_encode($row->toArray()));
            if (isset($processedRows[$rowKey])) {
                continue;
            }
            $processedRows[$rowKey] = true;

            $rowData = array_map(function ($value) {
                return is_string($value) ? trim($value) : $value;
            }, $row->toArray());

            $rowData['quantity'] = (float) $rowData['quantity'];
            $rowData['unit_price'] = (float) $rowData['unit_price'];

            $validator = Validator::make($rowData, [
                'item_code' => 'required|string',
                'quantity' => 'required|numeric|min:0.01',
                'unit_price' => 'required|numeric|min:0',
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

            $this->data['items'][] = [
                'product_id' => $product->id,
                'item_code' => $itemCode,
                'description'      => $product->product->name . ' ' . $product->description,
                'unit_name' => $product->product->unit->name,
                'quantity' => $rowData['quantity'],
                'unit_price' => round($rowData['unit_price'], 15),
                'remarks' => $rowData['remarks'] ?? null,
            ];
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
