<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Building;
use App\Models\Campus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BuildingController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', Building::class);
        return view('buildingindex');
    }

    public function getBuildings(Request $request)
    {
        \Log::info('getBuildings called', $request->all());

        $this->authorize('viewAny', Building::class);
        $query = Building::query()->with('campus'); // Eager load campus relationship

        if ($search = $request->get('search')) {
            \Log::info('Search term', ['search' => $search]);
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('short_name', 'like', "%{$search}%")
                  ->orWhere('address', 'like', "%{$search}%")
                  ->orWhereHas('campus', function ($q) use ($search) {
                      $q->where('name', 'like', "%{$search}%");
                  });
            });
        }

        $allowedSortColumns = ['name', 'short_name', 'address', 'is_active', 'created_at', 'campus_id'];
        $sortColumn = $request->get('sortColumn', 'created_at');
        $sortDirection = $request->get('sortDirection', 'desc');

        if (!in_array($sortColumn, $allowedSortColumns)) {
            \Log::warning('Invalid sort column', ['sortColumn' => $sortColumn]);
            $sortColumn = 'created_at';
        }
        if (!in_array(strtolower($sortDirection), ['asc', 'desc'])) {
            \Log::warning('Invalid sort direction', ['sortDirection' => $sortDirection]);
            $sortDirection = 'desc';
        }

        $query->orderBy($sortColumn, $sortDirection);
        $limit = max(1, intval($request->get('limit', 10)));
        $buildings = $query->paginate($limit);

        $data = collect($buildings->items())->map(function ($building) {
            return [
                'id' => $building->id,
                'short_name' => $building->short_name,
                'name' => $building->name,
                'address' => $building->address,
                'is_active' => (bool) $building->is_active,
                'campus_id' => $building->campus_id,
                'campus_name' => $building->campus ? $building->campus->name : null,
                'created_at' => $building->created_at ? $building->created_at->toDateTimeString() : null,
            ];
        });

        $response = [
            'data' => $data,
            'recordsTotal' => $buildings->total(),
            'recordsFiltered' => $buildings->total(),
            'draw' => intval($request->get('draw', 1)),
        ];

        \Log::info('getBuildings response', $response);
        return response()->json($response);
    }

    private function buildingValidationRules($buildingId = null)
    {
        return [
            'short_name' => [
                'required',
                'string',
                'max:255',
                'unique:buildings,short_name' . ($buildingId ? ',' . $buildingId : ''),
            ],
            'name' => 'required|string|max:255',
            'address' => 'required|string|max:255',
            'campus_id' => 'required|integer|exists:campus,id',
            'is_active' => 'integer',
        ];
    }

    public function store(Request $request)
    {
        $this->authorize('create', Building::class);
        $validated = validator($request->all(), $this->buildingValidationRules())->validate();

        DB::beginTransaction();
        try {
            $building = Building::create([
                'short_name' => $validated['short_name'],
                'name' => $validated['name'],
                'address' => $validated['address'],
                'campus_id' => $validated['campus_id'],
                'is_active' => $validated['is_active'] ?? 1,
            ]);
            DB::commit();

            Log::info('Building created', [
                'id' => $building->id,
                'name' => $building->name,
                'campus_id' => $building->campus_id,
            ]);

            return response()->json([
                'message' => 'Building created successfully.',
                'data' => $building
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Building creation failed', [
                'error' => $e->getMessage(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'message' => 'Failed to create building',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function edit(Building $building)
    {
        $this->authorize('update', $building);
        $building->load('campus'); // Eager load campus relationship
        return response()->json([
            'data' => [
                'id' => $building->id,
                'short_name' => $building->short_name,
                'name' => $building->name,
                'address' => $building->address,
                'campus_id' => $building->campus_id,
                'is_active' => (bool) $building->is_active,
                'created_at' => $building->created_at ? $building->created_at->toDateTimeString() : null,
            ]
        ]);
    }

    public function update(Request $request, Building $building)
    {
        $this->authorize('update', $building);
        $validated = validator($request->all(), $this->buildingValidationRules($building->id))->validate();

        DB::beginTransaction();
        try {
            $building->update([
                'short_name' => $validated['short_name'],
                'name' => $validated['name'],
                'address' => $validated['address'],
                'campus_id' => $validated['campus_id'],
                'is_active' => $validated['is_active'] ?? 1,
            ]);
            DB::commit();

            Log::info('Building updated', [
                'id' => $building->id,
                'name' => $building->name,
                'campus_id' => $building->campus_id,
            ]);

            return response()->json([
                'message' => 'Building updated successfully.',
                'data' => $building
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Building update failed', [
                'error' => $e->getMessage(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'message' => 'Failed to update building',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy(Building $building)
    {
        $this->authorize('delete', $building);
        try {
            $building->delete();

            return response()->json([
                'message' => 'Building deleted successfully.'
            ]);
        } catch (\Exception $e) {
            Log::error('Building deletion failed', [
                'error' => $e->getMessage(),
                'building_id' => $building->id
            ]);

            return response()->json([
                'message' => 'Failed to delete building',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}