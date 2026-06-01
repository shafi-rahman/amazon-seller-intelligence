<?php

namespace App\Modules\Reports\Services\Builders;

use App\Modules\Reconciliation\Models\ReconciliationReport;
use App\Modules\Reports\Models\Report;

class GstMismatchBuilder extends BaseBuilder
{
    public function build(Report $report): string
    {
        $runId = $report->parameters['reconciliation_run_id']
            ?? throw new \InvalidArgumentException('reconciliation_run_id required');

        $recon = ReconciliationReport::where('reconciliation_run_id', $runId)
            ->where('report_type', 'gst_mismatch')
            ->firstOrFail();

        $rows    = $recon->report_data['rows'] ?? [];
        $headers = ['Order ID', 'Order Date', 'Order Tax (₹)', 'GST Reported (₹)', 'Difference (₹)'];

        $formatted = array_map(fn($r) => [
            $r['amazon_order_id']   ?? '',
            $r['order_date']        ?? '',
            number_format((float)($r['order_tax']      ?? 0), 2),
            number_format((float)($r['reported_tax']   ?? 0), 2),
            number_format((float)($r['mismatch_amount']?? 0), 2),
        ], $rows);

        return $this->buildCsv($headers, $formatted, $report);
    }
}
