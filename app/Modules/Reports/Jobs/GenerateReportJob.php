<?php

namespace App\Modules\Reports\Jobs;

use App\Modules\Reports\Models\Report;
use App\Modules\Reports\Services\ReportGeneratorService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateReportJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 300;
    public int $tries   = 2;

    public function __construct(private readonly int $reportId) {}

    public function handle(ReportGeneratorService $service): void
    {
        $report = Report::findOrFail($this->reportId);

        if ($report->status === 'completed') {
            return;
        }

        $service->generate($report);
    }
}
