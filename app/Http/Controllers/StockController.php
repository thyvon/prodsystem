<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\StockLedgerService;
use App\Models\Warehouse;
use App\Models\ProductVariant;

class StockController extends Controller
{
    public function index()
    {
        return view('Inventory.Items.onhand');
    }

    public function getStockOnhand(Request $request)
    {
        $cutoffDate = $request->input('cutoff_date', null);

        $warehouses = Warehouse::whereNull('deleted_at')->pluck('name', 'id')->toArray();

        $stockService = app(StockLedgerService::class);
        $stockPivot = $stockService->getStockPivotByWarehouseForVariants($cutoffDate);

        // Map data
        $data = collect($stockPivot['rows'] ?? [])->map(function($row) use ($warehouses) {
            $rowData = [
                'product_id' => $row['product_id'],
                'product_name' => $row['product_name'],
                'description' => $row['description'],
                'total_stock' => $row['total'] ?? 0,
            ];

            foreach ($warehouses as $whId => $whName) {
                $rowData["warehouse_$whId"] = $row['warehouses'][$whId] ?? 0;
            }

            return $rowData;
        });

        return response()->json([
            'data' => $data,
            'warehouses' => $warehouses
        ]);
    }
}
