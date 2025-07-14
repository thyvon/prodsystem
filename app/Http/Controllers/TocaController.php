<?php

namespace App\Http\Controllers;

use App\Models\TocaAmount;
use App\Models\TocaPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class TocaController extends Controller
{
    /**
     * Display the Toca policies index view.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        $this->authorize('viewAny', TocaPolicy::class);
        return view('tocaPolicy.index');
    }

    /**
     * Retrieve paginated Toca policies with optional search and sort.
     * Includes associated TocaAmount records in the 'toca_amounts' field.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getTocaPolicies(Request $request): JsonResponse
    {
        $this->authorize('viewAny', TocaPolicy::class);

        $query = TocaPolicy::query()->with('tocaAmounts');

        // Handle search
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('short_name', 'like', "%{$search}%")
                  ->orWhereHas('tocaAmounts', function ($q) use ($search) {
                      $q->where('min_amount', 'like', "%{$search}%")
                        ->orWhere('max_amount', 'like', "%{$search}%");
                  });
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
        $tocaPolicies = $query->paginate($limit);

        $data = $tocaPolicies->getCollection()->map(function (TocaPolicy $tocaPolicy) {
            return [
                'id' => $tocaPolicy->id,
                'short_name' => e($tocaPolicy->short_name), // Escape to prevent JS issues
                'name' => e($tocaPolicy->name), // Escape to prevent JS issues
                'is_active' => (bool) $tocaPolicy->is_active,
                // Format TocaAmount records as a comma-separated string (e.g., "$100-$200, $300-$400")
                'toca_amounts' => $tocaPolicy->tocaAmounts->isNotEmpty()
                    ? $tocaPolicy->tocaAmounts->map(function (TocaAmount $amount, $index) {
                        return sprintf("%02d: $%.2f-$%.2f", $index + 1, $amount->min_amount, $amount->max_amount);
                    })->values()
                    : [],
                'created_at' => $tocaPolicy->created_at?->toDateTimeString(),
            ];
        });

        return response()->json([
            'data' => $data,
            'recordsTotal' => $tocaPolicies->total(),
            'recordsFiltered' => $tocaPolicies->total(),
            'draw' => (int) $request->input('draw', 1),
        ]);
    }

    /**
     * Get validation rules for TocaPolicy creation/update.
     *
     * @param int|null $tocaPolicyId
     * @return array
     */
    private function tocaPolicyValidationRules(?int $tocaPolicyId = null): array
    {
        return [
            'short_name' => [
                'required',
                'string',
                'max:255',
                'unique:toca_policies,short_name' . ($tocaPolicyId ? ',' . $tocaPolicyId : ''),
            ],
            'name' => ['required', 'string', 'max:255'],
            'is_active' => 'integer',
        ];
    }

    /**
     * Store a new TocaPolicy.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', TocaPolicy::class);

        $validated = Validator::make($request->all(), $this->tocaPolicyValidationRules())->validate();

        DB::beginTransaction();
        try {
            $tocaPolicy = TocaPolicy::create([
                'short_name' => $validated['short_name'],
                'name' => $validated['name'],
                'is_active' => $validated['is_active'] ?? 1,
            ]);
            DB::commit();

            return response()->json([
                'message' => 'TocaPolicy created successfully.',
                'data' => [
                    'id' => $tocaPolicy->id,
                    'short_name' => $tocaPolicy->short_name,
                    'name' => $tocaPolicy->name,
                    'is_active' => (bool) $tocaPolicy->is_active,
                    'created_at' => $tocaPolicy->created_at?->toDateTimeString(),
                ]
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to create TocaPolicy.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a TocaPolicy for editing.
     *
     * @param TocaPolicy $tocaPolicy
     * @return JsonResponse
     */
    public function edit(TocaPolicy $tocaPolicy): JsonResponse
    {
        $this->authorize('update', $tocaPolicy);
        return response()->json([
            'data' => $tocaPolicy
        ]);
    }

    /**
     * Update an existing TocaPolicy.
     *
     * @param Request $request
     * @param TocaPolicy $tocaPolicy
     * @return JsonResponse
     */
    public function update(Request $request, TocaPolicy $tocaPolicy): JsonResponse
    {
        $this->authorize('update', $tocaPolicy);

        $validated = Validator::make($request->all(), $this->tocaPolicyValidationRules($tocaPolicy->id))->validate();

        DB::beginTransaction();
        try {
            $tocaPolicy->update([
                'short_name' => $validated['short_name'],
                'name' => $validated['name'],
                'is_active' => $validated['is_active'] ?? 1,
            ]);
            DB::commit();

            return response()->json([
                'message' => 'TocaPolicy updated successfully.',
                'data' => [
                    'id' => $tocaPolicy->id,
                    'short_name' => $tocaPolicy->short_name,
                    'name' => $tocaPolicy->name,
                    'is_active' => (bool) $tocaPolicy->is_active,
                    'created_at' => $tocaPolicy->created_at?->toDateTimeString(),
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to update TocaPolicy.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a TocaPolicy.
     *
     * @param TocaPolicy $tocaPolicy
     * @return JsonResponse
     */
    public function destroy(TocaPolicy $tocaPolicy): JsonResponse
    {
        $this->authorize('delete', $tocaPolicy);

        try {
            $tocaPolicy->delete();
            return response()->json([
                'message' => 'TocaPolicy deleted successfully.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete TocaPolicy.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}