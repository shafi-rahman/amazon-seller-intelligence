<?php

namespace App\Modules\Finance\Services;

use App\Modules\Finance\Models\BankTransaction;
use App\Modules\Finance\Models\GstTransaction;
use App\Modules\Finance\Models\Order;
use App\Modules\Finance\Models\Settlement;
use App\Modules\Imports\Models\ImportBatch;
use Illuminate\Support\Facades\DB;

class FinanceDashboardService
{
    public function summary(int $workspaceId, string $dateFrom, string $dateTo): array
    {
        return [
            'period'           => ['start' => $dateFrom, 'end' => $dateTo],
            'data_availability'=> $this->dataAvailability($workspaceId),
            'orders_summary'   => $this->ordersSummary($workspaceId, $dateFrom, $dateTo),
            'settlements_summary'=> $this->settlementsSummary($workspaceId, $dateFrom, $dateTo),
            'bank_summary'     => $this->bankSummary($workspaceId, $dateFrom, $dateTo),
            'gst_summary'      => $this->gstSummary($workspaceId, $dateFrom, $dateTo),
            'top_products'     => $this->topProducts($workspaceId, $dateFrom, $dateTo, 5),
        ];
    }

    private function dataAvailability(int $workspaceId): array
    {
        $types = ['orders', 'settlements', 'bank_statement', 'gst_report'];
        $result = [];
        foreach ($types as $type) {
            $result[$type] = ImportBatch::where('workspace_id', $workspaceId)
                ->where('type', $type)
                ->where('status', 'completed')
                ->exists();
        }
        return $result;
    }

    private function ordersSummary(int $workspaceId, string $from, string $to): array
    {
        $base = Order::where('workspace_id', $workspaceId)
            ->whereBetween('purchase_date', [$from.' 00:00:00', $to.' 23:59:59']);

        $agg = $base->selectRaw("
            COUNT(*) as total_orders,
            COALESCE(SUM(item_price), 0) as total_revenue,
            COALESCE(SUM(item_tax), 0) as total_tax,
            COALESCE(SUM(quantity), 0) as total_units,
            COALESCE(SUM(item_price + item_tax), 0) as gross_revenue
        ")->first();

        $byStatus = $base->clone()->selectRaw('order_status, COUNT(*) as cnt')
            ->groupBy('order_status')
            ->pluck('cnt', 'order_status')
            ->toArray();

        $byFulfillment = $base->clone()->selectRaw('fulfillment_channel, COUNT(*) as cnt')
            ->whereNotNull('fulfillment_channel')
            ->groupBy('fulfillment_channel')
            ->pluck('cnt', 'fulfillment_channel')
            ->toArray();

        return [
            'total_orders'   => (int) $agg->total_orders,
            'total_revenue'  => round((float) $agg->total_revenue, 2),
            'total_tax'      => round((float) $agg->total_tax, 2),
            'gross_revenue'  => round((float) $agg->gross_revenue, 2),
            'total_units'    => (int) $agg->total_units,
            'by_status'      => $byStatus,
            'by_fulfillment' => $byFulfillment,
        ];
    }

    private function settlementsSummary(int $workspaceId, string $from, string $to): array
    {
        $rows = Settlement::where('workspace_id', $workspaceId)
            ->whereBetween('deposit_date', [$from, $to])
            ->selectRaw("
                COUNT(DISTINCT settlement_id) as settlement_cycles,
                COALESCE(SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END), 0) as gross_settled,
                COALESCE(SUM(CASE WHEN amount < 0 THEN amount ELSE 0 END), 0) as total_deductions,
                COALESCE(SUM(deposited_amount), 0) as total_deposited
            ")
            ->first();

        return [
            'settlement_cycles' => (int) $rows->settlement_cycles,
            'gross_settled'     => round((float) $rows->gross_settled, 2),
            'total_deductions'  => round((float) $rows->total_deductions, 2),
            'total_deposited'   => round((float) $rows->total_deposited, 2),
        ];
    }

    private function bankSummary(int $workspaceId, string $from, string $to): array
    {
        $rows = BankTransaction::where('workspace_id', $workspaceId)
            ->whereBetween('transaction_date', [$from, $to])
            ->selectRaw("
                COUNT(*) as total_transactions,
                COALESCE(SUM(credit_amount), 0) as total_credits,
                COALESCE(SUM(debit_amount), 0) as total_debits,
                COALESCE(SUM(CASE WHEN description ILIKE '%amazon%' THEN credit_amount ELSE 0 END), 0) as amazon_credits
            ")
            ->first();

        return [
            'total_transactions' => (int) $rows->total_transactions,
            'total_credits'      => round((float) $rows->total_credits, 2),
            'total_debits'       => round((float) $rows->total_debits, 2),
            'amazon_credits'     => round((float) $rows->amazon_credits, 2),
        ];
    }

    private function gstSummary(int $workspaceId, string $from, string $to): array
    {
        $rows = GstTransaction::where('workspace_id', $workspaceId)
            ->whereBetween('invoice_date', [$from, $to])
            ->selectRaw("
                COUNT(*) as total_invoices,
                COALESCE(SUM(igst_amount), 0) as total_igst,
                COALESCE(SUM(cgst_amount), 0) as total_cgst,
                COALESCE(SUM(sgst_amount), 0) as total_sgst,
                COALESCE(SUM(taxable_value), 0) as total_taxable_value
            ")
            ->first();

        return [
            'total_invoices'     => (int) $rows->total_invoices,
            'total_igst'         => round((float) $rows->total_igst, 2),
            'total_cgst'         => round((float) $rows->total_cgst, 2),
            'total_sgst'         => round((float) $rows->total_sgst, 2),
            'total_tax'          => round(
                (float) $rows->total_igst + (float) $rows->total_cgst + (float) $rows->total_sgst,
                2
            ),
            'total_taxable_value'=> round((float) $rows->total_taxable_value, 2),
        ];
    }

    private function topProducts(int $workspaceId, string $from, string $to, int $limit): array
    {
        return Order::where('workspace_id', $workspaceId)
            ->where('order_status', 'Shipped')
            ->whereBetween('purchase_date', [$from.' 00:00:00', $to.' 23:59:59'])
            ->whereNotNull('sku')
            ->selectRaw("sku, asin, SUM(quantity) as units_sold, SUM(item_price) as revenue")
            ->groupBy('sku', 'asin')
            ->orderByDesc('revenue')
            ->limit($limit)
            ->get()
            ->map(fn($r) => [
                'sku'      => $r->sku,
                'asin'     => $r->asin,
                'units'    => (int) $r->units_sold,
                'revenue'  => round((float) $r->revenue, 2),
            ])
            ->toArray();
    }
}
