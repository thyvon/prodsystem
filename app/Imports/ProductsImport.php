<?php

namespace App\Imports;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\MainCategory;
use App\Models\SubCategory;
use App\Models\UnitOfMeasure;
use App\Models\VariantAttribute;
use App\Models\VariantValue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class ProductsImport implements ToCollection, WithHeadingRow
{
    private array $errors = [];

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

            // Validation
            $validator = Validator::make($rowData, $this->rules(), $this->customValidationMessages());
            if ($validator->fails()) {
                $errors = implode('; ', collect($validator->errors()->all())->toArray());
                $this->errors[] = "Product code '{$itemCode}': {$errors}";
                continue;
            }

            // Related models
            $category = $categories->get($rowData['category']);
            $subCategory = $subCategories->get($rowData['sub_category']);
            $unit = $units->get($rowData['unit']);

            if (!$category || !$unit || ($rowData['sub_category'] && !$subCategory)) {
                $this->errors[] = "Product code '{$itemCode}': Invalid category, sub-category, or unit.";
                continue;
            }

            // Generate item code if missing
            $itemCode = $itemCode ?: $this->generateBaseItemCode($category->id);
            if ($existingProducts->has($itemCode)) {
                $this->errors[] = "Product code '{$itemCode}' already exists.";
                continue;
            }

            DB::beginTransaction();
            try {
                $product = Product::create([
                    'item_code' => $itemCode,
                    'name' => $rowData['name'],
                    'khmer_name' => $rowData['khmer_name'] ?? null,
                    'description' => $rowData['description'] ?? null,
                    'barcode' => $rowData['barcode'] ?? null,
                    'category_id' => $category->id,
                    'sub_category_id' => $subCategory?->id,
                    'unit_id' => $unit->id,
                    'manage_stock' => filter_var($rowData['manage_stock'] ?? true, FILTER_VALIDATE_BOOLEAN),
                    'is_active' => filter_var($rowData['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN),
                    'has_variants' => filter_var($rowData['has_variants'] ?? false, FILTER_VALIDATE_BOOLEAN),
                    'created_by' => auth()->id(),
                    'updated_by' => auth()->id(),
                ]);

                // Process variants
                if ($product->has_variants) {
                    foreach ($productRows as $index => $row) {
                        $variantValueIds = $this->parseVariantAttributes($row['variant_attributes'] ?? '', $variantAttributes);
                        $variantItemCode = $row['variant_item_code'] ?? $this->generateVariantItemCode($itemCode, $index + 1);

                        if ($existingVariants->has($variantItemCode) || in_array($variantItemCode, $usedVariantCodes)) {
                            $this->errors[] = "Variant code '{$variantItemCode}' already exists.";
                            continue;
                        }
                        $usedVariantCodes[] = $variantItemCode;

                        $variant = ProductVariant::create([
                            'product_id' => $product->id,
                            'item_code' => $variantItemCode,
                            'estimated_price' => (float)($row['variant_estimated_price'] ?? 0),
                            'average_price' => (float)($row['variant_average_price'] ?? 0),
                            'description' => $row['variant_description'] ?? null,
                            'is_active' => filter_var($row['variant_is_active'] ?? true, FILTER_VALIDATE_BOOLEAN),
                            'updated_by' => auth()->id(),
                        ]);

                        if (!empty($variantValueIds)) {
                            $variant->values()->sync($variantValueIds);
                        }
                    }
                } else {
                    // Single default variant
                    ProductVariant::create([
                        'product_id' => $product->id,
                        'item_code' => $itemCode,
                        'estimated_price' => (float)($rowData['variant_estimated_price'] ?? 0),
                        'average_price' => (float)($rowData['variant_average_price'] ?? 0),
                        'description' => $rowData['variant_description'] ?? $rowData['description'] ?? null,
                        'is_active' => true,
                        'updated_by' => auth()->id(),
                    ]);
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

    // --- Helpers ---

    private function rules(): array
    {
        return [
            'item_code' => ['nullable', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'khmer_name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'barcode' => ['nullable', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:255'],
            'sub_category' => ['nullable', 'string', 'max:255'],
            'unit' => ['required', 'string', 'max:255'],
            'manage_stock' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'has_variants' => ['nullable', 'boolean'],
            'variant_item_code' => ['nullable', 'string', 'max:255'],
            'variant_estimated_price' => ['nullable', 'numeric', 'min:0'],
            'variant_average_price' => ['nullable', 'numeric', 'min:0'],
            'variant_description' => ['nullable', 'string'],
            'variant_is_active' => ['nullable', 'boolean'],
            'variant_attributes' => ['nullable', 'string'],
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
        $pairs = array_map('trim', explode(',', $attributes));
        foreach ($pairs as $pair) {
            [$attrName, $valueName] = array_map('trim', explode(':', $pair, 2));
            if (!$attrName || !$valueName) continue;
            $attr = $variantAttributes->get($attrName);
            if (!$attr) continue;
            $value = $attr->values->firstWhere('value', $valueName);
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
