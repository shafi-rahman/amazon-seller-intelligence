<?php

namespace App\Modules\Reports\Services\Builders;

use App\Modules\Reconciliation\Models\ReconciliationReport;
use App\Modules\Reconciliation\Models\ReconciliationRun;
use App\Modules\Reports\Models\Report;

class ReconciliationSummaryBuilder extends BaseBuilder
{
    public function build(Report $report): string
    {
        $runId = $report->parameters['reconciliation_run_id']
            ?? throw new \InvalidArgumentException('reconciliation_run_id required');

        $run       = ReconciliationRun::findOrFail($runId);
        $summary   = ReconciliationReport::where('reconciliation_run_id', $runId)
            ->where('report_type', 'summary')
            ->first();

        $data = array_merge($summary?->report_data ?? [], [
            'run'       => $run,
            'generated' => now()->format('d M Y H:i'),
        ]);

        if ($report->file_format === 'csv') {
            $rows = collect($data)->filter(fn($v) => !is_array($v))->map(fn($v, $k) => [
                'metric' => str_replace('_', ' ', ucwords($k)),
                'value'  => $v,
            ])->values()->toArray();

            return $this->buildCsv(['Metric', 'Value'], $rows, $report);
        }

        return $this->buildPdf('reports.reconciliation_pdf', [
            'report' => (object)['report_type' => 'summary', 'reconciliation_run_id' => $runId],
            'data'   => $data,
        ], $report);
    }
}
