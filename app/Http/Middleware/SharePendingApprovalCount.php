<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use App\Models\Approval;

class SharePendingApprovalCount
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();
        $pendingCount = 0;
        $pendingList = collect();
        $unseenCount = 0; // 👈 unseen only counter
        $unseenList = collect(); // 👈 unseen only list

        if ($user) {
            $rawApprovals = Approval::where('approval_status', 'Pending')
                ->where('responder_id', $user->id)
                ->with(['requester:id,name,profile_url', 'responder:id,name,profile_url'])
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

                if ($previous->firstWhere('approval_status', 'Pending')
                    || $previous->firstWhere('approval_status', 'Rejected')
                    || $previous->firstWhere('approval_status', 'Returned')) {
                    return false;
                }

                $duplicates = $allApprovals->filter(function ($a) use ($approval) {
                    return $a->ordinal === $approval->ordinal &&
                        $a->request_type === $approval->request_type &&
                        $a->approval_status === 'Pending';
                });

                $firstDuplicate = $duplicates->sortBy('id')->first();
                return $firstDuplicate && $firstDuplicate->id === $approval->id;
            });

            $pendingCount = $filtered->count();

            // unseen (is_seen = false) only
            $unseenFiltered = $filtered->where('is_seen', false);
            $unseenCount = $unseenFiltered->count();

            $pendingList = $filtered->sortByDesc('created_at')->take(10)->map(fn($a) => $this->mapWithRoute($a));
            $unseenList = $unseenFiltered->sortByDesc('created_at')->take(10)->map(fn($a) => $this->mapWithRoute($a));
        }

        // Share globally
        View::share([
            'pendingApprovalCount' => $pendingCount,
            'pendingApprovalsList' => $pendingList,
            'unseenApprovalCount'  => $unseenCount,
            'unseenApprovalsList'  => $unseenList,
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
            'App\\Models\\StockRequest' => 'stock-requests',
            'App\\Models\\StockTransfer' => 'stock-transfers',
            'App\\Models\\DigitalDocsApproval' => 'digital-docs-approvals',
            'App\\Models\\PurchaseRequest' => 'purchase-requests',
            'App\\Models\\MonthlyStockReport' => 'monthly-stock-reports',
            'App\\Models\\StockCount' => 'stock-counts',
            'App\\Models\\WarehouseProductReport' => 'stock-reports',
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
            'requester_photo' => $approval->requester->profile_url ?? null,
            'route_url' => $slug ? url("/approvals/$slug/{$approval->approvable_id}/show") : null,
        ];
    }
}
