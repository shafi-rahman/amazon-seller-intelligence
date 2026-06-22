<?php

namespace App\Modules\Finance\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Models\Settlement;
use App\Modules\Finance\Resources\SettlementResource;
use App\Modules\Workspace\Models\Workspace;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettlementController extends Controller
{
    use ApiResponse;

    public function index(Request $request, int $workspaceId): JsonResponse
    {
        $workspace = Workspace::findOrFail($workspaceId);
        abort_unless($workspace->hasMember($request->user()), 403);

        $query = Settlement::where('workspace_id', $workspaceId)
            ->orderByDesc('deposit_date')
            ->orderByDesc('posted_date');

        if ($from = $request->query('date_from')) {
            $query->where('deposit_date', '>=', $from);
        }
        if ($to = $request->query('date_to')) {
            $query->where('deposit_date', '<=', $to);
        }
        if ($settlementId = $request->query('settlement_id')) {
            $query->where('settlement_id', $settlementId);
        }
        if ($type = $request->query('transaction_type')) {
            $query->where('transaction_type', $type);
        }
        if ($orderId = $request->query('order_id')) {
            $query->where('order_id', $orderId);
        }

        $paginator = $query->paginate($this->perPage($request, 50));

        return $this->paginatedThrough($paginator, SettlementResource::class);
    }

    public function summary(Request $request, int $workspaceId): JsonResponse
    {
        $workspace = Workspace::findOrFail($workspaceId);
        abort_unless($workspace->hasMember($request->user()), 403);

        $from = $request->query('date_from', now()->startOfMonth()->toDateString());
        $to   = $request->query('date_to',   now()->toDateString());

        $base = Settlement::where('workspace_id', $workspaceId)
            ->whereBetween('deposit_date', [$from, $to]);

        $agg = $base->selectRaw("
            COUNT(DISTINCT settlement_id) as settlement_cycles,
            COALESCE(SUM(deposited_amount), 0) as total_deposited,
            COALESCE(SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END), 0) as gross_payments,
            COALESCE(ABS(SUM(CASE WHEN amount < 0 THEN amount ELSE 0 END)), 0) as total_fees
        ")->first();

        $byType = $base->clone()
            ->whereNotNull('transaction_type')
            ->selectRaw('transaction_type, COUNT(*) as cnt, SUM(amount) as total')
            ->groupBy('transaction_type')
            ->orderByDesc('total')
            ->limit(10)
            ->get()
            ->map(fn($r) => [
                'type'  => $r->transaction_type,
                'count' => (int) $r->cnt,
                'total' => round((float) $r->total, 2),
            ]);

        return $this->success([
            'period'            => ['start' => $from, 'end' => $to],
            'settlement_cycles' => (int) $agg->settlement_cycles,
            'total_deposited'   => round((float) $agg->total_deposited, 2),
            'gross_payments'    => round((float) $agg->gross_payments, 2),
            'total_fees'        => round((float) $agg->total_fees, 2),
            'by_transaction_type' => $byType,
        ]);
    }
}
