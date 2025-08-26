<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;
use App\Models\Approval;

class SharePendingApprovalCount
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();
        $pendingCount = 0;
        $pendingList = collect();

        if ($user) {
            if ($user->hasRole('admin')) {
                $rawApprovals = Approval::where('approval_status', 'Pending')
                    ->with(['requester:id,name', 'responder:id,name'])
                    ->latest()
                    ->get();

                $filtered = $rawApprovals->filter(function ($approval) {
                    $allApprovals = Approval::where('approvable_type', $approval->approvable_type)
                        ->where('approvable_id', $approval->approvable_id)
                        ->orderBy('ordinal')
                        ->orderBy('id')
                        ->get();

                    $previous = $allApprovals->filter(function ($a) use ($approval) {
                        return ($a->ordinal < $approval->ordinal) ||
                            ($a->ordinal === $approval->ordinal && $a->id < $approval->id);
                    });

                    // Exclude if any previous is Pending
                    if ($previous->firstWhere('approval_status', 'Pending')) {
                        return false;
                    }

                    // Exclude if any previous is Rejected
                    if ($previous->firstWhere('approval_status', 'Rejected')) {
                        return false;
                    }

                    // Exclude if any previous is Returned
                    if ($previous->firstWhere('approval_status', 'Returned')) {
                        return false;
                    }

                    return true;
                });

                $pendingCount = $filtered->count();

                $pendingList = $filtered->take(10)->map(function ($approval) {
                    return $this->mapWithRoute($approval);
                });
            } else {
                $rawApprovals = Approval::where('approval_status', 'Pending')
                    ->where('responder_id', $user->id)
                    ->with(['requester:id,name', 'responder:id,name'])
                    ->get();

                $filtered = $rawApprovals->filter(function ($approval) {
                    $allApprovals = Approval::where('approvable_type', $approval->approvable_type)
                        ->where('approvable_id', $approval->approvable_id)
                        ->orderBy('ordinal')
                        ->orderBy('id')
                        ->get();

                    // Get all previous steps by ordinal and id
                    $previous = $allApprovals->filter(function ($a) use ($approval) {
                        return ($a->ordinal < $approval->ordinal) ||
                            ($a->ordinal === $approval->ordinal && $a->id < $approval->id);
                    });

                    // Exclude if any previous is Pending
                    if ($previous->firstWhere('approval_status', 'Pending')) {
                        return false;
                    }

                    // Exclude if any previous is Rejected
                    if ($previous->firstWhere('approval_status', 'Rejected')) {
                        return false;
                    }

                    // Exclude if any previous is Returned
                    if ($previous->firstWhere('approval_status', 'Returned')) {
                        return false;
                    }

                    // Keep only the first duplicate (same ordinal + type + pending status)
                    $duplicates = $allApprovals->filter(function ($a) use ($approval) {
                        return $a->ordinal === $approval->ordinal &&
                            $a->request_type === $approval->request_type &&
                            $a->approval_status === 'Pending';
                    });

                    $firstDuplicate = $duplicates->sortBy('id')->first();
                    return $firstDuplicate && $firstDuplicate->id === $approval->id;
                });

                $pendingCount = $filtered->count();

                $pendingList = $filtered->sortByDesc('created_at')->take(10)->map(function ($approval) {
                    return $this->mapWithRoute($approval);
                });
            }
        }

        // Share to all views
        View::share([
            'pendingApprovalCount' => $pendingCount,
            'pendingApprovalsList' => $pendingList,
        ]);

        return $next($request);
    }
    /**
     * Map approval with route URL for viewing.
     */
    private function mapWithRoute(Approval $approval): array
    {
        $typeMap = [
            'App\\Models\\MainStockBeginning' => 'stock-beginnings',
            'App\\Models\\PurchaseRequest' => 'purchase-requests',
            'App\\Models\\PurchaseOrder' => 'purchase-orders',
            // Add more mappings if needed
        ];

        $slug = $typeMap[$approval->approvable_type] ?? null;

        return [
            'id' => $approval->id,
            'document_name' => $approval->document_name,
            'document_reference' => $approval->document_reference,
            'request_type' => $approval->request_type,
            'approval_status' => $approval->approval_status,
            'created_at' => $approval->created_at?->toDateTimeString(),
            'responder_name' => $approval->responder->name ?? null,
            'requester_name' => $approval->requester->name ?? null,
            'route_url' => $slug ? url("$slug/{$approval->approvable_id}/show") : null,
        ];
    }
}
