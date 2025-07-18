<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\VariantAttribute;
use App\Models\VariantValue;
use App\Models\MainCategory;
use App\Models\SubCategory;
use App\Models\UnitOfMeasure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Controller for managing products with variants, including soft deletes and user tracking.
 */
class ProductController extends Controller
{
    /**
     * Display the product management view.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        $this->authorize('viewAny', Product::class);
        return view('Products.index');
    }

    /**
     * Validation rules for product and variants.
     *
     * @param int|null $productId
     * @param array $variantIds
     * @return array
     */
    private function validationRules($productId = null, $variantIds = [])
    {
        $existingVariantCodes = ProductVariant::pluck('item_code')->toArray();
        return [
            'item_code' => [
                'nullable',
                'string',
                'max:255',
                'unique:products,item_code' . ($productId ? ',' . $productId : ''),
            ],
            'name' => 'required|string|max:255',
            'khmer_name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'has_variants' => 'boolean',
            'barcode' => 'nullable|string|max:255',
            'category_id' => 'required|exists:main_categories,id',
            'sub_category_id' => 'nullable|exists:sub_categories,id',
            'unit_id' => 'required|exists:unit_of_measures,id',
            'manage_stock' => 'boolean',
            'image' => 'nullable|file|mimes:jpg,jpeg,png,webp|max:2048',
            'is_active' => 'boolean',
            'variants' => 'array|nullable',
            'variants.*.id' => 'nullable|exists:product_variants,id',
            'variants.*.item_code' => [
                'required',
                'string',
                'max:255',
                function ($attribute, $value, $fail) use ($variantIds, $existingVariantCodes) {
                    $index = (int) explode('.', $attribute)[1];
                    $variantId = $variantIds[$index] ?? null;
                    if (in_array($value, $existingVariantCodes) && (!$variantId || ProductVariant::where('id', '!=', $variantId)->where('item_code', $value)->exists())) {
                        $fail('The variant item code has already been taken.');
                    }
                }
            ],
            'variants.*.estimated_price' => 'required|numeric|min:0', // Made required
            'variants.*.average_price' => 'required|numeric|min:0',   // Made required
            'variants.*.description' => 'nullable|string',
            'variants.*.image' => 'nullable|file|mimes:jpg,jpeg,png,webp|max:2048',
            'variants.*.variant_value_ids' => 'array',
            'variants.*.variant_value_ids.*' => 'exists:variant_values,id',
            'variants.*.is_active' => 'integer|in:0,1',
        ];
    }

    /**
     * Retrieve paginated products with search and sorting.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getProducts(Request $request)
    {
        $this->authorize('viewAny', Product::class);

        $validated = $request->validate([
            'search' => 'nullable|string|max:255',
            'sortColumn' => 'nullable|string|in:name,khmer_name,description,created_at,updated_at',
            'sortDirection' => 'nullable|string|in:asc,desc',
            'limit' => 'nullable|integer|min:1|max:100',
            'draw' => 'nullable|integer',
        ]);

        $query = Product::with(['category', 'subCategory', 'unit', 'updatedBy', 'deletedBy']);

        if ($search = $validated['search'] ?? $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('khmer_name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $allowedSortColumns = ['name', 'khmer_name', 'description', 'created_at', 'updated_at'];
        $sortColumn = $validated['sortColumn'] ?? $request->input('sortColumn', 'created_at');
        $sortDirection = $validated['sortDirection'] ?? $request->input('sortDirection', 'desc');

        $sortColumn = in_array($sortColumn, $allowedSortColumns) ? $sortColumn : 'created_at';
        $sortDirection = in_array(strtolower($sortDirection), ['asc', 'desc']) ? $sortDirection : 'desc';

        $query->orderBy($sortColumn, $sortDirection);

        $limit = max(1, min(100, (int) ($validated['limit'] ?? $request->input('limit', 10))));
        $products = $query->paginate($limit);

        $data = $products->getCollection()->map(function (Product $product) {
            return [
                'id' => $product->id,
                'name' => $product->name,
                'khmer_name' => $product->khmer_name,
                'description' => $product->description,
                'image' => $product->image,
                'has_variants' => (bool) $product->has_variants,
                'is_active' => (bool) $product->is_active,
                'category_id' => $product->category_id,
                'category_name' => $product->category ? $product->category->name : null,
                'sub_category_id' => $product->sub_category_id,
                'sub_category_name' => $product->subCategory ? $product->subCategory->name : null,
                'unit_id' => $product->unit_id,
                'unit_name' => $product->unit ? $product->unit->name : null,
                'created_at' => $product->created_at?->toDateTimeString(),
                'updated_at' => $product->updated_at?->toDateTimeString(),
                'updated_by' => $product->updatedBy ? $product->updatedBy->name : null,
                'image_url' => $product->image ? asset('storage/' . $product->image) : null,
            ];
        });

        return response()->json([
            'data' => $data->all(),
            'recordsTotal' => $products->total(),
            'recordsFiltered' => $products->total(),
            'draw' => (int) ($validated['draw'] ?? $request->input('draw', 1)),
        ]);
    }

    /**
     * Store a new product with variants.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $this->authorize('create', Product::class);

        $validated = $request->validate($this->validationRules());

        DB::beginTransaction();

        try {
            $imagePath = $this->handleImageUpload($request, 'image', 'products');

            $baseItemCode = $validated['item_code'] ?? $this->generateBaseItemCode();

            $product = Product::create([
                'item_code' => $baseItemCode,
                'name' => $validated['name'],
                'khmer_name' => $validated['khmer_name'] ?? null,
                'description' => $validated['description'] ?? null,
                'has_variants' => $validated['has_variants'] ?? false,
                'barcode' => $validated['barcode'] ?? null,
                'category_id' => $validated['category_id'],
                'sub_category_id' => $validated['sub_category_id'] ?? null,
                'unit_id' => $validated['unit_id'],
                'manage_stock' => $validated['manage_stock'] ?? true,
                'image' => $imagePath,
                'is_active' => $validated['is_active'] ?? true,
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);

            if (!empty($validated['has_variants']) && $validated['has_variants'] && !empty($validated['variants'])) {
                // Product has variants, create each variant
                foreach ($validated['variants'] as $index => $variant) {
                    $variantImagePath = $this->handleImageUpload($request, "variants.$index.image", 'variants');
                    $variantItemCode = $variant['item_code'] ?? $this->generateVariantItemCode($baseItemCode, $index + 1);

                    $createdVariant = ProductVariant::create([
                        'product_id' => $product->id,
                        'item_code' => $variantItemCode,
                        'estimated_price' => $variant['estimated_price'],
                        'average_price' => $variant['average_price'],
                        'description' => $variant['description'] ?? null,
                        'image' => $variantImagePath,
                        'is_active' => $variant['is_active'] ?? 1,
                        'updated_by' => auth()->id(),
                    ]);

                    if (!empty($variant['variant_value_ids'])) {
                        $createdVariant->values()->sync($variant['variant_value_ids']);
                    }
                }
            } else {
                // No variants - create one default variant
                $variantData = $validated['variants'][0] ?? [];
                ProductVariant::create([
                    'product_id' => $product->id,
                    'item_code' => $baseItemCode,
                    'estimated_price' => $variantData['estimated_price'] ?? null,
                    'average_price' => $variantData['average_price'] ?? null,
                    'description' => $variantData['description'] ?? $validated['description'] ?? null,
                    'image' => $imagePath,
                    'is_active' => $variantData['is_active'] ?? $validated['is_active'] ?? 1,
                    'updated_by' => auth()->id(),
                ]);
            }

            DB::commit();

            return response()->json(['message' => 'Product created successfully'], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating product', [
                'error_message' => $e->getMessage(),
                'product_id' => $product->id ?? null,
            ]);
            return response()->json([
                'message' => 'Failed to create product',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Retrieve a product for editing.
     *
     * @param \App\Models\Product $product
     * @return \Illuminate\Http\JsonResponse
     */
    public function edit(Product $product)
    {
        $this->authorize('update', $product);

        try {
            $product->load(['variants.values.attribute', 'category', 'subCategory', 'unit', 'updatedBy', 'deletedBy']);
            $product->image_url = $product->image ? asset('storage/' . $product->image) : null;

            foreach ($product->variants as $variant) {
                $variant->image_url = $variant->image ? asset('storage/' . $variant->image) : null;
            }

            return response()->json(['product' => $product]);
        } catch (\Exception $e) {
            Log::error('Error fetching product for editing', [
                'error_message' => $e->getMessage(),
                'product_id' => $product->id,
            ]);
            return response()->json([
                'message' => 'Failed to fetch product',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update an existing product.
     *
     * @param \Illuminate\Http\Request $request
     * @param \App\Models\Product $product
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, Product $product)
    {
        $this->authorize('update', $product);

        $variantIds = [];
        if ($request->has('variants')) {
            foreach ($request->input('variants') as $i => $variant) {
                $variantIds[$i] = $variant['id'] ?? null;
            }
        }

        $validated = $request->validate($this->validationRules($product->id, $variantIds));

        DB::beginTransaction();

        try {
            $imagePath = $product->image;
            if ($request->has('remove_image') && $request->input('remove_image') === true) {
                if ($product->image) {
                    Storage::disk('public')->delete($product->image);
                }
                $imagePath = null;
            } else {
                $imagePath = $this->handleImageUpload($request, 'image', 'products', $product->image);
            }

            $product->update([
                'item_code' => $validated['item_code'] ?? $product->item_code,
                'name' => $validated['name'],
                'khmer_name' => $validated['khmer_name'] ?? null,
                'description' => $validated['description'] ?? null,
                'has_variants' => $validated['has_variants'] ?? false,
                'barcode' => $validated['barcode'] ?? null,
                'category_id' => $validated['category_id'],
                'sub_category_id' => $validated['sub_category_id'] ?? null,
                'unit_id' => $validated['unit_id'],
                'manage_stock' => $validated['manage_stock'] ?? true,
                'image' => $imagePath,
                'is_active' => $validated['is_active'] ?? true,
                'updated_by' => auth()->id(),
            ]);

            if (!empty($validated['has_variants']) && $validated['has_variants'] && !empty($validated['variants'])) {
                // Update or create variants
                $existingVariantIds = $product->variants->pluck('id')->toArray();
                $updatedVariantIds = array_filter(array_column($validated['variants'], 'id'));

                // Delete variants not in the updated list
                ProductVariant::where('product_id', $product->id)
                    ->whereNotIn('id', $updatedVariantIds)
                    ->each(function ($variant) {
                        if ($variant->image) {
                            Storage::disk('public')->delete($variant->image);
                        }
                        $variant->values()->detach();
                        $variant->delete();
                    });

                // Update or create variants
                foreach ($validated['variants'] as $index => $variant) {
                    $variantImagePath = $this->handleImageUpload($request, "variants.$index.image", 'variants', $variant['id'] ? ProductVariant::find($variant['id'])->image : null);
                    $variantItemCode = $variant['item_code'] ?? $this->generateVariantItemCode($product->item_code, $index + 1);

                    $variantData = [
                        'product_id' => $product->id,
                        'item_code' => $variantItemCode,
                        'estimated_price' => $variant['estimated_price'],
                        'average_price' => $variant['average_price'],
                        'description' => $variant['description'] ?? null,
                        'image' => $variantImagePath,
                        'is_active' => $variant['is_active'] ?? 1,
                        'updated_by' => auth()->id(),
                    ];

                    $createdVariant = $variant['id']
                        ? ProductVariant::find($variant['id'])->update($variantData)
                        : ProductVariant::create($variantData);

                    if ($variant['id']) {
                        $createdVariant = ProductVariant::find($variant['id']);
                    } else {
                        $createdVariant = ProductVariant::where('product_id', $product->id)
                            ->where('item_code', $variantItemCode)
                            ->first();
                    }

                    if (!empty($variant['variant_value_ids'])) {
                        $createdVariant->values()->sync($variant['variant_value_ids']);
                    } else {
                        $createdVariant->values()->detach();
                    }
                }
            } else {
                // No variants - update or create default variant
                $variantData = $validated['variants'][0] ?? [];
                $variant = $product->variants->first() ?? ProductVariant::create([
                    'product_id' => $product->id,
                    'item_code' => $product->item_code,
                    'updated_by' => auth()->id(),
                ]);

                $variant->update([
                    'estimated_price' => $variantData['estimated_price'] ?? null,
                    'average_price' => $variantData['average_price'] ?? null,
                    'description' => $variantData['description'] ?? $validated['description'] ?? null,
                    'image' => $imagePath,
                    'is_active' => $variantData['is_active'] ?? $validated['is_active'] ?? 1,
                    'updated_by' => auth()->id(),
                ]);
            }

            DB::commit();

            return response()->json([
                'message' => 'Product updated successfully',
                'product' => [
                    'id' => $product->id,
                    'image' => $product->image,
                    'image_url' => $product->image ? asset('storage/' . $product->image) : null,
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating product', [
                'error_message' => $e->getMessage(),
                'product_id' => $product->id,
            ]);
            return response()->json([
                'message' => 'Failed to update product',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Soft delete a product and its variants.
     *
     * @param \App\Models\Product $product
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Product $product)
    {
        $this->authorize('delete', $product);

        DB::beginTransaction();

        try {
            foreach ($product->variants as $variant) {
                if ($variant->image) {
                    Storage::disk('public')->delete($variant->image);
                }
                $variant->values()->detach();
                $variant->update(['deleted_by' => auth()->id()]);
                $variant->delete();
            }

            if ($product->image) {
                Storage::disk('public')->delete($product->image);
            }

            $product->update(['deleted_by' => auth()->id()]);
            $product->delete();

            DB::commit();

            Log::info('Product soft deleted successfully', ['product_id' => $product->id]);

            return response()->json(['message' => 'Product soft deleted successfully']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error soft deleting product', [
                'error_message' => $e->getMessage(),
                'product_id' => $product->id,
            ]);
            return response()->json([
                'message' => 'Failed to soft delete product',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Restore a soft-deleted product and its variants.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function restore($id)
    {
        $this->authorize('restore', Product::class);

        DB::beginTransaction();

        try {
            $product = Product::withTrashed()->findOrFail($id);
            $product->restore();
            $product->variants()->withTrashed()->restore();

            DB::commit();

            Log::info('Product restored successfully', ['product_id' => $product->id]);

            return response()->json(['message' => 'Product restored successfully']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error restoring product', [
                'error_message' => $e->getMessage(),
                'product_id' => $id,
            ]);
            return response()->json([
                'message' => 'Failed to restore product',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Permanently delete a product and its variants.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function forceDelete($id)
    {
        $this->authorize('forceDelete', Product::class);

        DB::beginTransaction();

        try {
            $product = Product::withTrashed()->findOrFail($id);

            foreach ($product->variants()->withTrashed()->get() as $variant) {
                if ($variant->image) {
                    Storage::disk('public')->delete($variant->image);
                }
                $variant->values()->detach();
                $variant->forceDelete();
            }

            if ($product->image) {
                Storage::disk('public')->delete($product->image);
            }

            $product->forceDelete();

            DB::commit();

            Log::info('Product permanently deleted', ['product_id' => $product->id]);

            return response()->json(['message' => 'Product permanently deleted']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error permanently deleting product', [
                'error_message' => $e->getMessage(),
                'product_id' => $id,
            ]);
            return response()->json([
                'message' => 'Failed to permanently delete product',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Retrieve soft-deleted products.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function trashed(Request $request)
    {
        $this->authorize('viewAny', Product::class);

        $validated = $request->validate([
            'search' => 'nullable|string|max:255',
            'sortColumn' => 'nullable|string|in:name,khmer_name,description,created_at,updated_at,deleted_at',
            'sortDirection' => 'nullable|string|in:asc,desc',
            'limit' => 'nullable|integer|min:1|max:100',
            'draw' => 'nullable|integer',
        ]);

        $query = Product::onlyTrashed()->with(['category', 'subCategory', 'unit', 'updatedBy', 'deletedBy']);

        if ($search = $validated['search'] ?? $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('khmer_name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $allowedSortColumns = ['name', 'khmer_name', 'description', 'created_at', 'updated_at', 'deleted_at'];
        $sortColumn = $validated['sortColumn'] ?? $request->get('sortColumn', 'deleted_at');
        $sortDirection = $validated['sortDirection'] ?? $request->get('sortDirection', 'desc');

        $sortColumn = in_array($sortColumn, $allowedSortColumns) ? $sortColumn : 'deleted_at';
        $sortDirection = in_array(strtolower($sortDirection), ['asc', 'desc']) ? $sortDirection : 'desc';

        $query->orderBy($sortColumn, $sortDirection);
        $limit = max(1, min(100, (int) ($validated['limit'] ?? $request->get('limit', 10))));
        $products = $query->paginate($limit);

        return response()->json([
            'data' => $products->items(),
            'recordsTotal' => $products->total(),
            'recordsFiltered' => $products->total(),
            'draw' => (int) ($validated['draw'] ?? $request->get('draw', 1)),
        ]);
    }

    /**
     * Handle image upload and deletion.
     *
     * @param \Illuminate\Http\Request $request
     * @param string $field
     * @param string $path
     * @param string|null $existingImage
     * @return string|null
     */
    protected function handleImageUpload(Request $request, $field, $path, $existingImage = null)
    {
        if ($request->hasFile($field) && $request->file($field)->isValid()) {
            if ($existingImage) {
                Storage::disk('public')->delete($existingImage);
            }
            return $request->file($field)->store($path, 'public');
        }
        return $existingImage;
    }
    /**
     * Generate a unique item code for a product.
     *
     * @return string
     */
    protected function generateBaseItemCode()
    {
        $nextNumber = 1;
        do {
            $itemCode = 'ITEM-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
            $exists = Product::where('item_code', $itemCode)->exists();
            $nextNumber++;
        } while ($exists);

        return $itemCode;
    }

    /**
     * Generate a unique item code for a variant.
     *
     * @param string $baseItemCode
     * @param int $index
     * @return string
     */
    protected function generateVariantItemCode($baseItemCode, $index)
    {
        $itemCode = $baseItemCode . '-' . str_pad($index, 2, '0', STR_PAD_LEFT);
        $exists = ProductVariant::where('item_code', $itemCode)->exists();
        $suffix = $index;
        while ($exists) {
            $suffix++;
            $itemCode = $baseItemCode . '-' . str_pad($suffix, 2, '0', STR_PAD_LEFT);
            $exists = ProductVariant::where('item_code', $itemCode)->exists();
        }
        return $itemCode;
    }
}