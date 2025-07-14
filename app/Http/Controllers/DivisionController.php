<?php

namespace App\Http\Controllers;

use App\Models\Division;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class DivisionController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', Division::class);
        return view('division.index');
    }
    public function getDivisions(Request $request): \Illuminate\Http\JsonResponse
    {
        $this->authorize('viewAny', Division::class);

        $query = Division::query();

        // Handle search
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('short_name', 'like', "%{$search}%");
            });
        }

        // Handle sorting
        $allowedSortColumns = ['name', 'short_name', 'is_active', 'created_at'];
        $sortColumn = $request->input('sortColumn', 'created_at');
        $sortDirection = strtolower($request->input('sortDirection', 'desc'));

        $sortColumn = in_array($sortColumn, $allowedSortColumns) ? $sortColumn : 'created_at';
        $sortDirection = in_array($sortDirection, ['asc', 'desc']) ? $sortDirection : 'desc';

        $query->orderBy($sortColumn, $sortDirection);

        // Handle pagination
        $limit = max(1, (int) $request->input('limit', 10));
        $divisions = $query->paginate($limit);

        $data = $divisions->getCollection()->map(function (Division $division) {
            return [
                'id' => $division->id,
                'short_name' => $division->short_name,
                'name' => $division->name,
                'is_active' => (bool) $division->is_active,
                'created_at' => $division->created_at?->toDateTimeString(),
            ];
        });

        return response()->json([
            'data' => $data,
            'recordsTotal' => $divisions->total(),
            'recordsFiltered' => $divisions->total(),
            'draw' => (int) $request->input('draw', 1),
        ]);
    }
    private function divisionValidationRules(?int $divisionId = null): array
    {
        return [
            'short_name' => [
                'required',
                'string',
                'max:255',
                'unique:divisions,short_name' . ($divisionId ? ',' . $divisionId : ''),
            ],
            'name' => 'required|string|max:255',
            'is_active' => 'integer',
        ];
    }
    public function store(Request $request): \Illuminate\Http\JsonResponse
    {
        $this->authorize('create', Division::class);
        $validated = Validator::make($request->all(), $this->divisionValidationRules())->validate();

        DB::beginTransaction();
        try {
            $division = Division::create([
                'short_name' => $validated['short_name'],
                'name' => $validated['name'],
                'is_active' => $validated['is_active'] ?? 1,
            ]);
            DB::commit();

            return response()->json([
                'message' => 'Division created successfully.',
                'data' => $division
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to create division',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function edit(Division $division): \Illuminate\Http\JsonResponse
    {
        $this->authorize('update', $division);
        return response()->json([
            'data' => $division
        ]);
    }
    public function update(Request $request, Division $division): \Illuminate\Http\JsonResponse
    {
        $this->authorize('update', $division);
        $validated = Validator::make($request->all(), $this->divisionValidationRules($division->id))->validate();

        DB::beginTransaction();
        try {
            $division->update([
                'short_name' => $validated['short_name'],
                'name' => $validated['name'],
                'is_active' => $validated['is_active'] ?? 1,
            ]);
            DB::commit();

            return response()->json([
                'message' => 'Division updated successfully.',
                'data' => $division
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to update division',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function destroy(Division $division): \Illuminate\Http\JsonResponse
    {
        $this->authorize('delete', $division);
        try {
            $division->delete();
            return response()->json([
                'message' => 'Division deleted successfully.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete division',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
