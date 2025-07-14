<?php

namespace App\Http\Controllers;

use App\Models\TocaAmount;
use App\Models\TocaPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class TocaAmountController extends Controller
{
    /**
     * Display the Toca Amounts index view.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        $this->authorize('viewAny', TocaAmount::class);
        return view('tocaPolicy.amount');
    }

    /**
     * Retrieve paginated Toca amounts with optional search and sort.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getTocaAmounts(Request $request): JsonResponse
    {
        $this->authorize('viewAny', TocaAmount::class);

        $query = TocaAmount::query()->with('tocaPolicy');

        // Handle search
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->whereHas('tocaPolicy', function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('short_name', 'like', "%{$search}%");
                });
            });
        }

        // Handle sorting
        $allowedSortColumns = ['min_amount', 'max_amount', 'is_active', 'created_at', 'toca_id'];
        $sortColumn = $request->input('sortColumn', 'created_at');
        $sortDirection = strtolower($request->input('sortDirection', 'desc'));

        $sortColumn = in_array($sortColumn, $allowedSortColumns) ? $sortColumn : 'created_at';
        $sortDirection = in_array($sortDirection, ['asc', 'desc']) ? $sortDirection : 'desc';

        $query->orderBy($sortColumn, $sortDirection);

        // Handle pagination
        $limit = max(1, (int) $request->input('limit', 10));
        $tocaAmounts = $query->paginate($limit);

        $data = $tocaAmounts->getCollection()->map(function (TocaAmount $tocaAmount) {
            return [
                'id' => $tocaAmount->id,
                'min_amount' => $tocaAmount->min_amount,
                'max_amount' => $tocaAmount->max_amount,
                'is_active' => (bool) $tocaAmount->is_active,
                'toca_id' => $tocaAmount->toca_id,
                'toca_name' => $tocaAmount->tocaPolicy ? $tocaAmount->tocaPolicy->name : null,
                'created_at' => $tocaAmount->created_at?->toDateTimeString(),
            ];
        });

        return response()->json([
            'data' => $data,
            'recordsTotal' => $tocaAmounts->total(),
            'recordsFiltered' => $tocaAmounts->total(),
            'draw' => (int) $request->input('draw', 1),
        ]);
    }

    /**
     * Get validation rules for Toca Amount creation/update.
     *
     * @param int|null $tocaAmountId
     * @return array
     */
    private function tocaAmountValidationRules(?int $tocaAmountId = null): array
    {
        return [
            'min_amount' => ['required', 'numeric', 'min:0'],
            'max_amount' => ['required', 'numeric', 'min:0', 'gte:min_amount'],
            'toca_id' => ['required', 'integer', 'exists:toca_policies,id'],
            'is_active' => 'integer',
        ];
    }

    /**
     * Store a new Toca Amount.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', TocaAmount::class);

        $validated = Validator::make($request->all(), $this->tocaAmountValidationRules())->validate();

        DB::beginTransaction();
        try {
            $tocaAmount = TocaAmount::create([
                'min_amount' => $validated['min_amount'],
                'max_amount' => $validated['max_amount'],
                'toca_id' => $validated['toca_id'],
                'is_active' => $validated['is_active'] ?? 1,
            ]);
            DB::commit();

            return response()->json([
                'message' => 'Toca Amount created successfully.',
                'data' => $tocaAmount
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to create Toca Amount.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a Toca Amount for editing.
     *
     * @param TocaAmount $tocaAmount
     * @return JsonResponse
     */
    public function edit(TocaAmount $tocaAmount): JsonResponse
    {
        $this->authorize('update', $tocaAmount);
        return response()->json([
            'data' => $tocaAmount
        ]);
    }

    /**
     * Update an existing Toca Amount.
     *
     * @param Request $request
     * @param TocaAmount $tocaAmount
     * @return JsonResponse
     */
    public function update(Request $request, TocaAmount $tocaAmount): JsonResponse
    {
        $this->authorize('update', $tocaAmount);

        $validated = Validator::make($request->all(), $this->tocaAmountValidationRules($tocaAmount->id))->validate();

        DB::beginTransaction();
        try {
            $tocaAmount->update([
                'min_amount' => $validated['min_amount'],
                'max_amount' => $validated['max_amount'],
                'toca_id' => $validated['toca_id'],
                'is_active' => $validated['is_active'] ?? 1,
            ]);
            DB::commit();

            return response()->json([
                'message' => 'Toca Amount updated successfully.',
                'data' => $tocaAmount
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to update Toca Amount.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a Toca Amount.
     *
     * @param TocaAmount $tocaAmount
     * @return JsonResponse
     */
    public function destroy(TocaAmount $tocaAmount): JsonResponse
    {
        $this->authorize('delete', $tocaAmount);

        try {
            $tocaAmount->delete();
            return response()->json([
                'message' => 'Toca Amount deleted successfully.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete Toca Amount.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}