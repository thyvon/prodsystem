<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Division;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class DepartmentController extends Controller
{
    /**
     * Display the departments index view.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        $this->authorize('viewAny', Department::class);
        return view('department.index');
    }

    /**
     * Retrieve paginated departments with optional search and sort.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getDepartments(Request $request): \Illuminate\Http\JsonResponse
    {
        $this->authorize('viewAny', Department::class);

        $query = Department::query()->with('division');

        // Handle search
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('short_name', 'like', "%{$search}%")
                    ->orWhereHas('division', function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%");
                    });
            });
        }

        // Handle sorting
        $allowedSortColumns = ['name', 'short_name', 'is_active', 'created_at', 'division_id'];
        $sortColumn = $request->input('sortColumn', 'created_at');
        $sortDirection = strtolower($request->input('sortDirection', 'desc'));

        $sortColumn = in_array($sortColumn, $allowedSortColumns) ? $sortColumn : 'created_at';
        $sortDirection = in_array($sortDirection, ['asc', 'desc']) ? $sortDirection : 'desc';

        $query->orderBy($sortColumn, $sortDirection);

        // Handle pagination
        $limit = max(1, (int) $request->input('limit', 10));
        $departments = $query->paginate($limit);

        $data = $departments->getCollection()->map(function (Department $department) {
            return [
                'id' => $department->id,
                'short_name' => $department->short_name,
                'name' => $department->name,
                'is_active' => (bool) $department->is_active,
                'division_id' => $department->division_id,
                'division_name' => $department->division ? 
                '(' . $department->division->short_name . ')' . ' - ' . $department->division->name : null,
                'created_at' => $department->created_at?->toDateTimeString(),
            ];
        });

        return response()->json([
            'data' => $data,
            'recordsTotal' => $departments->total(),
            'recordsFiltered' => $departments->total(),
            'draw' => (int) $request->input('draw', 1),
        ]);
    }

    /**
     * Get validation rules for department creation/update.
     *
     * @param int|null $departmentId
     * @return array
     */
    private function departmentValidationRules(?int $departmentId = null): array
    {
        return [
            'short_name' => [
                'required',
                'string',
                'max:255',
                'unique:departments,short_name' . ($departmentId ? ',' . $departmentId : ''),
            ],
            'name' => 'required|string|max:255',
            'division_id' => 'required|integer|exists:divisions,id',
            'is_active' => 'integer',
        ];
    }

    /**
     * Store a new department.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request): \Illuminate\Http\JsonResponse
    {
        $this->authorize('create', Department::class);
        $validated = Validator::make($request->all(), $this->departmentValidationRules())->validate();

        DB::beginTransaction();
        try {
            $department = Department::create([
                'short_name' => $validated['short_name'],
                'name' => $validated['name'],
                'division_id' => $validated['division_id'],
                'is_active' => $validated['is_active'] ?? 1,
            ]);
            DB::commit();

            return response()->json([
                'message' => 'Department created successfully.',
                'data' => $department
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to create department',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a department for editing.
     *
     * @param Department $department
     * @return \Illuminate\Http\JsonResponse
     */
    public function edit(Department $department): \Illuminate\Http\JsonResponse
    {
        $this->authorize('update', $department);
        return response()->json([
            'data' => $department
        ]);
    }

    /**
     * Update an existing department.
     *
     * @param Request $request
     * @param Department $department
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, Department $department): \Illuminate\Http\JsonResponse
    {
        $this->authorize('update', $department);
        $validated = Validator::make($request->all(), $this->departmentValidationRules($department->id))->validate();

        DB::beginTransaction();
        try {
            $department->update([
                'short_name' => $validated['short_name'],
                'name' => $validated['name'],
                'division_id' => $validated['division_id'],
                'is_active' => $validated['is_active'] ?? 1,
            ]);
            DB::commit();

            return response()->json([
                'message' => 'Department updated successfully.',
                'data' => $department
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to update department',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a department.
     *
     * @param Department $department
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Department $department): \Illuminate\Http\JsonResponse
    {
        $this->authorize('delete', $department);
        try {
            $department->delete();
            return response()->json([
                'message' => 'Department deleted successfully.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete department',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}