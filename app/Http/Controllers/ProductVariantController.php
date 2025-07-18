<?php

namespace App\Http\Controllers;

use App\Models\VariantAttribute;
use App\Models\VariantValue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\JsonResponse;

class ProductVariantController extends Controller
{
    /**
     * Display the variant attributes index view.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        $this->authorize('viewAny', VariantAttribute::class);
        return view('Products.Variant.index');
    }

    /**
     * Get paginated product variant attributes with their values.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getProductVariantAttributes(Request $request): JsonResponse
    {
        $this->authorize('viewAny', VariantAttribute::class);

        $query = VariantAttribute::with(['values' => function ($query) {
            $query->select('id', 'variant_attribute_id', 'value', 'is_active');
        }]);

        if ($search = $request->input('search')) {
            $query->where('name', 'like', "%{$search}%");
        }

        $allowedSortColumns = ['name', 'ordinal', 'is_active', 'created_at'];
        $sortColumn = $request->input('sortColumn', 'created_at');
        $sortDirection = strtolower($request->input('sortDirection', 'desc'));
        $sortColumn = in_array($sortColumn, $allowedSortColumns) ? $sortColumn : 'created_at';
        $sortDirection = in_array($sortDirection, ['asc', 'desc']) ? $sortDirection : 'desc';
        $query->orderBy($sortColumn, $sortDirection);

        $limit = max(1, (int) $request->input('limit', 10));
        $productVariantAttributes = $query->paginate($limit);

        $data = $productVariantAttributes->getCollection()->map(function ($attribute) {
            return [
                'id' => $attribute->id,
                'name' => $attribute->name,
                'ordinal' => $attribute->ordinal,
                'is_active' => (bool) $attribute->is_active,
                'created_at' => $attribute->created_at?->toDateTimeString(),
                'values' => $attribute->values->map(function ($value) {
                    return [
                        'id' => $value->id,
                        'value' => $value->value,
                        'is_active' => (bool) $value->is_active,
                    ];
                })->toArray(),
            ];
        })->toArray();

        return response()->json([
            'data' => $data,
            'recordsTotal' => $productVariantAttributes->total(),
            'recordsFiltered' => $productVariantAttributes->total(),
            'draw' => (int) $request->input('draw', 1),
        ]);
    }

    /**
     * Validation rules for product variant attributes and values.
     *
     * @param int|null $productVariantAttributeId
     * @return array
     */
    private function validationRules(?int $productVariantAttributeId = null): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                'unique:variant_attributes,name' . ($productVariantAttributeId ? ',' . $productVariantAttributeId : ''),
            ],
            'ordinal' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['integer', 'in:0,1'],
            'values' => ['sometimes', 'array'],
            'values.*.value' => [
                'required',
                'string',
                'max:255',
                'distinct',
                function ($attribute, $value, $fail) use ($productVariantAttributeId) {
                    $exists = VariantValue::where('value', $value)
                        ->where('variant_attribute_id', $productVariantAttributeId ?? 0)
                        ->exists();
                    if ($exists) {
                        $fail("The $attribute already exists for this attribute.");
                    }
                    $existsAcross = VariantValue::where('value', $value)
                        ->where('variant_attribute_id', '!=', $productVariantAttributeId ?? 0)
                        ->exists();
                    if ($existsAcross) {
                        $fail("The $attribute has already been taken by another attribute.");
                    }
                },
            ],
            'values.*.is_active' => ['integer', 'in:0,1'],
        ];
    }

    /**
     * Validation rules for adding values to an existing attribute.
     *
     * @param int $productVariantAttributeId
     * @return array
     */
    private function valueValidationRules(int $productVariantAttributeId): array
    {
        return [
            'value' => ['sometimes', 'string', 'max:255', function ($attribute, $value, $fail) use ($productVariantAttributeId) {
                $exists = VariantValue::where('value', $value)
                    ->where('variant_attribute_id', $productVariantAttributeId)
                    ->exists();
                if ($exists) {
                    $fail("The $attribute already exists for this attribute.");
                }
                $existsAcross = VariantValue::where('value', $value)
                    ->where('variant_attribute_id', '!=', $productVariantAttributeId)
                    ->exists();
                if ($existsAcross) {
                    $fail("The $attribute has already been taken by another attribute.");
                }
            }],
            'values' => ['sometimes', 'array', 'min:1'],
            'values.*.value' => ['required', 'string', 'max:255', function ($attribute, $value, $fail) use ($productVariantAttributeId) {
                $exists = VariantValue::where('value', $value)
                    ->where('variant_attribute_id', $productVariantAttributeId)
                    ->exists();
                if ($exists) {
                    $fail("The $attribute already exists for this attribute.");
                }
                $existsAcross = VariantValue::where('value', $value)
                    ->where('variant_attribute_id', '!=', $productVariantAttributeId)
                    ->exists();
                if ($existsAcross) {
                    $fail("The $attribute has already been taken by another attribute.");
                }
            }],
            'values.*.is_active' => ['integer', 'in:0,1'],
        ];
    }

    /**
     * Store a new product variant attribute with its values.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', VariantAttribute::class);

        $validator = Validator::make($request->all(), $this->validationRules());
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }
        $validated = $validator->validated();

        DB::beginTransaction();
        try {
            $productVariantAttribute = VariantAttribute::create([
                'name' => $validated['name'],
                'ordinal' => $validated['ordinal'] ?? VariantAttribute::max('ordinal') + 1,
                'is_active' => $validated['is_active'] ?? 1,
            ]);

            if (!empty($validated['values'])) {
                $values = array_map(function ($valueData) use ($productVariantAttribute) {
                    return [
                        'variant_attribute_id' => $productVariantAttribute->id,
                        'value' => $valueData['value'],
                        'is_active' => $valueData['is_active'] ?? 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }, $validated['values']);
                VariantValue::insert($values);
            }

            $productVariantAttribute->load(['values' => function ($query) {
                $query->select('id', 'variant_attribute_id', 'value', 'is_active');
            }]);

            DB::commit();
            return response()->json([
                'message' => 'Product Variant Attribute created successfully.',
                'data' => [
                    'id' => $productVariantAttribute->id,
                    'name' => $productVariantAttribute->name,
                    'ordinal' => $productVariantAttribute->ordinal,
                    'is_active' => (bool) $productVariantAttribute->is_active,
                    'created_at' => $productVariantAttribute->created_at?->toDateTimeString(),
                    'values' => $productVariantAttribute->values->map(function ($value) {
                        return [
                            'id' => $value->id,
                            'value' => $value->value,
                            'is_active' => (bool) $value->is_active,
                        ];
                    })->toArray(),
                ],
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to create product variant attribute.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Fetch a product variant attribute for editing.
     *
     * @param VariantAttribute $productVariantAttribute
     * @return JsonResponse
     */
    public function edit(VariantAttribute $productVariantAttribute): JsonResponse
    {
        $this->authorize('update', $productVariantAttribute);

        $productVariantAttribute->load(['values' => function ($query) {
            $query->select('id', 'variant_attribute_id', 'value', 'is_active');
        }]);

        return response()->json([
            'data' => [
                'id' => $productVariantAttribute->id,
                'name' => $productVariantAttribute->name,
                'ordinal' => $productVariantAttribute->ordinal,
                'is_active' => (bool) $productVariantAttribute->is_active,
                'created_at' => $productVariantAttribute->created_at?->toDateTimeString(),
                'values' => $productVariantAttribute->values->map(function ($value) {
                    return [
                        'id' => $value->id,
                        'value' => $value->value,
                        'is_active' => (bool) $value->is_active,
                    ];
                })->toArray(),
            ],
        ]);
    }

    /**
     * Update a product variant attribute and its values.
     *
     * @param Request $request
     * @param VariantAttribute $productVariantAttribute
     * @return JsonResponse
     */
    public function update(Request $request, VariantAttribute $productVariantAttribute): JsonResponse
    {
        $this->authorize('update', $productVariantAttribute);

        $validator = Validator::make($request->all(), $this->validationRules($productVariantAttribute->id));
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }
        $validated = $validator->validated();

        DB::beginTransaction();
        try {
            $productVariantAttribute->update([
                'name' => $validated['name'],
                'ordinal' => $validated['ordinal'] ?? VariantAttribute::max('ordinal') + 1,
                'is_active' => $validated['is_active'] ?? 1,
            ]);

            if (isset($validated['values'])) {
                $existingValueIds = $productVariantAttribute->values()->pluck('id')->toArray();
                $newValueIds = [];

                foreach ($validated['values'] as $valueData) {
                    $valueId = $valueData['id'] ?? null;
                    $valuePayload = [
                        'value' => $valueData['value'],
                        'is_active' => $valueData['is_active'] ?? 1,
                        'updated_at' => now(),
                    ];

                    if ($valueId) {
                        $productVariantAttribute->values()->where('id', $valueId)->update($valuePayload);
                        $newValueIds[] = $valueId;
                    } else {
                        $newValue = $productVariantAttribute->values()->create(array_merge($valuePayload, [
                            'variant_attribute_id' => $productVariantAttribute->id,
                            'created_at' => now(),
                        ]));
                        $newValueIds[] = $newValue->id;
                    }
                }

                $valuesToDelete = array_diff($existingValueIds, $newValueIds);
                $productVariantAttribute->values()->whereIn('id', $valuesToDelete)->delete();
            }

            $productVariantAttribute->load(['values' => function ($query) {
                $query->select('id', 'variant_attribute_id', 'value', 'is_active');
            }]);

            DB::commit();
            return response()->json([
                'message' => 'Product Variant Attribute updated successfully.',
                'data' => [
                    'id' => $productVariantAttribute->id,
                    'name' => $productVariantAttribute->name,
                    'ordinal' => $productVariantAttribute->ordinal,
                    'is_active' => (bool) $productVariantAttribute->is_active,
                    'created_at' => $productVariantAttribute->created_at?->toDateTimeString(),
                    'values' => $productVariantAttribute->values->map(function ($value) {
                        return [
                            'id' => $value->id,
                            'value' => $value->value,
                            'is_active' => (bool) $value->is_active,
                        ];
                    })->toArray(),
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to update product variant attribute.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Add new values to an existing product variant attribute.
     *
     * @param Request $request
     * @param VariantAttribute $productVariantAttribute
     * @return JsonResponse
     */
    public function addValues(Request $request, VariantAttribute $productVariantAttribute): JsonResponse
    {
        $this->authorize('update', $productVariantAttribute);

        $validator = Validator::make($request->all(), $this->valueValidationRules($productVariantAttribute->id));
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }
        $validated = $validator->validated();

        DB::beginTransaction();
        try {
            if (isset($validated['value'])) {
                VariantValue::create([
                    'variant_attribute_id' => $productVariantAttribute->id,
                    'value' => $validated['value'],
                    'is_active' => $request->input('is_active', 1),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } elseif (isset($validated['values'])) {
                $values = array_map(function ($valueData) use ($productVariantAttribute) {
                    return [
                        'variant_attribute_id' => $productVariantAttribute->id,
                        'value' => $valueData['value'],
                        'is_active' => $valueData['is_active'] ?? 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }, $validated['values']);
                VariantValue::insert($values);
            }

            $productVariantAttribute->load(['values' => function ($query) {
                $query->select('id', 'variant_attribute_id', 'value', 'is_active');
            }]);

            DB::commit();
            return response()->json([
                'message' => 'Value(s) added to Product Variant Attribute successfully.',
                'data' => [
                    'id' => $productVariantAttribute->id,
                    'name' => $productVariantAttribute->name,
                    'ordinal' => $productVariantAttribute->ordinal,
                    'is_active' => (bool) $productVariantAttribute->is_active,
                    'created_at' => $productVariantAttribute->created_at?->toDateTimeString(),
                    'values' => $productVariantAttribute->values->map(function ($value) {
                        return [
                            'id' => $value->id,
                            'value' => $value->value,
                            'is_active' => (bool) $value->is_active,
                        ];
                    })->toArray(),
                ],
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to add values to product variant attribute.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a product variant attribute and its values.
     *
     * @param VariantAttribute $productVariantAttribute
     * @return JsonResponse
     */
    public function destroy(VariantAttribute $productVariantAttribute): JsonResponse
    {
        $this->authorize('delete', $productVariantAttribute);

        DB::beginTransaction();
        try {
            $productVariantAttribute->values()->delete();
            $productVariantAttribute->delete();

            DB::commit();
            return response()->json([
                'message' => 'Product Variant Attribute and its values deleted successfully.',
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            DB::rollBack();
            if ($e->getCode() === '23000') {
                return response()->json([
                    'message' => 'Cannot delete this Product Variant Attribute because it is being used by one or more products.',
                ], 409);
            }
            return response()->json([
                'message' => 'Failed to delete product variant attribute.',
                'error' => $e->getMessage(),
            ], 500);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'An unexpected error occurred while deleting the product variant attribute.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Fetch all product variant attributes with values for ProductModal.vue.
     *
     * @return JsonResponse
     */
    public function getAttributesWithValues(): JsonResponse
    {
        $this->authorize('viewAny', VariantAttribute::class);

        $attributes = VariantAttribute::with(['values' => function ($query) {
            $query->select('id', 'variant_attribute_id', 'value', 'is_active');
        }])->get();

        $data = $attributes->map(function ($attribute) {
            return [
                'id' => $attribute->id,
                'name' => $attribute->name,
                'ordinal' => $attribute->ordinal,
                'is_active' => (bool) $attribute->is_active,
                'created_at' => $attribute->created_at?->toDateTimeString(),
                'values' => $attribute->values->map(function ($value) {
                    return [
                        'id' => $value->id,
                        'value' => $value->value,
                        'is_active' => (bool) $value->is_active,
                    ];
                })->toArray(),
            ];
        })->toArray();

        return response()->json([
            'data' => $data,
        ]);
    }
}