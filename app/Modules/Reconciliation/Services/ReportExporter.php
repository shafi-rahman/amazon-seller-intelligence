<?php

namespace App\Modules\Reconciliation\Services;

use App\Modules\Reconciliation\Models\ReconciliationReport;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;

class ReportExporter
{
    public function exportCsv(ReconciliationReport $report): string
    {
        $data    = $report->report_data ?? [];
        $rows    = $data['rows'] ?? [];
        $path    = "asip-reports/reconciliation/{$report->workspace_id}/{$report->reconciliation_run_id}/{$report->report_type}.csv";

        $output  = fopen('php://temp', 'r+');

        // Headers from first row keys
        if (!empty($rows)) {
            fputcsv($output, array_keys($rows[0]));
            foreach ($rows as $row) {
                fputcsv($output, array_values($row));
            }
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        Storage::disk('s3')->put($path, $csv);

        return $path;
    }

    public function exportPdf(ReconciliationReport $report): string
    {
        $path = "asip-reports/reconciliation/{$report->workspace_id}/{$report->reconciliation_run_id}/{$report->report_type}.pdf";

        $html = View::make('reports.reconciliation_pdf', [
            'report' => $report,
            'data'   => $report->report_data ?? [],
        ])->render();

        // Use DomPDF if installed, else write HTML as fallback
        if (class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html);
            Storage::disk('s3')->put($path, $pdf->output());
        } else {
            // Fallback: store as HTML (acceptable for dev without DomPDF)
            $htmlPath = str_replace('.pdf', '.html', $path);
            Storage::disk('s3')->put($htmlPath, $html);
            $path = $htmlPath;
        }

        return $path;
    }

    public function presignedUrl(string $path): string
    {
        return Storage::disk('s3')->temporaryUrl($path, now()->addMinutes(60));
    }
}
