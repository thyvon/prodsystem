<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;

class PowerQueryDataController extends Controller
{
    public function productVariants(): JsonResponse
    {
        $data = DB::table('product_variants as pv')
            ->join('products as p', 'p.id', '=', 'pv.product_id')
            ->select([
                'pv.id',
                'p.name as product_name',
                'pv.item_code',
                'pv.description as variant_description',
                'pv.average_price',
                'pv.created_at',
            ])
            ->orderBy('pv.id')
            ->get();

        return response()->json([
            'data' => $data
        ]);
    }

    public function getStockIssueItems(): JsonResponse
    {
        $data = DB::table('stock_issue_items as sii')
            ->join('stock_issues as si', 'si.id', '=', 'sii.stock_issue_id')
            ->join('product_variants as pv', 'pv.id', '=', 'sii.product_id')
            ->join('products as p', 'p.id', '=', 'pv.product_id')
            ->select([
                'sii.id',
                'si.reference_number',
                'si.transaction_date',
                'pv.item_code',
                'p.name as product_name',
                'pv.description as variant_description',
                'sii.quantity',
                'sii.created_at',
            ])
            ->orderBy('sii.id')
            ->get();

        return response()->json([
            'data' => $data
        ]);
    }
}
