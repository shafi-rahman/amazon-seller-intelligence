<?php

namespace App\Modules\Reconciliation\Services;

use App\Modules\Finance\Models\BankTransaction;
use App\Modules\Finance\Models\GstTransaction;
use App\Modules\Finance\Models\Order;
use App\Modules\Finance\Models\Settlement;
use App\Modules\Reconciliation\Events\ReconciliationCompleted;
use App\Modules\Reconciliation\Models\ReconciliationMatch;
use App\Modules\Reconciliation\Models\ReconciliationReport;
use App\Modules\Reconciliation\Models\ReconciliationRun;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ReconciliationEngine
{
    // Tolerances
    private const REFUND_AMOUNT_TOLERANCE = 0.01;   // ±1%
    private const REFUND_DATE_DAYS        = 30;
    private const BANK_DATE_TOLERANCE     = 3;       // ±3 days for exact match
    private const BANK_DATE_TDS           = 5;       // ±5 days for TDS match
    private const BANK_TDS_TOLERANCE      = 0.02;   // ±2% for TDS
    private const GST_MISMATCH_THRESHOLD  = 1.00;   // ₹1 tolerance

    public function run(ReconciliationRun $run): void
    {
        $run->update(['status' => 'running', 'started_at' => now()]);

        try {
            // All passes + report writes run in ONE transaction so a mid-run
            // failure leaves no partial financial data. Clearing prior rows for
            // this run first makes a retry (tries=2, or a re-queued job) idempotent
            // — it can never duplicate matches/reports.
            DB::transaction(function () use ($run) {
                ReconciliationMatch::where('reconciliation_run_id', $run->id)->delete();
                ReconciliationReport::where('reconciliation_run_id', $run->id)->delete();

                $this->passA_exactOrderSettlement($run);
                $this->passB_fuzzyRefundMatch($run);
                $this->passCD_settlementBank($run);
                $this->step3_gstCrossCheck($run);
                $this->generateAllReports($run);
            });

            $run->update(['status' => 'completed', 'completed_at' => now()]);
            ReconciliationCompleted::dispatch($run->fresh());
        } catch (\Throwable $e) {
            $run->update([
                'status'       => 'failed',
                'completed_at' => now(),
                'summary'      => ['error' => $e->getMessage()],
            ]);
            throw $e;
        }
    }

    // ─── Pass A: Exact order ↔ settlement match ─────────────────────────────

    private function passA_exactOrderSettlement(ReconciliationRun $run): void
    {
        $from = $run->period_start->toDateString().' 00:00:00';
        $to   = $run->period_end->toDateString().' 23:59:59';

        // Use raw SQL for performance with large datasets
        DB::statement("
            INSERT INTO reconciliation_matches
                (reconciliation_run_id, order_id, settlement_id, match_type, match_confidence, status, created_at)
            SELECT DISTINCT ON (o.id)
                :run_id, o.id, s.id, 'exact', 100, 'matched', NOW()
            FROM orders o
            INNER JOIN settlements s
                ON  s.order_id    = o.amazon_order_id
                AND s.workspace_id = o.workspace_id
                AND (s.transaction_type ILIKE 'Order%'
                     OR s.transaction_type ILIKE 'Shipment%'
                     OR s.transaction_type = 'ItemPrice')
            WHERE o.workspace_id = :workspace_id
              AND o.purchase_date >= :from_date::timestamptz
              AND o.purchase_date <= :to_date::timestamptz
              AND o.order_status NOT IN ('Cancelled', 'Pending')
            ORDER BY o.id ASC, s.id ASC
        ", [
            'run_id'       => $run->id,
            'workspace_id' => $run->workspace_id,
            'from_date'    => $from,
            'to_date'      => $to,
        ]);
    }

    // ─── Pass B: Fuzzy refund match for cancelled orders ────────────────────

    private function passB_fuzzyRefundMatch(ReconciliationRun $run): void
    {
        $from = $run->period_start->toDateString().' 00:00:00';
        $to   = $run->period_end->toDateString().' 23:59:59';

        // Find cancelled/returned orders not already matched
        $cancelledOrders = Order::where('workspace_id', $run->workspace_id)
            ->whereBetween('purchase_date', [$from, $to])
            ->whereIn('order_status', ['Cancelled', 'Returned'])
            ->whereNotIn('id', function ($q) use ($run) {
                $q->select('order_id')
                    ->from('reconciliation_matches')
                    ->where('reconciliation_run_id', $run->id)
                    ->whereNotNull('order_id');
            })
            ->select('id', 'amazon_order_id', 'purchase_date', 'item_price')
            ->get();

        if ($cancelledOrders->isEmpty()) {
            return;
        }

        $matches = [];
        foreach ($cancelledOrders as $order) {
            // Find a refund settlement close in amount and within 30 days
            $refund = Settlement::where('workspace_id', $run->workspace_id)
                ->where('transaction_type', 'ILIKE', 'Refund%')
                ->where(function ($q) use ($order) {
                    $amount    = abs((float) $order->item_price);
                    $tolerance = $amount * self::REFUND_AMOUNT_TOLERANCE;
                    $q->whereBetween(DB::raw('ABS(amount)'), [
                        $amount - $tolerance,
                        $amount + $tolerance,
                    ]);
                })
                ->whereBetween('posted_date', [
                    Carbon::parse($order->purchase_date)->toDateString(),
                    Carbon::parse($order->purchase_date)->addDays(self::REFUND_DATE_DAYS)->toDateString(),
                ])
                ->first();

            if ($refund) {
                $matches[] = [
                    'reconciliation_run_id' => $run->id,
                    'order_id'              => $order->id,
                    'settlement_id'         => $refund->id,
                    'match_type'            => 'fuzzy_refund',
                    'match_confidence'      => 85,
                    'status'                => 'matched',
                    'created_at'            => now()->toDateTimeString(),
                ];
            }
        }

        if (!empty($matches)) {
            ReconciliationMatch::insert($matches);
        }
    }

    // ─── Pass C+D: Settlement cycles ↔ bank credits ─────────────────────────

    private function passCD_settlementBank(ReconciliationRun $run): void
    {
        $from = $run->period_start->toDateString();
        $to   = $run->period_end->toDateString();

        // Get unique settlement cycles in the period
        $cycles = DB::select("
            SELECT
                MIN(s.id)          AS settlement_id,
                s.settlement_id    AS cycle_id,
                s.deposit_date,
                s.deposited_amount
            FROM settlements s
            WHERE s.workspace_id = :workspace_id
              AND s.deposit_date BETWEEN :from_date AND :to_date
              AND s.deposited_amount IS NOT NULL
              AND s.deposited_amount > 0
            GROUP BY s.settlement_id, s.deposit_date, s.deposited_amount
        ", [
            'workspace_id' => $run->workspace_id,
            'from_date'    => $from,
            'to_date'      => $to,
        ]);

        $matches = [];

        foreach ($cycles as $cycle) {
            // Pass C: Exact amount + description contains settlement_id or 'amazon'
            $bank = BankTransaction::where('workspace_id', $run->workspace_id)
                ->where('credit_amount', $cycle->deposited_amount)
                ->whereBetween('transaction_date', [
                    Carbon::parse($cycle->deposit_date)->subDays(self::BANK_DATE_TOLERANCE)->toDateString(),
                    Carbon::parse($cycle->deposit_date)->addDays(self::BANK_DATE_TOLERANCE)->toDateString(),
                ])
                ->where(function ($q) use ($cycle) {
                    $q->where('description', 'ILIKE', '%'.$cycle->cycle_id.'%')
                      ->orWhere('description', 'ILIKE', '%amazon%')
                      ->orWhere('reference', 'ILIKE', '%'.$cycle->cycle_id.'%');
                })
                ->first();

            if ($bank) {
                $matches[] = [
                    'reconciliation_run_id' => $run->id,
                    'settlement_id'         => $cycle->settlement_id,
                    'bank_transaction_id'   => $bank->id,
                    'match_type'            => 'settlement_bank_exact',
                    'match_confidence'      => 100,
                    'status'                => 'matched',
                    'created_at'            => now()->toDateTimeString(),
                ];
                continue;
            }

            // Pass D: TDS tolerance (±2%) — bank description ILIKE '%amazon%'
            $amount     = (float) $cycle->deposited_amount;
            $tdsMin     = $amount * (1 - self::BANK_TDS_TOLERANCE);
            $tdsMax     = $amount * (1 + self::BANK_TDS_TOLERANCE);

            $bankTds = BankTransaction::where('workspace_id', $run->workspace_id)
                ->whereBetween('credit_amount', [$tdsMin, $tdsMax])
                ->whereBetween('transaction_date', [
                    Carbon::parse($cycle->deposit_date)->subDays(self::BANK_DATE_TDS)->toDateString(),
                    Carbon::parse($cycle->deposit_date)->addDays(self::BANK_DATE_TDS)->toDateString(),
                ])
                ->where('description', 'ILIKE', '%amazon%')
                ->first();

            if ($bankTds) {
                $mismatch = round(abs((float) $bankTds->credit_amount - $amount), 2);
                $matches[] = [
                    'reconciliation_run_id' => $run->id,
                    'settlement_id'         => $cycle->settlement_id,
                    'bank_transaction_id'   => $bankTds->id,
                    'match_type'            => 'settlement_bank_tds',
                    'match_confidence'      => 70,
                    'status'                => 'partial',
                    'mismatch_amount'       => $mismatch,
                    'notes'                 => "TDS deduction: ₹{$mismatch}",
                    'created_at'            => now()->toDateTimeString(),
                ];
            }
            // If no bank match found, the settlement cycle is unmatched (reported in missing_credits)
        }

        if (!empty($matches)) {
            ReconciliationMatch::insert($matches);
        }
    }

    // ─── Step 3: GST cross-check ────────────────────────────────────────────

    private function step3_gstCrossCheck(ReconciliationRun $run): void
    {
        // Already stored in reconciliation_reports.gst_mismatch during generateAllReports()
        // This method pre-computes the mismatch data for use in report generation
    }

    // ─── Report generation ──────────────────────────────────────────────────

    private function generateAllReports(ReconciliationRun $run): void
    {
        $from = $run->period_start->toDateString().' 00:00:00';
        $to   = $run->period_end->toDateString().' 23:59:59';

        $reports = [
            'missing_settlements' => $this->buildMissingSettlements($run, $from, $to),
            'missing_credits'     => $this->buildMissingCredits($run),
            'refund_impact'       => $this->buildRefundImpact($run, $from, $to),
            'return_impact'       => $this->buildReturnImpact($run, $from, $to),
            'gst_mismatch'        => $this->buildGstMismatch($run, $from, $to),
            'summary'             => [],  // built after others
        ];

        // Build summary from computed data
        $reports['summary'] = $this->buildSummary($run, $reports, $from, $to);

        // Store run-level summary
        $run->update(['summary' => [
            'total_orders'        => $reports['summary']['total_orders'] ?? 0,
            'matched_orders'      => $reports['summary']['matched_orders'] ?? 0,
            'unmatched_orders'    => $reports['summary']['unmatched_orders'] ?? 0,
            'total_order_value'   => $reports['summary']['total_order_value'] ?? 0,
            'total_settled'       => $reports['summary']['total_settled'] ?? 0,
            'missing_credits'     => count($reports['missing_credits']['rows'] ?? []),
            'gst_mismatches'      => count($reports['gst_mismatch']['rows'] ?? []),
        ]]);

        // Persist each report
        foreach ($reports as $type => $data) {
            ReconciliationReport::create([
                'reconciliation_run_id' => $run->id,
                'workspace_id'          => $run->workspace_id,
                'report_type'           => $type,
                'report_data'           => $data,
            ]);
        }
    }

    private function buildMissingSettlements(ReconciliationRun $run, string $from, string $to): array
    {
        $matchedOrderIds = ReconciliationMatch::where('reconciliation_run_id', $run->id)
            ->whereNotNull('order_id')
            ->whereIn('match_type', ['exact', 'fuzzy_refund'])
            ->pluck('order_id')
            ->toArray();

        $unmatched = Order::where('workspace_id', $run->workspace_id)
            ->whereBetween('purchase_date', [$from, $to])
            ->whereNotIn('id', $matchedOrderIds ?: [0])
            ->select('id', 'amazon_order_id', 'purchase_date', 'order_status', 'sku', 'item_price', 'fulfillment_channel')
            ->orderBy('purchase_date')
            ->get();

        $rows = $unmatched->map(function ($o) use ($run) {
            $daysSince = Carbon::parse($o->purchase_date)->diffInDays(now());
            $reason = match(true) {
                $o->order_status === 'Cancelled' => 'cancelled_no_refund_settlement',
                $daysSince <= 14               => 'pending_settlement',
                default                        => 'settlement_missing',
            };
            return [
                'amazon_order_id'  => $o->amazon_order_id,
                'purchase_date'    => $o->purchase_date,
                'order_status'     => $o->order_status,
                'sku'              => $o->sku,
                'item_price'       => (float) $o->item_price,
                'fulfillment'      => $o->fulfillment_channel,
                'days_since_order' => $daysSince,
                'reason'           => $reason,
            ];
        })->toArray();

        $byReason = collect($rows)->groupBy('reason')->map->count()->toArray();

        return [
            'count'       => count($rows),
            'total_value' => round(collect($rows)->sum('item_price'), 2),
            'by_reason'   => $byReason,
            'rows'        => $rows,
        ];
    }

    private function buildMissingCredits(ReconciliationRun $run): array
    {
        $from = $run->period_start->toDateString();
        $to   = $run->period_end->toDateString();

        // Settlement cycles that have no bank match in this run
        $matchedSettlementIds = ReconciliationMatch::where('reconciliation_run_id', $run->id)
            ->whereNotNull('bank_transaction_id')
            ->whereNotNull('settlement_id')
            ->pluck('settlement_id')
            ->toArray();

        // Use a subquery to avoid aggregate function in WHERE clause (invalid SQL)
        $allCycles = DB::select("
            SELECT
                MIN(s.id)          AS min_id,
                s.settlement_id    AS cycle_id,
                s.deposit_date,
                MAX(s.deposited_amount) AS deposited_amount,
                s.currency
            FROM settlements s
            WHERE s.workspace_id = :workspace_id
              AND s.deposit_date BETWEEN :from_date AND :to_date
              AND s.deposited_amount IS NOT NULL
              AND s.deposited_amount > 0
            GROUP BY s.settlement_id, s.deposit_date, s.currency
        ", [
            'workspace_id' => $run->workspace_id,
            'from_date'    => $from,
            'to_date'      => $to,
        ]);

        // Filter out cycles whose representative row (MIN id) is already matched
        $matchedSet      = array_flip($matchedSettlementIds);
        $unmatchedCycles = collect($allCycles)->filter(
            fn($c) => !isset($matchedSet[$c->min_id])
        )->values();

        $rows = collect($unmatchedCycles)->map(fn($c) => [
            'settlement_id'    => $c->cycle_id,
            'deposit_date'     => $c->deposit_date,
            'deposited_amount' => (float) ($c->deposited_amount ?? 0),
            'currency'         => $c->currency ?? 'INR',
            'days_since_deposit' => Carbon::parse($c->deposit_date)->diffInDays(now()),
            'action'           => Carbon::parse($c->deposit_date)->diffInDays(now()) <= 5
                ? 'Bank credit may be in transit'
                : 'Check bank statement for this date range',
        ])->values()->toArray();

        return [
            'count'       => count($rows),
            'total_value' => round(collect($rows)->sum('deposited_amount'), 2),
            'rows'        => $rows,
        ];
    }

    private function buildRefundImpact(ReconciliationRun $run, string $from, string $to): array
    {
        $refunds = Settlement::where('workspace_id', $run->workspace_id)
            ->where('transaction_type', 'ILIKE', 'Refund%')
            ->whereBetween('posted_date', [substr($from, 0, 10), substr($to, 0, 10)])
            ->select('order_id', 'posted_date', 'amount', 'settlement_id', 'amount_description')
            ->orderBy('posted_date')
            ->get();

        $rows = $refunds->map(fn($r) => [
            'order_id'          => $r->order_id,
            'refund_date'       => $r->posted_date,
            'refunded_amount'   => round(abs((float) $r->amount), 2),
            'amount_description'=> $r->amount_description,
            'settlement_id'     => $r->settlement_id,
        ])->toArray();

        return [
            'total_refunds'    => count($rows),
            'total_refund_value'=> round(collect($rows)->sum('refunded_amount'), 2),
            'rows'             => $rows,
        ];
    }

    private function buildReturnImpact(ReconciliationRun $run, string $from, string $to): array
    {
        $returns = Order::where('workspace_id', $run->workspace_id)
            ->whereBetween('purchase_date', [$from, $to])
            ->whereIn('order_status', ['Cancelled', 'Returned'])
            ->select('amazon_order_id', 'purchase_date', 'order_status', 'sku', 'item_price')
            ->orderBy('purchase_date')
            ->get();

        $rows = $returns->map(fn($o) => [
            'amazon_order_id' => $o->amazon_order_id,
            'purchase_date'   => $o->purchase_date,
            'order_status'    => $o->order_status,
            'sku'             => $o->sku,
            'item_price'      => (float) $o->item_price,
        ])->toArray();

        return [
            'total_returns'   => count($rows),
            'total_value'     => round(collect($rows)->sum('item_price'), 2),
            'rows'            => $rows,
        ];
    }

    private function buildGstMismatch(ReconciliationRun $run, string $from, string $to): array
    {
        // Compare orders.item_tax vs gst_transactions sum for same order_id
        $mismatches = DB::select("
            SELECT
                o.amazon_order_id,
                o.purchase_date::date AS order_date,
                CAST(o.item_tax AS numeric(12,2)) AS order_tax,
                COALESCE(g.gst_total, 0) AS reported_tax,
                CAST(ABS(CAST(o.item_tax AS numeric) - COALESCE(g.gst_total, 0)) AS numeric(12,2)) AS mismatch_amount
            FROM orders o
            LEFT JOIN (
                SELECT order_id,
                       SUM(COALESCE(igst_amount,0) + COALESCE(cgst_amount,0) + COALESCE(sgst_amount,0)) AS gst_total
                FROM gst_transactions
                WHERE workspace_id = :workspace_id
                GROUP BY order_id
            ) g ON g.order_id = o.amazon_order_id
            WHERE o.workspace_id = :workspace_id2
              AND o.purchase_date >= :from_date::timestamptz
              AND o.purchase_date <= :to_date::timestamptz
              AND o.item_tax > 0
              AND ABS(CAST(o.item_tax AS numeric) - COALESCE(g.gst_total, 0)) > :threshold
            ORDER BY mismatch_amount DESC
            LIMIT 500
        ", [
            'workspace_id'  => $run->workspace_id,
            'workspace_id2' => $run->workspace_id,
            'from_date'     => $from,
            'to_date'       => $to,
            'threshold'     => self::GST_MISMATCH_THRESHOLD,
        ]);

        $rows = collect($mismatches)->map(fn($r) => [
            'amazon_order_id' => $r->amazon_order_id,
            'order_date'      => $r->order_date,
            'order_tax'       => (float) $r->order_tax,
            'reported_tax'    => (float) $r->reported_tax,
            'mismatch_amount' => (float) $r->mismatch_amount,
        ])->toArray();

        return [
            'count'              => count($rows),
            'total_mismatch'     => round(collect($rows)->sum('mismatch_amount'), 2),
            'rows'               => $rows,
        ];
    }

    private function buildSummary(ReconciliationRun $run, array $reports, string $from, string $to): array
    {
        $totalOrders = Order::where('workspace_id', $run->workspace_id)
            ->whereBetween('purchase_date', [$from, $to])
            ->count();

        $orderRevenue = Order::where('workspace_id', $run->workspace_id)
            ->whereBetween('purchase_date', [$from, $to])
            ->sum('item_price');

        $matchedOrders = ReconciliationMatch::where('reconciliation_run_id', $run->id)
            ->whereNotNull('order_id')
            ->whereIn('match_type', ['exact', 'fuzzy_refund'])
            ->distinct('order_id')
            ->count('order_id');

        $totalSettled = Settlement::where('workspace_id', $run->workspace_id)
            ->whereBetween('deposit_date', [
                $run->period_start->toDateString(),
                $run->period_end->toDateString(),
            ])
            ->where('deposited_amount', '>', 0)
            ->selectRaw('MAX(deposited_amount) as max_deposit')
            ->groupBy('settlement_id')
            ->get()
            ->sum('max_deposit');

        $bankCredits = BankTransaction::where('workspace_id', $run->workspace_id)
            ->whereBetween('transaction_date', [
                $run->period_start->toDateString(),
                $run->period_end->toDateString(),
            ])
            ->where('description', 'ILIKE', '%amazon%')
            ->sum('credit_amount');

        $matchRate = $totalOrders > 0
            ? round($matchedOrders / $totalOrders * 100, 1)
            : 0;

        return [
            'period'               => [
                'start' => $run->period_start->toDateString(),
                'end'   => $run->period_end->toDateString(),
            ],
            'total_orders'         => $totalOrders,
            'matched_orders'       => $matchedOrders,
            'unmatched_orders'     => $totalOrders - $matchedOrders,
            'match_rate_pct'       => $matchRate,
            'total_order_value'    => round((float) $orderRevenue, 2),
            'total_settled'        => round((float) $totalSettled, 2),
            'total_bank_credits'   => round((float) $bankCredits, 2),
            'missing_settlements'  => $reports['missing_settlements']['count'] ?? 0,
            'missing_credits'      => $reports['missing_credits']['count'] ?? 0,
            'refund_count'         => $reports['refund_impact']['total_refunds'] ?? 0,
            'refund_value'         => $reports['refund_impact']['total_refund_value'] ?? 0,
            'return_count'         => $reports['return_impact']['total_returns'] ?? 0,
            'gst_mismatches'       => $reports['gst_mismatch']['count'] ?? 0,
        ];
    }
}
