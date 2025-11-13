<?php

namespace App\Imports;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\MainCategory;
use App\Models\SubCategory;
use App\Models\UnitOfMeasure;
use App\Models\VariantAttribute;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class ProductsImport implements ToCollection, WithHeadingRow
{
    private array $errors = [];
    private int $createdCount = 0;
    private int $updatedCount = 0;

    public function collection(Collection $rows): void
    {
        if ($rows->isEmpty()) {
            $this->errors[] = 'Excel file is empty or has no valid rows.';
            return;
        }

        // Preload data
        $categoryNames = $rows->pluck('category')->unique()->filter()->values();
        $subCategoryNames = $rows->pluck('sub_category')->unique()->filter()->values();
        $unitNames = $rows->pluck('unit')->unique()->filter()->values();
        $itemCodes = $rows->pluck('item_code')->unique()->filter()->values();
        $variantItemCodes = $rows->pluck('variant_item_code')->unique()->filter()->values();

        $categories = MainCategory::whereIn('name', $categoryNames)->get()->keyBy('name');
        $subCategories = SubCategory::whereIn('name', $subCategoryNames)->get()->keyBy('name');
        $units = UnitOfMeasure::whereIn('name', $unitNames)->get()->keyBy('name');
        $existingProducts = Product::withTrashed()->whereIn('item_code', $itemCodes)->get()->keyBy('item_code');
        $existingVariants = ProductVariant::withTrashed()->whereIn('item_code', $variantItemCodes)->get()->keyBy('item_code');

        $variantAttributes = VariantAttribute::with('values')->get()->keyBy('name');
        $usedVariantCodes = [];
        $grouped = $rows->groupBy(fn($row) => trim($row['item_code'] ?? ''));

        foreach ($grouped as $itemCode => $productRows) {
            $firstRow = $productRows->first();
            $rowData = array_map(fn($v) => is_string($v) ? trim($v) : $v, $firstRow->toArray());

            // Validate
            $validator = Validator::make($rowData, $this->rules(), $this->customValidationMessages());
            if ($validator->fails()) {
                $this->errors[] = "Product code '{$itemCode}': " . implode('; ', $validator->errors()->all());
                continue;
            }

            $category = $categories->get($rowData['category']);
            $subCategory = $subCategories->get($rowData['sub_category']);
            $unit = $units->get($rowData['unit']);

            if (!$category || !$unit || ($rowData['sub_category'] && !$subCategory)) {
                $this->errors[] = "Product code '{$itemCode}': Invalid category, sub-category, or unit.";
                continue;
            }

            // Handle product create or update
            DB::beginTransaction();
            try {
                $product = $existingProducts->get($itemCode);

                $productData = [
                    'item_code' => $itemCode ?: $this->generateBaseItemCode($category->id),
                    'category_id' => $category->id,
                    'sub_category_id' => $subCategory?->id,
                    'unit_id' => $unit->id,
                    'updated_by' => auth()->id(),
                ];

                $fields = [
                    'name', 'khmer_name', 'description', 'barcode',
                    'manage_stock', 'is_active', 'has_variants'
                ];

                foreach ($fields as $field) {
                    if (!is_null($rowData[$field] ?? null)) {
                        $productData[$field] = $rowData[$field];
                    }
                }

                if ($product) {
                    $product->update($productData);
                    $this->updatedCount++;
                } else {
                    $productData['created_by'] = auth()->id();
                    $product = Product::create($productData);
                    $this->createdCount++;
                }

                // Handle variants
                if ($product->has_variants) {
                    foreach ($productRows as $index => $row) {
                        $variantValueIds = $this->parseVariantAttributes($row['variant_attributes'] ?? '', $variantAttributes);
                        $variantItemCode = $row['variant_item_code'] ?? $this->generateVariantItemCode($itemCode, $index + 1);

                        $variant = ProductVariant::withTrashed()
                            ->where('item_code', $variantItemCode)
                            ->first();

                        $variantData = [
                            'product_id' => $product->id,
                            'updated_by' => auth()->id(),
                        ];

                        $variantFields = [
                            'estimated_price' => (float)($row['variant_estimated_price'] ?? null),
                            'average_price' => (float)($row['variant_average_price'] ?? null),
                            'description' => $row['variant_description'] ?? null,
                            'is_active' => filter_var($row['variant_is_active'] ?? true, FILTER_VALIDATE_BOOLEAN),
                        ];

                        foreach ($variantFields as $key => $value) {
                            if (!is_null($value)) {
                                $variantData[$key] = $value;
                            }
                        }

                        if ($variant) {
                            $variant->update($variantData);
                        } else {
                            $variantData['item_code'] = $variantItemCode;
                            ProductVariant::create($variantData);
                        }

                        if (!empty($variantValueIds)) {
                            $variant?->values()->sync($variantValueIds);
                        }
                    }
                } else {
                    $variant = ProductVariant::where('item_code', $itemCode)->first();
                    $variantData = [
                        'product_id' => $product->id,
                        'item_code' => $itemCode,
                        'updated_by' => auth()->id(),
                    ];

                    if (!is_null($rowData['variant_estimated_price'] ?? null)) {
                        $variantData['estimated_price'] = (float)$rowData['variant_estimated_price'];
                    }
                    if (!is_null($rowData['variant_average_price'] ?? null)) {
                        $variantData['average_price'] = (float)$rowData['variant_average_price'];
                    }
                    if (!is_null($rowData['variant_description'] ?? null)) {
                        $variantData['description'] = $rowData['variant_description'];
                    }

                    if ($variant) {
                        $variant->update($variantData);
                    } else {
                        ProductVariant::create($variantData);
                    }
                }

                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                $this->errors[] = "Product code '{$itemCode}': Failed to import. Error: {$e->getMessage()}";
            }
        }
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getSummaryMessage(): string
    {
        $created = $this->createdCount;
        $updated = $this->updatedCount;
        return "Import completed successfully: {$created} new products created, {$updated} updated.";
    }

    // --- Helpers ---
    private function rules(): array
    {
        return [
            'item_code' => ['nullable', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:255'],
            'unit' => ['required', 'string', 'max:255'],
        ];
    }

    private function customValidationMessages(): array
    {
        return [
            'name.required' => "The name field is required.",
            'category.required' => "The category field is required.",
            'unit.required' => "The unit field is required.",
        ];
    }

    private function parseVariantAttributes(string $attributes, Collection $variantAttributes): array
    {
        if (!$attributes) return [];
        $ids = [];
        foreach (array_map('trim', explode(',', $attributes)) as $pair) {
            [$attrName, $valueName] = array_map('trim', explode(':', $pair, 2));
            $attr = $variantAttributes->get($attrName);
            $value = $attr?->values->firstWhere('value', $valueName);
            if ($value) $ids[] = $value->id;
        }
        return $ids;
    }

    private function generateBaseItemCode($categoryId): string
    {
        $mainCategory = MainCategory::find($categoryId);
        $short = $mainCategory ? strtoupper($mainCategory->short_name) : 'GEN';
        $num = 1;
        do {
            $code = 'PRO-' . $short . '-' . str_pad($num, 4, '0', STR_PAD_LEFT);
            $exists = Product::withTrashed()->where('item_code', $code)->exists();
            $num++;
        } while ($exists);
        return $code;
    }

    private function generateVariantItemCode(string $baseCode, int $index): string
    {
        $code = $baseCode . '-' . str_pad($index, 2, '0', STR_PAD_LEFT);
        $suffix = $index;
        while (ProductVariant::withTrashed()->where('item_code', $code)->exists()) {
            $suffix++;
            $code = $baseCode . '-' . str_pad($suffix, 2, '0', STR_PAD_LEFT);
        }
        return $code;
    }
}
