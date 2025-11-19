<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\StockLedger;
use App\Models\ProductVariant;
use App\Models\Warehouse;
use Carbon\Carbon;
use Spatie\Browsershot\Browsershot;

class StockController extends Controller
{

    public function index(Request $request)
    {
        return view('Inventory.stock-report.index');
    }

    public function stockReport(Request $request)
    {  
        $forPrint = $request->input('forPrint', false);
        $startDate = $request->input('start_date');
        $endDate   = $request->input('end_date') ?? Carbon::today()->toDateString();

        $warehouseIds = $this->normalizeArray($request->input('warehouse_ids', []));
        $productIds   = $this->normalizeArray($request->input('product_ids', []));

        $validated = $request->validate([
            'search'        => 'nullable|string|max:255',
            'sortColumn'    => 'nullable|string',
            'sortDirection' => 'nullable|string|in:asc,desc',
            'limit'         => 'nullable|integer|min:1',
            'page'          => 'nullable|integer|min:1',
            'draw'          => 'nullable|integer',
        ]);

        $sortColumn    = $validated['sortColumn'] ?? 'item_code';
        $sortDirection = $validated['sortDirection'] ?? 'asc';
        $limit         = $validated['limit'] ?? 10;
        $page          = $validated['page'] ?? 1;

        $query = ProductVariant::query()
            ->whereNull('deleted_at')
            ->whereHas('product', fn($q) => $q->where('manage_stock', 1))
            ->when(!empty($productIds), fn($q) => $q->whereIn('id', $productIds));

        if (!empty($validated['search'])) {
            $search = $validated['search'];
            $query->where(function ($q) use ($search) {
                $q->where('item_code', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%")
                ->orWhereHas('product', fn($pQ) => $pQ->where('name', 'like', "%{$search}%"));
            });
        }

        if ($sortColumn === 'item_code') {
            $query->orderBy('item_code', $sortDirection);
        } else {
            $query->orderBy('id', $sortDirection);
        }

        $products = $forPrint 
            ? $query->get() 
            : $query->paginate($limit, ['*'], 'page', $page);

        $report = $products->map(function ($product) use ($warehouseIds, $startDate, $endDate) {
            $productId = $product->id;

            $beginQty = $beginTotal = 0;
            $stockInQty = $stockInTotal = 0;
            $stockOutQty = $stockOutTotal = 0;

            $warehousesToLoop = !empty($warehouseIds)
                ? $warehouseIds
                : StockLedger::where('product_id', $productId)->pluck('parent_warehouse')->unique();

            foreach ($warehousesToLoop as $warehouseId) {
                $begin    = $this->getBeginEnd($productId, $warehouseId, $startDate, $endDate);
                $stockIn  = $this->getStockIn($productId, $warehouseId, $startDate, $endDate);
                $stockOut = $this->getStockOut($productId, $warehouseId, $startDate, $endDate);

                $beginQty      += $begin['quantity'];
                $beginTotal    += $begin['total_price'];
                $stockInQty    += $stockIn['quantity'];
                $stockInTotal  += $stockIn['total_price'];
                $stockOutQty   += $stockOut['quantity'];
                $stockOutTotal += $stockOut['total_price'];
            }

            $endingQty   = $beginQty + $stockInQty + $stockOutQty;
            $avgPrice    = $this->avgPrice($productId, [], $endDate);
            $endingTotal = $endingQty * $avgPrice;

            return [
                'product_id'         => $productId,
                'item_code'          => $product->item_code,
                'description'        => ($product->product->name ?? '') . ' ' . ($product->description ?? ''),
                'beginning_quantity' => round($beginQty, 6),
                'beginning_total'    => round($beginTotal, 6),
                'stock_in_quantity'  => round($stockInQty, 6),
                'stock_in_total'     => round($stockInTotal, 6),
                'stock_out_quantity' => round(abs($stockOutQty), 6),
                'stock_out_total'    => round(abs($stockOutTotal), 6),
                'ending_quantity'    => round($endingQty, 6),
                'ending_total'       => round($endingTotal, 6),
                'average_price'      => round($avgPrice, 6),
            ];
        });

        if ($forPrint) {
            $html = view('Inventory.stock-report.print-report', [
                'report'     => $report,
                'start_date' => $startDate,
                'end_date'   => $endDate,
            ])->render();

            $pdfContent = Browsershot::html($html)
                ->noSandbox()
                ->landscape()
                ->format('A4')
                ->margins(5, 3, 5, 3)
                ->showBackground()
                ->pdf();

            return response()->json([
                'pdf_base64' => base64_encode($pdfContent),
                'filename'   => 'Stock Report.pdf',
            ]);
        }

        return response()->json([
            'data'            => $report,
            'recordsTotal'    => $products->total(),
            'recordsFiltered' => $products->total(),
            'draw'            => (int) ($validated['draw'] ?? 1),
        ]);
    }


    // ---------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------

    private function normalizeArray($value)
    {
        if (is_string($value)) {
            return array_filter(array_map('trim', explode(',', $value)));
        }

        return is_array($value) ? $value : [];
    }

    /**
     * Beginning Balance = all transactions BEFORE start_date
     * If no start_date → before end_date (original logic)
     */
    private function getBeginEnd($productId, $warehouseId, $startDate, $endDate)
    {
        $query = StockLedger::where('product_id', $productId)
            ->where('parent_warehouse', $warehouseId);

        if ($startDate) {
            $query->whereDate('transaction_date', '<', $startDate);
        } else {
            $query->whereDate('transaction_date', '<', $endDate);
        }

        $rows = $query->get();

        return [
            'quantity'     => round($rows->sum('quantity'), 6),
            'total_price'  => round($rows->sum('total_price'), 6),
        ];
    }

    /**
     * Stock In between start_date → end_date
     */
    private function getStockIn($productId, $warehouseId, $startDate, $endDate)
    {
        $query = StockLedger::where('product_id', $productId)
            ->where('parent_warehouse', $warehouseId)
            ->where('quantity', '>', 0);

        if ($startDate) {
            $query->whereDate('transaction_date', '>=', $startDate)
                  ->whereDate('transaction_date', '<=', $endDate);
        } else {
            $query->whereDate('transaction_date', $endDate);
        }

        $rows = $query->get();

        return [
            'quantity'     => round($rows->sum('quantity'), 6),
            'total_price'  => round($rows->sum('total_price'), 6),
        ];
    }

    /**
     * Stock Out between start_date → end_date
     */
    private function getStockOut($productId, $warehouseId, $startDate, $endDate)
    {
        $query = StockLedger::where('product_id', $productId)
            ->where('parent_warehouse', $warehouseId)
            ->where('quantity', '<', 0);

        if ($startDate) {
            $query->whereDate('transaction_date', '>=', $startDate)
                  ->whereDate('transaction_date', '<=', $endDate);
        } else {
            $query->whereDate('transaction_date', $endDate);
        }

        $rows = $query->get();

        return [
            'quantity'     => round($rows->sum('quantity'), 6),
            'total_price'  => round($rows->sum('total_price'), 6),
        ];
    }

    /**
     * Average Price (your original stable logic)
     */
    private function avgPrice($productId, array $warehouseIds = [], $endDate = null)
    {
        $ledger = StockLedger::where('product_id', $productId)
            ->when($endDate, fn($q) => $q->whereDate('transaction_date', '<=', $endDate))
            ->get();

        $totalQty      = $ledger->sum('quantity');
        $totalPrice    = $ledger->sum('total_price');

        $stockOutQty   = $ledger->where('quantity', '<', 0)->sum('quantity');
        $stockOutTotal = $ledger->where('quantity', '<', 0)->sum('total_price');

        $qtyBalance    = $totalQty + abs($stockOutQty);
        $priceBalance  = $totalPrice + abs($stockOutTotal);

        return $qtyBalance
            ? round($priceBalance / $qtyBalance, 6)
            : 0;
    }

    public function getWarehouses(Request $request)
    {
        $warehouses = Warehouse::where('is_active', 1)->get();

        return $warehouses->map(fn($w) => [
            'id'   => $w->id,
            'text' => $w->name, // Select2 needs "text"
        ]);
    }
}
