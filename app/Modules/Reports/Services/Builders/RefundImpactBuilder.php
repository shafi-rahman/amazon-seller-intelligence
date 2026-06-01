<?php

namespace App\Modules\Reports\Services\Builders;

use App\Modules\Reconciliation\Models\ReconciliationReport;
use App\Modules\Reports\Models\Report;

class RefundImpactBuilder extends BaseBuilder
{
    public function build(Report $report): string
    {
        $runId = $report->parameters['reconciliation_run_id']
            ?? throw new \InvalidArgumentException('reconciliation_run_id required');

        $recon = ReconciliationReport::where('reconciliation_run_id', $runId)
            ->where('report_type', 'refund_impact')
            ->firstOrFail();

        $rows    = $recon->report_data['rows'] ?? [];
        $headers = ['Order ID', 'Refund Date', 'Refunded Amount (₹)', 'Description', 'Settlement ID'];

        $formatted = array_map(fn($r) => [
            $r['order_id']          ?? '',
            $r['refund_date']       ?? '',
            number_format((float)($r['refunded_amount'] ?? 0), 2),
            $r['amount_description'] ?? '',
            $r['settlement_id']     ?? '',
        ], $rows);

        return $this->buildCsv($headers, $formatted, $report);
    }
}
