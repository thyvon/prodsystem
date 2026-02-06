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
}
