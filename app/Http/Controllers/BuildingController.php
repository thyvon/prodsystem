<?php

namespace App\Http\Controllers;

use App\Models\Building;
use App\Models\Campus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class BuildingController extends Controller
{
    /**
     * Display the buildings index view.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        $this->authorize('viewAny', Building::class);
        return view('building.index');
    }

    /**
     * Retrieve paginated buildings with optional search and sort.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getBuildings(Request $request): \Illuminate\Http\JsonResponse
    {
        $this->authorize('viewAny', Building::class);

        $query = Building::query()->with('campus');

        // Handle search
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('short_name', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%")
                    ->orWhereHas('campus', function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%");
                    });
            });
        }

        // Handle sorting
        $allowedSortColumns = ['name', 'short_name', 'address', 'is_active', 'created_at', 'campus_id'];
        $sortColumn = $request->input('sortColumn', 'created_at');
        $sortDirection = strtolower($request->input('sortDirection', 'desc'));

        $sortColumn = in_array($sortColumn, $allowedSortColumns) ? $sortColumn : 'created_at';
        $sortDirection = in_array($sortDirection, ['asc', 'desc']) ? $sortDirection : 'desc';

        $query->orderBy($sortColumn, $sortDirection);

        // Handle pagination
        $limit = max(1, (int) $request->input('limit', 10));
        $buildings = $query->paginate($limit);

        $data = $buildings->getCollection()->map(function (Building $building) {
            return [
                'id' => $building->id,
                'short_name' => $building->short_name,
                'name' => $building->name,
                'address' => $building->address,
                'is_active' => (bool) $building->is_active,
                'campus_id' => $building->campus_id,
                'campus_name' => $building->campus?->name,
                'created_at' => $building->created_at?->toDateTimeString(),
            ];
        });

        return response()->json([
            'data' => $data,
            'recordsTotal' => $buildings->total(),
            'recordsFiltered' => $buildings->total(),
            'draw' => (int) $request->input('draw', 1),
        ]);
    }

    /**
     * Get validation rules for building creation/update.
     *
     * @param int|null $buildingId
     * @return array
     */
    private function buildingValidationRules(?int $buildingId = null): array
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

    /**
     * Store a new building.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request): \Illuminate\Http\JsonResponse
    {
        $this->authorize('create', Building::class);
        $validated = Validator::make($request->all(), $this->buildingValidationRules())->validate();

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

            return response()->json([
                'message' => 'Building created successfully.',
                'data' => $building
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to create building',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a building for editing.
     *
     * @param Building $building
     * @return \Illuminate\Http\JsonResponse
     */
    public function edit(Building $building): \Illuminate\Http\JsonResponse
    {
        $this->authorize('update', $building);
        return response()->json([
            'data' => $building
        ]);
    }

    /**
     * Update an existing building.
     *
     * @param Request $request
     * @param Building $building
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, Building $building): \Illuminate\Http\JsonResponse
    {
        $this->authorize('update', $building);
        $validated = Validator::make($request->all(), $this->buildingValidationRules($building->id))->validate();

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

            return response()->json([
                'message' => 'Building updated successfully.',
                'data' => $building
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to update building',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a building.
     *
     * @param Building $building
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Building $building): \Illuminate\Http\JsonResponse
    {
        $this->authorize('delete', $building);
        try {
            $building->delete();
            return response()->json([
                'message' => 'Building deleted successfully.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete building',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}