<?php

namespace App\Modules\Reports\Services\Builders;

use App\Modules\Reconciliation\Models\ReconciliationReport;
use App\Modules\Reports\Models\Report;

class MissingCreditsBuilder extends BaseBuilder
{
    public function build(Report $report): string
    {
        $runId = $report->parameters['reconciliation_run_id']
            ?? throw new \InvalidArgumentException('reconciliation_run_id required');

        $recon = ReconciliationReport::where('reconciliation_run_id', $runId)
            ->where('report_type', 'missing_credits')
            ->firstOrFail();

        $rows    = $recon->report_data['rows'] ?? [];
        $headers = ['Settlement ID', 'Deposit Date', 'Expected Amount (₹)', 'Days Since Deposit', 'Action'];

        $formatted = array_map(fn($r) => [
            $r['settlement_id']      ?? '',
            $r['deposit_date']       ?? '',
            number_format((float)($r['deposited_amount'] ?? 0), 2),
            $r['days_since_deposit'] ?? '',
            $r['action']             ?? '',
        ], $rows);

        return $this->buildCsv($headers, $formatted, $report);
    }
}
