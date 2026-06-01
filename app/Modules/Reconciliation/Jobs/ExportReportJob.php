<?php

namespace App\Modules\Reconciliation\Jobs;

use App\Modules\Reconciliation\Models\ReconciliationReport;
use App\Modules\Reconciliation\Services\ReportExporter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ExportReportJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 120;

    public function __construct(
        private readonly int    $reportId,
        private readonly string $format,  // csv | pdf
    ) {}

    public function handle(ReportExporter $exporter): void
    {
        $report = ReconciliationReport::findOrFail($this->reportId);

        $path = match ($this->format) {
            'pdf'   => $exporter->exportPdf($report),
            default => $exporter->exportCsv($report),
        };

        $report->update(['export_path' => $path]);
    }
}
