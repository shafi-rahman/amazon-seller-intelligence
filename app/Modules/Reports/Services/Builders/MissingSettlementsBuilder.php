<?php

namespace App\Modules\Reports\Services\Builders;

use App\Modules\Reconciliation\Models\ReconciliationReport;
use App\Modules\Reports\Models\Report;

class MissingSettlementsBuilder extends BaseBuilder
{
    public function build(Report $report): string
    {
        $runId = $report->parameters['reconciliation_run_id']
            ?? throw new \InvalidArgumentException('reconciliation_run_id required');

        $recon = ReconciliationReport::where('reconciliation_run_id', $runId)
            ->where('report_type', 'missing_settlements')
            ->firstOrFail();

        $rows    = $recon->report_data['rows'] ?? [];
        $headers = ['Order ID', 'Purchase Date', 'Status', 'SKU', 'Price (₹)', 'Days Since Order', 'Reason'];

        $formatted = array_map(fn($r) => [
            $r['amazon_order_id'] ?? '',
            $r['purchase_date']   ?? '',
            $r['order_status']    ?? '',
            $r['sku']             ?? '',
            number_format((float)($r['item_price'] ?? 0), 2),
            $r['days_since_order']?? '',
            str_replace('_', ' ', $r['reason'] ?? ''),
        ], $rows);

        return $this->buildCsv($headers, $formatted, $report);
    }
}
