<?php
namespace App\Services;

use App\Models\Warehouse;
use Illuminate\Http\Request;

class WarehouseService
{
    public function getWarehouses(Request $request)
    {
        $query = Warehouse::query()->with('building');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('khmer_name', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%")
                    ->orWhere('address_khmer', 'like', "%{$search}%")
                    ->orWhereHas('building', function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%");
                    });
            });
        }

        $allowedSortColumns = ['code', 'name', 'khmer_name', 'address', 'address_khmer', 'is_active', 'created_at', 'building_id', 'created_by', 'updated_by'];
        $sortColumn = $request->input('sortColumn', 'created_at');
        $sortDirection = strtolower($request->input('sortDirection', 'desc'));

        $sortColumn = in_array($sortColumn, $allowedSortColumns) ? $sortColumn : 'created_at';
        $sortDirection = in_array($sortDirection, ['asc', 'desc']) ? $sortDirection : 'desc';

        $query->orderBy($sortColumn, $sortDirection);

        $limit = max(1, (int) $request->input('limit', 10));
        return $query->paginate($limit);
    }
}
