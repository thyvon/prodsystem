<?php

namespace App\Imports;

use App\Models\ProductVariant;
use App\Models\BudgetItem;
use App\Models\Campus;
use App\Models\Department;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class PurchaseItemImport implements ToCollection, WithHeadingRow
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

        // Preload ProductVariants
        $itemCodes = $rows->pluck('item_code')->unique()->filter()->values();
        $variants = ProductVariant::whereIn('item_code', $itemCodes)->get()->keyBy('item_code');

        // Preload BudgetItems by reference_no
        $budgetRefs = $rows->pluck('budget_code_ref')->unique()->filter()->values();
        $budgetItems = BudgetItem::whereIn('reference_no', $budgetRefs)->get()->keyBy('reference_no');

        // Preload campuses and departments by short_name
        $allCampusShortNames = collect([]);
        $allDeptShortNames = collect([]);

        foreach ($rows as $row) {
            if (!empty($row['campus_names'])) {
                $allCampusShortNames = $allCampusShortNames->merge(explode(',', $row['campus_names']));
            }
            if (!empty($row['department_names'])) {
                $allDeptShortNames = $allDeptShortNames->merge(explode(',', $row['department_names']));
            }
        }

        $allCampusShortNames = $allCampusShortNames->map(fn($v) => trim($v))->unique();
        $allDeptShortNames = $allDeptShortNames->map(fn($v) => trim($v))->unique();

        $campuses = Campus::whereIn('short_name', $allCampusShortNames)->get()->keyBy(fn($c) => strtolower($c->short_name));
        $departments = Department::whereIn('short_name', $allDeptShortNames)->get()->keyBy(fn($d) => strtolower($d->short_name));

        $processedRows = [];

        foreach ($rows as $index => $row) {
            $rowData = array_map(fn($v) => is_string($v) ? trim($v) : $v, $row->toArray());

            $rowData['quantity'] = (float) ($rowData['quantity'] ?? 0);
            $rowData['unit_price'] = isset($rowData['unit_price']) ? (float) $rowData['unit_price'] : 0;
            $rowData['currency'] = $rowData['currency'] ?? 'USD';
            $rowData['exchange_rate'] = isset($rowData['exchange_rate']) ? (float) $rowData['exchange_rate'] : 1;
            $rowData['budget_code_ref'] = $rowData['budget_code_ref'] ?? null;

            $validator = Validator::make($rowData, [
                'item_code' => 'required|string',
                'quantity' => 'required|numeric|min:0.01',
                'unit_price' => 'required|numeric|min:0',
                'currency' => 'required|string',
                'budget_code_ref' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                $this->data['errors'][] = "Row " . ($index + 2) . ": " . json_encode($validator->errors()->toArray());
                continue;
            }

            $itemCode = $rowData['item_code'];
            $variant = $variants[$itemCode] ?? null;

            if (!$variant) {
                $this->data['errors'][] = "Row " . ($index + 2) . ": Product variant not found for item code: $itemCode";
                continue;
            }

            $budget = $budgetItems[$rowData['budget_code_ref']] ?? null;
            $budget_id = $budget ? $budget->id : null;

            // Map campus_ids
            $campus_ids = [];
            if (!empty($rowData['campus_names'])) {
                foreach (explode(',', $rowData['campus_names']) as $name) {
                    $name = strtolower(trim($name));
                    if (isset($campuses[$name])) $campus_ids[] = $campuses[$name]->id;
                }
            }

            // Map department_ids
            $department_ids = [];
            if (!empty($rowData['department_names'])) {
                foreach (explode(',', $rowData['department_names']) as $name) {
                    $name = strtolower(trim($name));
                    if (isset($departments[$name])) $department_ids[] = $departments[$name]->id;
                }
            }

            // ✅ Always add a new row, no quantity merging
            $this->data['items'][] = [
                'product_id' => $variant->id,
                'product_code' => $variant->item_code,
                'product_description' => $variant->description,
                'unit_name' => $variant->product->unit->name,
                'quantity' => $rowData['quantity'],
                'unit_price' => $rowData['unit_price'],
                'currency' => $rowData['currency'],
                'exchange_rate' => $rowData['exchange_rate'],
                'description' => $rowData['remarks'] ?? null,
                'budget_code_id' => $budget_id,
                'campus_ids' => $campus_ids,
                'department_ids' => $department_ids,
            ];
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
