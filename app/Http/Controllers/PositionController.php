<?php

namespace App\Http\Controllers;

use App\Models\Position;
use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PositionController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', Position::class);
        return view('position.index');
    }

    public function getPositions(Request $request): \Illuminate\Http\JsonResponse
    {
        $this->authorize('viewAny', Position::class);

        $query = Position::query()->with('department');

        // Handle search
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('short_title', 'like', "%{$search}%")
                    ->orWhereHas('department', function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%")
                            ->orWhere('short_name', 'like', "%{$search}%");
                    });
            });
        }

        // Handle sorting
        $allowedSortColumns = ['title', 'short_title', 'is_active', 'created_at', 'department_id'];
        $sortColumn = $request->input('sortColumn', 'created_at');
        $sortDirection = strtolower($request->input('sortDirection', 'desc'));

        $sortColumn = in_array($sortColumn, $allowedSortColumns) ? $sortColumn : 'created_at';
        $sortDirection = in_array($sortDirection, ['asc', 'desc']) ? $sortDirection : 'desc';

        $query->orderBy($sortColumn, $sortDirection);

        // Handle pagination
        $limit = max(1, (int) $request->input('limit', 10));
        $positions = $query->paginate($limit);

        $data = $positions->getCollection()->map(function (Position $position) {
            return [
                'id' => $position->id,
                'title' => $position->title,
                'short_title' => $position->short_title,
                'is_active' => (bool) $position->is_active,
                'department_id' => $position->department_id,
                'department_name' => $position->department ?$position->department->name . ' (' . $position->department->short_name . ')' : null,
                'created_at' => $position->created_at?->toDateTimeString(),
            ];
        });

        return response()->json([
            'data' => $data,
            'recordsTotal' => $positions->total(),
            'recordsFiltered' => $positions->total(),
            'draw' => (int) $request->input('draw', 1),
        ]);
    }

    private function positionValidationRules(?int $positionId = null): array
    {
        return [
            'short_title' => [
                'required',
                'string',
                'max:255',
                'unique:positions,short_title' . ($positionId ? ',' . $positionId : ''),
            ],
            'title' => 'required|string|max:255',
            'department_id' => 'required|integer|exists:departments,id',
            'is_active' => 'integer',
        ];
    }

    public function store(Request $request): \Illuminate\Http\JsonResponse
    {
        $this->authorize('create', Position::class);
        $validated = Validator::make($request->all(), $this->positionValidationRules())->validate();

        DB::beginTransaction();
        try {
            $position = Position::create([
                'short_title' => $validated['short_title'],
                'title' => $validated['title'],
                'department_id' => $validated['department_id'],
                'is_active' => $validated['is_active'] ?? 1,
            ]);
            DB::commit();

            return response()->json([
                'message' => 'Position created successfully.',
                'data' => $position
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to create position',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function edit(Position $position): \Illuminate\Http\JsonResponse
    {
        $this->authorize('update', $position);
        return response()->json([
            'data' => $position
        ]);
    }

    public function update(Request $request, Position $position): \Illuminate\Http\JsonResponse
    {
        $this->authorize('update', $position);
        $validated = Validator::make($request->all(), $this->positionValidationRules($position->id))->validate();

        DB::beginTransaction();
        try {
            $position->update([
                'short_title' => $validated['short_title'],
                'title' => $validated['title'],
                'department_id' => $validated['department_id'],
                'is_active' => $validated['is_active'] ?? 1,
            ]);
            DB::commit();

            return response()->json([
                'message' => 'Position updated successfully.',
                'data' => $position
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to update position',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy(Position $position): \Illuminate\Http\JsonResponse
    {
        $this->authorize('delete', $position);
        try {
            $position->delete();
            return response()->json([
                'message' => 'Position deleted successfully.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete position',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
