<?php

namespace App\Modules\Finance\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Models\BankTransaction;
use App\Modules\Finance\Resources\BankTransactionResource;
use App\Modules\Workspace\Models\Workspace;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BankTransactionController extends Controller
{
    use ApiResponse;

    public function index(Request $request, int $workspaceId): JsonResponse
    {
        $workspace = Workspace::findOrFail($workspaceId);
        abort_unless($workspace->hasMember($request->user()), 403);

        $query = BankTransaction::where('workspace_id', $workspaceId)
            ->orderByDesc('transaction_date');

        if ($from = $request->query('date_from')) {
            $query->where('transaction_date', '>=', $from);
        }
        if ($to = $request->query('date_to')) {
            $query->where('transaction_date', '<=', $to);
        }
        if ($type = $request->query('type')) {
            match ($type) {
                'credit' => $query->where('credit_amount', '>', 0),
                'debit'  => $query->where('debit_amount', '>', 0),
                default  => null,
            };
        }
        if ($bank = $request->query('bank_name')) {
            $query->where('bank_name', $bank);
        }
        if ($search = $request->query('search')) {
            $query->where('description', 'ILIKE', "%{$search}%")
                  ->orWhere('reference', 'ILIKE', "%{$search}%");
        }

        $paginator = $query->paginate($this->perPage($request, 50));

        return $this->paginatedThrough($paginator, BankTransactionResource::class);
    }

    public function summary(Request $request, int $workspaceId): JsonResponse
    {
        $workspace = Workspace::findOrFail($workspaceId);
        abort_unless($workspace->hasMember($request->user()), 403);

        $from = $request->query('date_from', now()->startOfMonth()->toDateString());
        $to   = $request->query('date_to',   now()->toDateString());

        $agg = BankTransaction::where('workspace_id', $workspaceId)
            ->whereBetween('transaction_date', [$from, $to])
            ->selectRaw("
                COUNT(*) as total_transactions,
                COALESCE(SUM(credit_amount), 0) as total_credits,
                COALESCE(SUM(debit_amount), 0) as total_debits,
                COALESCE(SUM(CASE WHEN description ILIKE '%amazon%' THEN credit_amount ELSE 0 END), 0) as amazon_credits
            ")
            ->first();

        return $this->success([
            'period'             => ['start' => $from, 'end' => $to],
            'total_transactions' => (int) $agg->total_transactions,
            'total_credits'      => round((float) $agg->total_credits, 2),
            'total_debits'       => round((float) $agg->total_debits, 2),
            'net_cashflow'       => round((float) $agg->total_credits - (float) $agg->total_debits, 2),
            'amazon_credits'     => round((float) $agg->amazon_credits, 2),
        ]);
    }
}
