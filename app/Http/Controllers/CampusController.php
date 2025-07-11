<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Campus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CampusController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', Campus::class);
        return view('campus.index');
    }

    public function getCampuses(Request $request)
    {
        $this->authorize('viewAny', Campus::class);
        $query = Campus::query();

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('short_name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('address', 'like', "%{$search}%");
            });
        }

        $allowedSortColumns = ['name', 'short_name', 'code', 'address', 'is_active', 'created_at'];
        $sortColumn = $request->get('sortColumn', 'created_at');
        $sortDirection = $request->get('sortDirection', 'desc');

        if (!in_array($sortColumn, $allowedSortColumns)) {
            $sortColumn = 'created_at';
        }
        if (!in_array(strtolower($sortDirection), ['asc', 'desc'])) {
            $sortDirection = 'desc';
        }

        $query->orderBy($sortColumn, $sortDirection);
        $limit = intval($request->get('limit', 10));
        $campuses = $query->paginate($limit);

        $data = collect($campuses->items())->map(function ($campus) {
            return [
                'id' => $campus->id,
                'code' => $campus->code,
                'short_name' => $campus->short_name,
                'name' => $campus->name,
                'address' => $campus->address,
                'is_active' => $campus->is_active,
                'created_at' => $campus->created_at,
            ];
        });

        return response()->json([
            'data' => $data,
            'recordsTotal' => $campuses->total(),
            'recordsFiltered' => $campuses->total(),
            'draw' => intval($request->get('draw')),
        ]);
    }

    private function campusValidationRules($campusId = null)
    {
        return [
            'code' => [
                'required',
                'string',
                'max:255',
                'unique:campus,code' . ($campusId ? ',' . $campusId : ''),
            ],
            'short_name' => [
                'required',
                'string',
                'max:255',
                'unique:campus,short_name' . ($campusId ? ',' . $campusId : ''),
            ],
            'name' => 'required|string|max:255',
            'address' => 'required|string|max:255',
            'is_active' => 'integer',
        ];
    }

    public function store(Request $request)
    {
        $this->authorize('create', Campus::class);
        $validated = validator($request->all(), $this->campusValidationRules())->validate();

        DB::beginTransaction();
        try {
            $campus = Campus::create([
                'code' => $validated['code'],
                'short_name' => $validated['short_name'],
                'name' => $validated['name'],
                'address' => $validated['address'],
                'is_active' => $validated['is_active'] ?? 1,
            ]);
            DB::commit();

            Log::info('Campus created', [
                'id' => $campus->id,
                'name' => $campus->name,
            ]);

            return response()->json([
                'message' => 'Campus created successfully.',
                'data' => $campus
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Campus creation failed', [
                'error' => $e->getMessage(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'message' => 'Failed to create campus.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function edit(Campus $campus)
    {
        $this->authorize('update', $campus);
        return response()->json([
            'data' => $campus
        ]);
    }

    public function update(Request $request, Campus $campus)
    {
        $this->authorize('update', $campus);
        $validated = validator($request->all(), $this->campusValidationRules($campus->id))->validate();

        DB::beginTransaction();
        try {
            $campus->update([
                'code' => $validated['code'],
                'short_name' => $validated['short_name'],
                'name' => $validated['name'],
                'address' => $validated['address'],
                'is_active' => $validated['is_active'] ?? 1,
            ]);
            DB::commit();

            Log::info('Campus updated', [
                'id' => $campus->id,
                'name' => $campus->name,
            ]);

            return response()->json([
                'message' => 'Campus updated successfully.',
                'data' => $campus
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Campus update failed', [
                'error' => $e->getMessage(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'message' => 'Failed to update campus.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy(Campus $campus)
    {
        $this->authorize('delete', $campus);
        try {
            $campus->delete();

            return response()->json([
                'message' => 'Campus deleted successfully.'
            ]);
        } catch (\Exception $e) {
            Log::error('Campus deletion failed', [
                'error' => $e->getMessage(),
                'campus_id' => $campus->id
            ]);

            return response()->json([
                'message' => 'Failed to delete campus.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}