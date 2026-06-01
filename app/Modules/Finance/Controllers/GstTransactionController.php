<?php

namespace App\Modules\Finance\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Models\GstTransaction;
use App\Modules\Finance\Resources\GstTransactionResource;
use App\Modules\Workspace\Models\Workspace;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GstTransactionController extends Controller
{
    use ApiResponse;

    public function index(Request $request, int $workspaceId): JsonResponse
    {
        $workspace = Workspace::findOrFail($workspaceId);
        abort_unless($workspace->hasMember($request->user()), 403);

        $query = GstTransaction::where('workspace_id', $workspaceId)
            ->orderByDesc('invoice_date');

        if ($from = $request->query('date_from')) {
            $query->where('invoice_date', '>=', $from);
        }
        if ($to = $request->query('date_to')) {
            $query->where('invoice_date', '<=', $to);
        }
        if ($type = $request->query('transaction_type')) {
            $query->where('transaction_type', $type);
        }
        if ($orderId = $request->query('order_id')) {
            $query->where('order_id', $orderId);
        }
        if ($state = $request->query('ship_to_state')) {
            $query->where('ship_to_state', $state);
        }

        $paginator = $query->paginate((int) $request->query('per_page', 50));

        return $this->paginated($paginator);
    }

    public function summary(Request $request, int $workspaceId): JsonResponse
    {
        $workspace = Workspace::findOrFail($workspaceId);
        abort_unless($workspace->hasMember($request->user()), 403);

        $from = $request->query('date_from', now()->startOfMonth()->toDateString());
        $to   = $request->query('date_to',   now()->toDateString());

        $agg = GstTransaction::where('workspace_id', $workspaceId)
            ->whereBetween('invoice_date', [$from, $to])
            ->selectRaw("
                COUNT(*) as total_invoices,
                COALESCE(SUM(taxable_value), 0) as total_taxable,
                COALESCE(SUM(igst_amount), 0) as total_igst,
                COALESCE(SUM(cgst_amount), 0) as total_cgst,
                COALESCE(SUM(sgst_amount), 0) as total_sgst
            ")
            ->first();

        $byType = GstTransaction::where('workspace_id', $workspaceId)
            ->whereBetween('invoice_date', [$from, $to])
            ->whereNotNull('transaction_type')
            ->selectRaw('transaction_type, COUNT(*) as cnt')
            ->groupBy('transaction_type')
            ->pluck('cnt', 'transaction_type');

        $byState = GstTransaction::where('workspace_id', $workspaceId)
            ->whereBetween('invoice_date', [$from, $to])
            ->whereNotNull('ship_to_state')
            ->selectRaw('ship_to_state, SUM(taxable_value) as value')
            ->groupBy('ship_to_state')
            ->orderByDesc('value')
            ->limit(10)
            ->pluck('value', 'ship_to_state')
            ->map(fn($v) => round((float) $v, 2));

        return $this->success([
            'period'        => ['start' => $from, 'end' => $to],
            'total_invoices'=> (int) $agg->total_invoices,
            'total_taxable' => round((float) $agg->total_taxable, 2),
            'total_igst'    => round((float) $agg->total_igst, 2),
            'total_cgst'    => round((float) $agg->total_cgst, 2),
            'total_sgst'    => round((float) $agg->total_sgst, 2),
            'total_tax'     => round(
                (float) $agg->total_igst + (float) $agg->total_cgst + (float) $agg->total_sgst, 2
            ),
            'by_type'       => $byType,
            'by_state'      => $byState,
        ]);
    }
}
