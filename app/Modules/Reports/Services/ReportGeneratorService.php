<?php

namespace App\Modules\Reports\Services;

use App\Modules\Reports\Jobs\GenerateReportJob;
use App\Modules\Reports\Models\Report;
use App\Modules\Reports\Services\Builders\CompetitorBenchmarkBuilder;
use App\Modules\Reports\Services\Builders\GstMismatchBuilder;
use App\Modules\Reports\Services\Builders\KeywordGapBuilder;
use App\Modules\Reports\Services\Builders\ListingAnalysisBuilder;
use App\Modules\Reports\Services\Builders\MissingCreditsBuilder;
use App\Modules\Reports\Services\Builders\MissingSettlementsBuilder;
use App\Modules\Reports\Services\Builders\ReconciliationSummaryBuilder;
use App\Modules\Reports\Services\Builders\RefundImpactBuilder;
use Illuminate\Support\Facades\Storage;

class ReportGeneratorService
{
    // Report type → [title, supported formats]
    public const REPORT_TYPES = [
        'reconciliation_summary' => ['title' => 'Reconciliation Summary',     'formats' => ['pdf', 'csv']],
        'missing_settlements'    => ['title' => 'Missing Settlements',        'formats' => ['csv']],
        'missing_credits'        => ['title' => 'Missing Bank Credits',       'formats' => ['csv']],
        'refund_impact'          => ['title' => 'Refund Impact Analysis',     'formats' => ['csv']],
        'gst_mismatch'           => ['title' => 'GST Mismatch Report',        'formats' => ['csv']],
        'listing_analysis'       => ['title' => 'Listing Analysis',           'formats' => ['pdf']],
        'keyword_gap'            => ['title' => 'Keyword Gap Analysis',       'formats' => ['csv']],
        'competitor_benchmark'   => ['title' => 'Competitor Benchmark',       'formats' => ['pdf', 'csv']],
    ];

    /**
     * Queue a report for generation. Returns the Report record immediately.
     */
    public function request(
        int    $workspaceId,
        int    $userId,
        string $type,
        string $format,
        array  $parameters = [],
    ): Report {
        abort_unless(isset(self::REPORT_TYPES[$type]), 422, "Unknown report type: {$type}");

        $report = Report::create([
            'workspace_id' => $workspaceId,
            'user_id'      => $userId,
            'type'         => $type,
            'title'        => self::REPORT_TYPES[$type]['title'],
            'parameters'   => $parameters,
            'status'       => 'pending',
            'file_format'  => $format,
        ]);

        GenerateReportJob::dispatch($report->id)->onQueue('reports');

        return $report;
    }

    /**
     * Build and store the report. Called from GenerateReportJob.
     */
    public function generate(Report $report): void
    {
        $report->update(['status' => 'generating']);

        try {
            $builder = $this->resolveBuilder($report->type);
            $path    = $builder->build($report);
            $report->markCompleted($path, $report->file_format);
        } catch (\Throwable $e) {
            $report->markFailed($e->getMessage());
            throw $e;
        }
    }

    public function presignedUrl(Report $report): string
    {
        abort_if(empty($report->file_path), 404, 'Report file not ready yet.');
        return Storage::disk('s3')->temporaryUrl($report->file_path, now()->addMinutes(60));
    }

    private function resolveBuilder(string $type): mixed
    {
        return match ($type) {
            'reconciliation_summary' => app(ReconciliationSummaryBuilder::class),
            'missing_settlements'    => app(MissingSettlementsBuilder::class),
            'missing_credits'        => app(MissingCreditsBuilder::class),
            'refund_impact'          => app(RefundImpactBuilder::class),
            'gst_mismatch'           => app(GstMismatchBuilder::class),
            'listing_analysis'       => app(ListingAnalysisBuilder::class),
            'keyword_gap'            => app(KeywordGapBuilder::class),
            'competitor_benchmark'   => app(CompetitorBenchmarkBuilder::class),
            default                  => throw new \InvalidArgumentException("No builder for type: {$type}"),
        };
    }
}
