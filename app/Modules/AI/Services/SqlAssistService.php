<?php

namespace App\Modules\AI\Services;

use Illuminate\Support\Facades\DB;

/**
 * Pre-computed SQL query templates that inject structured financial data
 * into AI prompts without going through vector search.
 * Used for "WHERE did my money go?" type questions.
 */
class SqlAssistService
{
    public function getFinancialContext(int $workspaceId, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $from = $dateFrom ?? now()->startOfMonth()->toDateString();
        $to   = $dateTo   ?? now()->toDateString();

        return [
            'period'          => compact('from', 'to'),
            'orders_summary'  => $this->ordersSummary($workspaceId, $from, $to),
            'top_products'    => $this->topProducts($workspaceId, $from, $to),
            'settlements'     => $this->settlementsSummary($workspaceId, $from, $to),
            'recent_reconciliation' => $this->latestReconciliation($workspaceId),
        ];
    }

    public function getListingContext(int $workspaceId, int $productId): array
    {
        return [
            'product'          => $this->productSummary($workspaceId, $productId),
            'listing_score'    => $this->latestListingScore($productId),
            'top_gaps'         => $this->topKeywordGaps($productId),
            'competitor_count' => $this->competitorCount($workspaceId, $productId),
        ];
    }

    // ─── Financial queries ────────────────────────────────────────────────

    private function ordersSummary(int $workspaceId, string $from, string $to): object|null
    {
        return DB::selectOne("
            SELECT
                COUNT(*)                             AS total_orders,
                COALESCE(SUM(item_price), 0)        AS total_revenue,
                COALESCE(SUM(item_tax), 0)          AS total_tax,
                COALESCE(SUM(quantity), 0)          AS total_units,
                COUNT(CASE WHEN order_status = 'Shipped' THEN 1 END)   AS shipped,
                COUNT(CASE WHEN order_status = 'Cancelled' THEN 1 END) AS cancelled
            FROM orders
            WHERE workspace_id = ?
              AND purchase_date >= ?::timestamptz
              AND purchase_date <= ?::timestamptz
        ", [$workspaceId, $from.' 00:00:00', $to.' 23:59:59']);
    }

    private function topProducts(int $workspaceId, string $from, string $to, int $limit = 5): array
    {
        return DB::select("
            SELECT sku, asin,
                   SUM(quantity)   AS units_sold,
                   SUM(item_price) AS revenue
            FROM orders
            WHERE workspace_id = ?
              AND order_status = 'Shipped'
              AND purchase_date >= ?::timestamptz
              AND purchase_date <= ?::timestamptz
              AND sku IS NOT NULL
            GROUP BY sku, asin
            ORDER BY revenue DESC
            LIMIT ?
        ", [$workspaceId, $from.' 00:00:00', $to.' 23:59:59', $limit]);
    }

    private function settlementsSummary(int $workspaceId, string $from, string $to): object|null
    {
        return DB::selectOne("
            SELECT
                COUNT(DISTINCT settlement_id)          AS cycles,
                COALESCE(SUM(deposited_amount) FILTER (WHERE deposited_amount IS NOT NULL AND transaction_type NOT IN ('Transfer') OR transaction_type IS NULL), 0) AS total_settled
            FROM settlements
            WHERE workspace_id = ?
              AND deposit_date BETWEEN ? AND ?
        ", [$workspaceId, $from, $to]);
    }

    private function latestReconciliation(int $workspaceId): object|null
    {
        return DB::selectOne("
            SELECT id, period_start, period_end, status,
                   summary->>'total_orders'     AS total_orders,
                   summary->>'matched_orders'   AS matched_orders,
                   summary->>'unmatched_orders' AS unmatched_orders,
                   summary->>'missing_settlements' AS missing_settlements
            FROM reconciliation_runs
            WHERE workspace_id = ?
              AND status = 'completed'
            ORDER BY created_at DESC
            LIMIT 1
        ", [$workspaceId]);
    }

    // ─── Listing queries ──────────────────────────────────────────────────

    private function productSummary(int $workspaceId, int $productId): object|null
    {
        return DB::selectOne("
            SELECT asin, title, brand, price, rating, review_count, listing_score
            FROM products
            WHERE workspace_id = ? AND id = ?
        ", [$workspaceId, $productId]);
    }

    private function latestListingScore(int $productId): array
    {
        $row = DB::selectOne("
            SELECT analysis_data
            FROM product_analyses
            WHERE product_id = ? AND analysis_type = 'listing_score'
            ORDER BY created_at DESC
            LIMIT 1
        ", [$productId]);

        return $row ? json_decode($row->analysis_data, true) : [];
    }

    private function topKeywordGaps(int $productId, int $limit = 10): array
    {
        return DB::select("
            SELECT keyword, gap_type, MAX(priority_score) AS priority
            FROM keyword_gaps
            WHERE product_id = ?
              AND gap_type = 'missing'
            GROUP BY keyword, gap_type
            ORDER BY priority DESC
            LIMIT ?
        ", [$productId, $limit]);
    }

    private function competitorCount(int $workspaceId, int $productId): int
    {
        return (int) DB::selectOne("
            SELECT COUNT(*) AS cnt FROM competitors
            WHERE workspace_id = ? AND product_id = ?
        ", [$workspaceId, $productId])->cnt;
    }

    /**
     * Format financial context as a compact string for AI prompt injection.
     */
    public function formatFinancialContext(array $context): string
    {
        $lines = [];

        $period = $context['period'];
        $lines[] = "Period: {$period['from']} to {$period['to']}";

        if ($s = $context['orders_summary']) {
            $lines[] = "Orders: {$s->total_orders} total | {$s->shipped} shipped | {$s->cancelled} cancelled";
            $lines[] = "Revenue: ₹".number_format($s->total_revenue, 2)." | Tax: ₹".number_format($s->total_tax, 2);
        }

        if (!empty($context['top_products'])) {
            $lines[] = "Top products by revenue:";
            foreach ($context['top_products'] as $p) {
                $lines[] = "  - {$p->sku} ({$p->asin}): {$p->units_sold} units | ₹".number_format($p->revenue, 2);
            }
        }

        if ($r = $context['recent_reconciliation']) {
            $lines[] = "Last reconciliation ({$r->period_start} to {$r->period_end}):";
            $lines[] = "  Matched {$r->matched_orders}/{$r->total_orders} orders | Missing settlements: {$r->missing_settlements}";
        }

        return implode("\n", $lines);
    }
}
