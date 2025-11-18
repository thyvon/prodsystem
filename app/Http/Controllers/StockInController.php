<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\StockIn;
use App\Models\StockInItem;
use App\Models\ProductVariant;
use App\Models\Supplier;
use App\Models\Warehouse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class StockInController extends Controller
{

    public function form()
    {
        // $this->authorize('create', StockIn::class);
        return view('Inventory.stockIn.form');
    }
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', StockIn::class);

        // Validate request
        $validated = Validator::make(
            $request->all(),
            array_merge(
                $this->stockInValidationRule(),
                $this->stockInItemValidationRule()
            )
        )->validate();

        try {
            return DB::transaction(function () use ($validated) {

                $userId = auth()->id() ?? 1;

                // Generate reference_no if not provided
                $referenceNo = $validated['reference_no']
                    ?? $this->generateReferenceNo($validated['transaction_date']);

                // Create Stock In
                $stockIn = StockIn::create([
                    'transaction_date' => $validated['transaction_date'],
                    'reference_no'     => $referenceNo,
                    'transaction_type' => $validated['transaction_type'],
                    'invoice_no'       => $validated['invoice_no'] ?? null,
                    'payment_terms'    => $validated['payment_terms'] ?? null,
                    'supplier_id'      => $validated['supplier_id'],
                    'warehouse_id'     => $validated['warehouse_id'],
                    'remarks'          => $validated['remarks'] ?? null,
                    'created_by'       => $userId,
                    'updated_by'       => null,
                    'deleted_by'       => null,
                ]);

                // Prepare items
                $items = array_map(function ($item) use ($stockIn, $userId) {

                    $qty       = $item['quantity'];
                    $unitPrice = $item['unit_price'];

                    return [
                        'stock_in_id'  => $stockIn->id,
                        'product_id'   => $item['product_id'],
                        'quantity'     => $qty,
                        'unit_price'   => $unitPrice,
                        'vat'          => $item['vat'] ?? 0,
                        'discount'     => $item['discount'] ?? 0,
                        'delivery_fee' => $item['delivery_fee'] ?? 0,
                        'total_price'  => bcmul($qty, $unitPrice, 10),
                        'remarks'      => $item['remarks'] ?? null,
                        'updated_by'   => null,
                        'deleted_by'   => null,
                    ];
                }, $validated['items']);

                // Bulk insert items
                StockInItem::insert($items);

                return response()->json([
                    'message' => 'Stock In created successfully.',
                    'data'    => $stockIn->load('stockInItems'),
                ], 201);
            });

        } catch (\Exception $e) {
            Log::error('Failed to create Stock In', ['error' => $e->getMessage()]);

            return response()->json([
                'message' => 'Failed to create Stock In',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function update(Request $request, StockIn $stockIn): JsonResponse
    {
        $this->authorize('update', $stockIn);

        // Validate request
        $validated = Validator::make(
            $request->all(),
            array_merge(
                $this->stockInValidationRule($stockIn->id),
                $this->stockInItemValidationRule()
            )
        )->validate();

        try {
            return DB::transaction(function () use ($validated, $stockIn) {

                $userId = auth()->id() ?? 1;

                // Update Stock In
                $stockIn->update([
                    'transaction_date' => $validated['transaction_date'],
                    'reference_no'     => $validated['reference_no'] ?? $stockIn->reference_no,
                    'transaction_type' => $validated['transaction_type'],
                    'invoice_no'       => $validated['invoice_no'] ?? null,
                    'payment_terms'    => $validated['payment_terms'] ?? null,
                    'supplier_id'      => $validated['supplier_id'],
                    'warehouse_id'     => $validated['warehouse_id'],
                    'remarks'          => $validated['remarks'] ?? null,
                    'updated_by'       => $userId,
                ]);

                // Process Items
                $existingItems = $stockIn->stockInItems->keyBy('id');
                $submittedItemIds = [];

                foreach ($validated['items'] as $item) {

                    $qty       = $item['quantity'];
                    $unitPrice = $item['unit_price'];

                    // UPDATE existing item
                    if (!empty($item['id']) && $existingItems->has($item['id'])) {

                        $existingItems[$item['id']]->update([
                            'product_id'   => $item['product_id'],
                            'quantity'     => $qty,
                            'unit_price'   => $unitPrice,
                            'vat'          => $item['vat'] ?? 0,
                            'discount'     => $item['discount'] ?? 0,
                            'delivery_fee' => $item['delivery_fee'] ?? 0,
                            'total_price'  => bcmul($qty, $unitPrice, 10),
                            'remarks'      => $item['remarks'] ?? null,
                            'updated_by'   => $userId,
                        ]);

                        $submittedItemIds[] = $item['id'];

                    } else {

                        // CREATE NEW ITEM
                        $newItem = StockInItem::create([
                            'stock_in_id'  => $stockIn->id,
                            'product_id'   => $item['product_id'],
                            'quantity'     => $qty,
                            'unit_price'   => $unitPrice,
                            'vat'          => $item['vat'] ?? 0,
                            'discount'     => $item['discount'] ?? 0,
                            'delivery_fee' => $item['delivery_fee'] ?? 0,
                            'total_price'  => bcmul($qty, $unitPrice, 10),
                            'remarks'      => $item['remarks'] ?? null,
                            'updated_by'   => $userId,
                            'deleted_by'   => null,
                        ]);

                        $submittedItemIds[] = $newItem->id;
                    }
                }

                // Soft delete removed items
                $stockIn->stockInItems()
                    ->whereNotIn('id', $submittedItemIds)
                    ->each(function ($item) use ($userId) {
                        $item->deleted_by = $userId;
                        $item->save();
                        $item->delete();
                    });

                return response()->json([
                    'message' => 'Stock In updated successfully.',
                    'data' => $stockIn->load('stockInItems'),
                ], 200);
            });

        } catch (\Exception $e) {

            Log::error('Failed to update Stock In', [
                'error' => $e->getMessage(),
                'stock_in_id' => $stockIn->id,
            ]);

            return response()->json([
                'message' => 'Failed to update Stock In',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    // HELPERS


    public function getProducts(Request $request)
    {
        $draw = (int) $request->input('draw', 1);
        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        $searchValue = $request->input('search.value', null);
        $orderColumnIndex = $request->input('order.0.column', 0);
        $orderDirection = $request->input('order.0.dir', 'asc');

        $columns = [
            0 => 'id',
            1 => 'item_code',
            2 => 'description',
            3 => 'estimated_price',
            4 => 'is_active',
            5 => 'created_at',
            6 => 'updated_at',
        ];
        $orderColumn = $columns[$orderColumnIndex] ?? 'id';

        // Base query
        $query = ProductVariant::with('product')
            ->whereNull('deleted_at');

        // Apply search
        if ($searchValue) {
            $query->where(function ($q) use ($searchValue) {
                $q->where('item_code', 'like', "%{$searchValue}%")
                ->orWhere('description', 'like', "%{$searchValue}%")
                ->orWhereHas('product', function ($q2) use ($searchValue) {
                    $q2->where('name', 'like', "%{$searchValue}%");
                });
            });
        }

        $recordsFiltered = $query->count();

        // Apply order and pagination
        $variants = $query->orderBy($orderColumn, $orderDirection)
                        ->skip($start)
                        ->take($length)
                        ->get();

        // Map data for DataTable
        $data = $variants->map(function ($variant) {
            return [
                'id' => $variant->id,
                'item_code' => $variant->item_code,
                'description' => ($variant->product->name ?? '') . ' ' . $variant->description,
                'unit_name' => $variant->product->unit->name ?? '',
                'estimated_price' => number_format($variant->estimated_price, 2),
                'is_active' => (int) $variant->is_active,
                // Add stock info if you want
                'stock_on_hand' => 0, // placeholder, replace with real stock
                'average_price' => number_format($variant->estimated_price, 2),
            ];
        });

        return response()->json([
            'draw' => $draw,
            'recordsTotal' => ProductVariant::whereNull('deleted_at')->count(),
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }

    public function getSuppliers(Request $request)
    {
        $campuses = Supplier::where('is_active', 1)->get();

        return $campuses->map(fn($c) => [
            'id'   => $c->id,
            'text' => $c->short_name, // Select2 needs "text"
        ]);
    }

    public function getWarehouses(Request $request)
    {
        $warehouses = Warehouse::where('is_active', 1)->get();

        return $warehouses->map(fn($w) => [
            'id'   => $w->id,
            'text' => $w->name, // Select2 needs "text"
        ]);
    }

    public function getDepartments(Request $request)
    {
        $departments = Department::where('is_active', 1)->get();

        return $departments->map(fn($d) => [
            'id'   => $d->id,
            'text' => $d->short_name, // Select2 needs "text"
        ]);
    }

    // Validation Rule
    private function stockInValidationRule(): array
    {
        return [
            'transaction_date' => ['required', 'date', 'date_format:' . self::DATE_FORMAT],
            'reference_no'     => ['nullable', 'string', 'max:50'],
            'transaction_type' => ['required', 'string', 'max:50'],
            'invoice_no'       => ['nullable', 'string', 'max:50'],
            'payment_terms'    => ['nullable', 'string', 'max:100'],
            'supplier_id'      => ['required', 'integer', 'exists:suppliers,id'],
            'warehouse_id'     => ['required', 'integer', 'exists:warehouses,id'],
            'remarks'          => ['nullable', 'string', 'max:1000'],
        ];
    }


    private function stockInItemValidationRule(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id'   => ['required', 'integer', 'exists:product_variants,id'],
            'items.*.quantity'     => ['required', 'numeric', 'min:0.0001'],
            'items.*.unit_price'   => ['required', 'numeric', 'min:0'],
            'items.*.vat'          => ['nullable', 'numeric', 'min:0'],
            'items.*.discount'     => ['nullable', 'numeric', 'min:0'],
            'items.*.delivery_fee' => ['nullable', 'numeric', 'min:0'],
            'items.*.remarks'      => ['nullable', 'string', 'max:1000'],
        ];
    }

}
