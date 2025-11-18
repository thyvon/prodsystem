<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use App\Models\StockIn;
use App\Models\StockInItem;
use App\Models\ProductVariant;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Imports\StockInImport;
use Maatwebsite\Excel\Facades\Excel;

class StockInController extends Controller
{

    private const ALLOWED_SORT_COLUMNS = [
        'transaction_date',
        'reference_no',
        'created_at',
        'updated_at',
        'created_by',
        'updated_by',
        'warehouse_name',
        'supplier_name',
    ];
    private const DEFAULT_SORT_DIRECTION = 'desc';
    private const DEFAULT_LIMIT = 10;
    private const MAX_LIMIT = 1000;

    private const DATE_FORMAT = 'Y-m-d';

    public function index()
    {
        $this->authorize('viewAny', StockIn::class);
        return view('Inventory.stockIn.index');
    }

    public function getStockIns(Request $request): JsonResponse
    {
        $this->authorize('viewAny', StockIn::class);

        $user = auth()->user();
        $isAdmin = $user->hasRole('admin');
        $campusIds = $user->campus->pluck('id')->toArray();

        $validated = $request->validate([
            'search' => 'nullable|string|max:255',
            'sortColumn' => 'nullable|string',
            'sortDirection' => 'nullable|string|in:asc,desc',
            'limit' => 'nullable|integer|min:1|max:' . self::MAX_LIMIT,
            'page' => 'nullable|integer|min:1',
            'draw' => 'nullable|integer',
        ]);

        $sortColumn = $validated['sortColumn'] ?? 'stock_ins.id';
        $sortDirection = $validated['sortDirection'] ?? 'desc';

        $query = StockIn::with([
            'warehouse.building.campus',
            'createdBy.campus',
            'updatedBy',
            'supplier'
        ])
        ->when(!$isAdmin, fn($q) => $q->whereHas('warehouse.building.campus', fn($q2) => $q2->whereIn('id', $campusIds)))
        ->when($validated['search'] ?? null, fn($q, $search) => $q->where(fn($subQ) =>
            $subQ->where('reference_no', 'like', "%{$search}%")
                ->orWhereHas('supplier', fn($sQ) => $sQ->where('name', 'like', "%{$search}%"))
                ->orWhereHas('warehouse', fn($wQ) => $wQ->where('name', 'like', "%{$search}%"))
                ->orWhereHas('createdBy', fn($cQ) => $cQ->where('name', 'like', "%{$search}%"))
        ));

        // Sorting via join for relational columns
        if ($sortColumn === 'warehouse_name') {
            $query->join('warehouses', 'stock_ins.warehouse_id', '=', 'warehouses.id')
                ->orderBy('warehouses.name', $sortDirection)
                ->select('stock_ins.*');
        } elseif ($sortColumn === 'supplier_name') {
            $query->join('suppliers', 'stock_ins.supplier_id', '=', 'suppliers.id')
                ->orderBy('suppliers.name', $sortDirection)
                ->select('stock_ins.*');
        } elseif ($sortColumn === 'created_by') {
            $query->join('users', 'stock_ins.created_by', '=', 'users.id')
                ->orderBy('users.name', $sortDirection)
                ->select('stock_ins.*');
        } else {
            $query->orderBy($sortColumn, $sortDirection);
        }

        $stockIns = $query->paginate(
            $validated['limit'] ?? self::DEFAULT_LIMIT,
            ['*'],
            'page',
            $validated['page'] ?? 1
        );

        $stockInsMapped = $stockIns->map(fn($in) => [
            'id' => $in->id,
            'reference_no' => $in->reference_no,
            'transaction_date' => $in->transaction_date,
            'supplier_name' => $in->supplier->name ?? null,
            'payment_terms' => $in->payment_terms ?? null,
            'warehouse_name' => $in->warehouse->name ?? null,
            'warehouse_campus_name' => $in->warehouse->building->campus->short_name ?? null,
            'quantity' => number_format($in->Items->sum('quantity'), 2, '.', ''),
            'total_price' => number_format($in->Items->sum('total_price'), 4, '.', ''),
            'created_by' => $in->createdBy->name ?? null,
            'created_at' => $in->created_at,
            'updated_at' => $in->updated_at,
            'remarks' => $in->remarks ?? null,
        ]);

        return response()->json([
            'data' => $stockInsMapped,
            'recordsTotal' => $stockIns->total(),
            'recordsFiltered' => $stockIns->total(),
            'draw' => (int) ($validated['draw'] ?? 1),
        ]);
    }

    public function indexItem()
    {
        $this->authorize('viewAny', StockIn::class);
        return view('Inventory.stockIn.item-list');
    }

    public function getAllStockInItems(Request $request): JsonResponse
    {
        $this->authorize('viewAny', StockIn::class);

        $user = auth()->user();
        $isAdmin = $user->hasRole('admin');
        $campusIds = $user->campus->pluck('id')->toArray();

        // Validate request
        $validated = $request->validate([
            'search' => 'nullable|string|max:255',
            'sortColumn' => 'nullable|string',
            'sortDirection' => 'nullable|string|in:asc,desc',
            'limit' => 'nullable|integer|min:1|max:' . self::MAX_LIMIT,
            'page' => 'nullable|integer|min:1',
            'draw' => 'nullable|integer',
            'warehouse_ids' => 'nullable|array',
            'warehouse_ids.*' => 'integer|exists:warehouses,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
        ]);

        $sortColumn = $validated['sortColumn'] ?? 'stock_in_items.id';
        $sortDirection = $validated['sortDirection'] ?? self::DEFAULT_SORT_DIRECTION;

        $query = StockInItem::with(['product.product.unit', 'stockIn.warehouse.building.campus', 'stockIn.supplier'])
            // restrict by campus if not admin
            ->when(!$isAdmin, fn($q) =>
                $q->whereHas('stockIn.warehouse.building.campus', fn($q2) =>
                    $q2->whereIn('id', $campusIds)
                )
            )
            // Search filter (product code / product name / variant description / stock in reference / supplier)
            ->when($validated['search'] ?? null, fn($q, $search) =>
                $q->where(fn($subQ) =>
                    $subQ->where('remarks', 'like', "%{$search}%")
                        ->orWhereHas('product', fn($pvQ) =>
                            $pvQ->where('description', 'like', "%{$search}%")
                                ->orWhere('item_code', 'like', "%{$search}%")
                                ->orWhereHas('product', fn($pQ) =>
                                    $pQ->where('name', 'like', "%{$search}%")
                                )
                        )
                        ->orWhereHas('stockIn', fn($siQ) =>
                            $siQ->where('reference_no', 'like', "%{$search}%")
                                ->orWhereHas('supplier', fn($sQ) => $sQ->where('name', 'like', "%{$search}%"))
                                ->orWhereHas('warehouse', fn($wQ) => $wQ->where('name', 'like', "%{$search}%"))
                        )
                )
            )
            // Multi-warehouse filter
            ->when($validated['warehouse_ids'] ?? null, function ($q, $warehouseIds) {
                $q->whereHas('stockIn', function ($siQ) use ($warehouseIds) {
                    $siQ->whereIn('warehouse_id', $warehouseIds);
                });
            })
            // Date range filter (based on stock_in.transaction_date)
            ->when($validated['start_date'] ?? null, fn($q, $start) =>
                $q->whereHas('stockIn', fn($siQ) => $siQ->whereDate('transaction_date', '>=', $start))
            )
            ->when($validated['end_date'] ?? null, fn($q, $end) =>
                $q->whereHas('stockIn', fn($siQ) => $siQ->whereDate('transaction_date', '<=', $end))
            );

        // Sorting relational columns (product_code and stock_in_reference)
        if ($sortColumn === 'product_code') {
            $query->join('product_variants', 'stock_in_items.product_id', '=', 'product_variants.id')
                ->orderBy('product_variants.item_code', $sortDirection)
                ->select('stock_in_items.*');
        } elseif ($sortColumn === 'stock_in_reference') {
            $query->join('stock_ins', 'stock_in_items.stock_in_id', '=', 'stock_ins.id')
                ->orderBy('stock_ins.reference_no', $sortDirection)
                ->select('stock_in_items.*');
        } else {
            $query->orderBy($sortColumn, $sortDirection);
        }

        // Pagination
        $limit = $validated['limit'] ?? self::DEFAULT_LIMIT;
        $page = $validated['page'] ?? 1;
        $items = $query->paginate($limit, ['*'], 'page', $page);

        // Map data for frontend
        $itemsMapped = $items->map(function ($item) {
            $v = $item->product;
            $productName = $v->product->name ?? '';
            $variantDescription = $v->description ?? '';

            return [
                'id' => $item->id,
                'stock_in_reference' => $item->stockIn->reference_no ?? null,
                'product_code' => $v->item_code ?? null,
                'description' => trim($productName . ' ' . $variantDescription),
                'quantity' => number_format($item->quantity, 2, '.', ''),
                'unit_name' => $v->product->unit->name ?? null,
                'unit_price' => number_format($item->unit_price, 4, '.', ''),
                'total_price' => number_format($item->total_price, 4, '.', ''),
                'supplier_name' => $item->stockIn->supplier->name ?? null,
                'warehouse_name' => $item->stockIn->warehouse->name ?? null,
                'transaction_type' => $item->stockIn->transaction_type ?? null,
                'transaction_date' => $item->stockIn->transaction_date ?? null,
                'remarks' => $item->remarks,
            ];
        });

        return response()->json([
            'data' => $itemsMapped,
            'recordsTotal' => $items->total(),
            'recordsFiltered' => $items->total(),
            'draw' => (int) ($validated['draw'] ?? 1),
        ]);
    }

    public function create()
    {
        $this->authorize('create', StockIn::class);
        return view('Inventory.stockIn.form');
    }

    public function edit(StockIn $stockIn)
    {
        $this->authorize('update', $stockIn);

        return view('Inventory.stockIn.form', ['initialId' => $stockIn->id]);
    }

    public function getEditData(StockIn $stockIn): JsonResponse
    {
        $this->authorize('update', $stockIn);

        // load items
        $stockIn->load('Items');

        // Fetch product variant details in bulk to avoid N+1
        $productIds = $stockIn->Items->pluck('product_id')->unique()->filter()->values()->all();
        $variants = ProductVariant::with(['product.unit'])->whereIn('id', $productIds)->get()->keyBy('id');

        $items = $stockIn->Items->map(function ($it) use ($variants) {
            $v = $variants->get($it->product_id);

            return [
                'id' => $it->id,
                'product_id' => $it->product_id,
                'product_code' => $v?->item_code ?? null,
                'description' => trim(($v->product->name ?? '') . ' ' . ($v->description ?? '')),
                'unit_name' => $v->product->unit->name ?? '',
                'quantity' => (float) $it->quantity,
                'unit_price' => (string) $it->unit_price,
                'vat' => (float) ($it->vat ?? 0),
                'discount' => (float) ($it->discount ?? 0),
                'delivery_fee' => (float) ($it->delivery_fee ?? 0),
                'total_price' => (string) ($it->total_price ?? 0),
                'remarks' => $it->remarks ?? null,
            ];
        })->values();

        $stockInData = [
            'id' => $stockIn->id,
            'transaction_date' => $stockIn->transaction_date,
            'reference_no' => $stockIn->reference_no,
            'transaction_type' => $stockIn->transaction_type,
            'invoice_no' => $stockIn->invoice_no,
            'payment_terms' => $stockIn->payment_terms,
            'supplier_id' => $stockIn->supplier_id,
            'warehouse_id' => $stockIn->warehouse_id,
            'remarks' => $stockIn->remarks,
        ];

        return response()->json([
            'stock_in' => $stockInData,
            'items' => $items,
        ]);
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,csv'],
        ]);

        try {
            Excel::import(new StockInImport, $request->file('file'));

            return response()->json([
                'message' => 'Stock In imported successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 500);
        }
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
                    ?? $this->generateReferenceNo($validated['transaction_date'], $validated['warehouse_id'] ?? null);

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
                    'data'    => $stockIn->load('Items'),
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
                $existingItems = $stockIn->Items->keyBy('id');
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
                $stockIn->Items()
                    ->whereNotIn('id', $submittedItemIds)
                    ->each(function ($item) use ($userId) {
                        $item->deleted_by = $userId;
                        $item->save();
                        $item->delete();
                    });

                return response()->json([
                    'message' => 'Stock In updated successfully.',
                    'data' => $stockIn->load('Items'),
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

    public function destroy(StockIn $stockIn): JsonResponse
    {
        $this->authorize('delete', $stockIn);

        try {
            return DB::transaction(function () use ($stockIn) {
                $userId = auth()->id() ?? 1;

                // Soft-delete related items (mark deleted_by then delete)
                foreach ($stockIn->Items as $item) {
                    $item->deleted_by = $userId;
                    $item->save();
                    $item->delete();
                }

                // Soft-delete stock in
                $stockIn->deleted_by = $userId;
                $stockIn->save();
                $stockIn->delete();

                return response()->json(['message' => 'Stock In deleted successfully.'], 200);
            });
        } catch (\Exception $e) {
            Log::error('Failed to delete Stock In', [
                'error' => $e->getMessage(),
                'stock_in_id' => $stockIn->id,
            ]);

            return response()->json([
                'message' => 'Failed to delete Stock In',
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
            'text' => $c->name, // Select2 needs "text"
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

    private function generateReferenceNo(string $transactionDate, ?int $warehouseId = null): string
    {
        // Try to load warehouse by id with building.campus if provided
        $warehouse = $warehouseId ? Warehouse::with('building.campus')->find($warehouseId) : null;

        // Fallback: if no warehouse supplied or not found, attempt to pick an active warehouse (best-effort)
        if (!$warehouse) {
            $warehouse = Warehouse::with('building.campus')->where('is_active', 1)->first();
        }

        try {
            $date = \Carbon\Carbon::createFromFormat(self::DATE_FORMAT, $transactionDate);
            if (!$date || $date->format(self::DATE_FORMAT) !== $transactionDate) {
                throw new \InvalidArgumentException('Invalid date format. Expected ' . self::DATE_FORMAT . '.');
            }
        } catch (\Exception $e) {
            throw new \InvalidArgumentException('Invalid date format. Expected ' . self::DATE_FORMAT . '.', 0, $e);
        }

        $shortName = $warehouse->building?->campus?->short_name ?? 'WH';
        $monthYear = $date->format('my'); // e.g. 0925

        // Sequence number for this shortName + month
        $sequence = $this->getSequenceNumber($shortName, $monthYear);

        // Use SIN prefix for Stock IN
        return "SIN-{$shortName}-{$monthYear}-{$sequence}";
    }

    private function getSequenceNumber(string $shortName, string $monthYear): string
    {
        $prefix = "SIN-{$shortName}-{$monthYear}-";

        $count = StockIn::withTrashed()
            ->where('reference_no', 'like', "{$prefix}%")
            ->count();

        return str_pad($count + 1, 2, '0', STR_PAD_LEFT);
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
