<?php

namespace App\Imports;

use App\Models\Product;
use App\Models\MainCategory;
use App\Models\SubCategory;
use App\Models\UnitOfMeasure;
use App\Models\VariantAttribute;
use App\Models\VariantValue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class ProductsImport implements ToCollection, WithHeadingRow
{
    private $data;

    public function __construct()
    {
        $this->data = [
            'products' => [],
            'errors' => [],
        ];
    }

    public function collection(Collection $rows): void
    {
        if ($rows->isEmpty()) {
            $this->data['errors'][] = 'Excel file is empty or has no valid rows.';
            return;
        }

        // Preload related data
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

            // Cast numeric fields
            $rowData['variant_estimated_price'] = isset($rowData['variant_estimated_price']) ? (float) $rowData['variant_estimated_price'] : null;
            $rowData['variant_average_price'] = isset($rowData['variant_average_price']) ? (float) $rowData['variant_average_price'] : null;

            // Validate row
            $validator = Validator::make($rowData, $this->rules(), $this->customValidationMessages($index + 2));
            if ($validator->fails()) {
                $this->data['errors'][] = "Row " . ($index + 2) . ": " . json_encode($validator->errors()->toArray());
                continue;
            }

            // Only use existing related models
            $category = $this->getExistingCategory($rowData['category'], $categories);
            $subCategory = $this->getExistingSubCategory($rowData['sub_category'], $category ? $category->id : null, $subCategories);
            $unit = $this->getExistingUnit($rowData['unit'], $units);

            if (!$category) {
                $this->data['errors'][] = "Row " . ($index + 2) . ": Category '{$rowData['category']}' does not exist.";
                continue;
            }
            if (!$unit) {
                $this->data['errors'][] = "Row " . ($index + 2) . ": Unit '{$rowData['unit']}' does not exist.";
                continue;
            }
            if ($rowData['sub_category'] && !$subCategory) {
                $this->data['errors'][] = "Row " . ($index + 2) . ": Sub-category '{$rowData['sub_category']}' does not exist for category '{$rowData['category']}'.";
                continue;
            }

            $itemCode = $rowData['item_code'] ?? $this->generateBaseItemCode($category->id);
            if ($existingProducts->has($itemCode)) {
                $this->data['errors'][] = "Row " . ($index + 2) . ": Item code $itemCode already exists.";
                continue;
            }

            // Parse variant attributes (only existing)
            $variantValueIds = $this->parseExistingVariantAttributes($rowData['variant_attributes'] ?? null);

            // If variant attributes are required but not found, skip
            if (($rowData['has_variants'] ?? false) && ($rowData['variant_attributes'] ?? null) && empty($variantValueIds)) {
                $this->data['errors'][] = "Row " . ($index + 2) . ": Variant attributes do not match existing attributes/values.";
                continue;
            }

            // Prepare product data
            $productData = [
                'item_code' => $itemCode,
                'name' => $rowData['name'] ?? 'Unnamed Product',
                'khmer_name' => $rowData['khmer_name'] ?? null,
                'description' => $rowData['description'] ?? null,
                'barcode' => $rowData['barcode'] ?? null,
                'category_id' => $category->id,
                'sub_category_id' => $subCategory ? $subCategory->id : null,
                'unit_id' => $unit->id,
                'manage_stock' => filter_var($rowData['manage_stock'] ?? true, FILTER_VALIDATE_BOOLEAN),
                'is_active' => true, // Default to true to allow user review
                'has_variants' => filter_var($rowData['has_variants'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'variants' => [],
                'selected_attributes' => [],
            ];

            // Handle variants
            if ($productData['has_variants']) {
                if (empty($rowData['variant_attributes']) && empty($rowData['variant_item_code']) && 
                    !isset($rowData['variant_estimated_price']) && !isset($rowData['variant_average_price'])) {
                    $this->data['errors'][] = "Row " . ($index + 2) . ": At least one variant field (item code, attributes, or prices) must be provided when has_variants is true.";
                    continue;
                }

                $variantItemCode = $rowData['variant_item_code'] ?? $this->generateVariantItemCode($itemCode, 1, $productData['has_variants']);
                if ($existingVariants->has($variantItemCode)) {
                    $this->data['errors'][] = "Row " . ($index + 2) . ": Variant item code $variantItemCode already exists.";
                    continue;
                }

                $productData['variants'] = [
                    [
                        'id' => null,
                        'item_code' => $variantItemCode,
                        'estimated_price' => $rowData['variant_estimated_price'] ?? 0, // Default to 0
                        'average_price' => $rowData['variant_average_price'] ?? 0, // Default to 0
                        'description' => $rowData['variant_description'] ?? null,
                        'is_active' => filter_var($rowData['variant_is_active'] ?? true, FILTER_VALIDATE_BOOLEAN),
                        'image' => null,
                        'values' => array_map(function ($valueId) {
                            $value = VariantValue::find($valueId);
                            return [
                                'id' => $valueId,
                                'attribute' => $value && $value->attribute ? ['id' => $value->attribute->id] : null,
                            ];
                        }, $variantValueIds),
                    ],
                ];
                $productData['selected_attributes'] = $this->mapSelectedAttributes($variantValueIds);
            }

            $this->data['products'][] = $productData;
        }

        if (empty($this->data['products']) && empty($this->data['errors'])) {
            $this->data['errors'] = ['No valid products processed.'];
        }
    }

    /**
     * Validation rules.
     */
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

    /**
     * Custom validation messages.
     */
    private function customValidationMessages(int $rowIndex): array
    {
        return [
            'name.required' => "The name field is required in row $rowIndex.",
            'category.required' => "The category field is required in row $rowIndex.",
            'unit.required' => "The unit field is required in row $rowIndex.",
        ];
    }

    /**
     * Get a category from preloaded collection.
     */
    private function getExistingCategory(?string $name, Collection $categories): ?MainCategory
    {
        if (!$name) return null;
        return $categories->get($name);
    }

    /**
     * Get a sub-category from preloaded collection.
     */
    private function getExistingSubCategory(?string $name, ?int $categoryId, Collection $subCategories): ?SubCategory
    {
        if (!$name || !$categoryId) return null;
        $subCategory = $subCategories->get($name);
        if ($subCategory && $subCategory->main_category_id === $categoryId) {
            return $subCategory;
        }
        return null;
    }

    /**
     * Get a unit from preloaded collection.
     */
    private function getExistingUnit(?string $name, Collection $units): ?UnitOfMeasure
    {
        if (!$name) return null;
        return $units->get($name);
    }

    /**
     * Parse variant attributes (e.g., "Size:Small,Color:Red") using only existing attributes/values.
     */
    private function parseExistingVariantAttributes(?string $attributes): array
    {
        if (!$attributes) return [];

        $variantValueIds = [];
        $attributePairs = array_map('trim', explode(',', $attributes));

        foreach ($attributePairs as $pair) {
            [$attributeName, $valueName] = array_map('trim', explode(':', $pair, 2));
            if (!$attributeName || !$valueName) continue;

            $attribute = VariantAttribute::where('name', $attributeName)->first();
            if (!$attribute) continue;

            $value = VariantValue::where('variant_attribute_id', $attribute->id)
                ->where('value', $valueName)
                ->first();
            if (!$value) continue;

            $variantValueIds[] = $value->id;
        }

        return $variantValueIds;
    }

    /**
     * Map variant attributes for selected_attributes.
     */
    private function mapSelectedAttributes(array $variantValueIds): array
    {
        $selectedAttributes = [];
        foreach ($variantValueIds as $valueId) {
            $value = VariantValue::find($valueId);
            if ($value && $value->variant_attribute_id) {
                if (!isset($selectedAttributes[$value->variant_attribute_id])) {
                    $selectedAttributes[$value->variant_attribute_id] = [];
                }
                if (!in_array($valueId, $selectedAttributes[$value->variant_attribute_id])) {
                    $selectedAttributes[$value->variant_attribute_id][] = $valueId;
                }
            }
        }
        return $selectedAttributes;
    }

    /**
     * Generate a unique base item code for a product based on category.
     *
     * @param int $categoryId
     * @return string
     */
    protected function generateBaseItemCode($categoryId)
    {
        $mainCategory = MainCategory::find($categoryId);
        $shortName = $mainCategory ? strtoupper($mainCategory->short_name) : 'GEN';

        $nextNumber = 1;
        do {
            $itemCode = 'PRO-' . $shortName . '-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
            $exists = Product::withTrashed()->where('item_code', $itemCode)->exists();
            $nextNumber++;
        } while ($exists);

        return $itemCode;
    }

    /**
     * Generate a unique item code for a variant.
     *
     * @param string $baseItemCode
     * @param int $index
     * @param bool $hasVariants
     * @return string
     */
    protected function generateVariantItemCode($baseItemCode, $index, $hasVariants = true)
    {
        // If product has no variants, use the base item code for the variant
        if (!$hasVariants) {
            return $baseItemCode;
        }

        $itemCode = $baseItemCode . '-' . str_pad($index, 2, '0', STR_PAD_LEFT);
        $exists = ProductVariant::withTrashed()->where('item_code', $itemCode)->exists();
        $suffix = $index;
        while ($exists) {
            $suffix++;
            $itemCode = $baseItemCode . '-' . str_pad($suffix, 2, '0', STR_PAD_LEFT);
            $exists = ProductVariant::withTrashed()->where('item_code', $itemCode)->exists();
        }
        return $itemCode;
    }

    /**
     * Get the processed data.
     */
    public function getData(): array
    {
        return $this->data;
    }
}