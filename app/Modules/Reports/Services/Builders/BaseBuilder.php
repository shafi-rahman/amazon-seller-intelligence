<?php

namespace App\Modules\Reports\Services\Builders;

use App\Modules\Reports\Models\Report;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;

abstract class BaseBuilder
{
    abstract public function build(Report $report): string;

    // ─── CSV generation ───────────────────────────────────────────────────

    protected function buildCsv(array $headers, array $rows, Report $report): string
    {
        $output = fopen('php://temp', 'r+');
        fputcsv($output, $headers, separator: ',', enclosure: '"', escape: '\\');
        foreach ($rows as $row) {
            fputcsv($output, array_values($row), separator: ',', enclosure: '"', escape: '\\');
        }
        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        $path = $this->storagePath($report, 'csv');
        Storage::disk('s3')->put($path, $csv);
        return $path;
    }

    // ─── PDF generation ───────────────────────────────────────────────────

    protected function buildPdf(string $view, array $data, Report $report): string
    {
        $html = View::make($view, $data)->render();
        $path = $this->storagePath($report, 'pdf');

        if (class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html);
            $pdf->setPaper('A4', 'portrait');
            Storage::disk('s3')->put($path, $pdf->output());
        } else {
            // Fallback: store as HTML (renders in browser identically)
            $htmlPath = str_replace('.pdf', '.html', $path);
            Storage::disk('s3')->put($htmlPath, $html);
            return $htmlPath;
        }

        return $path;
    }

    protected function storagePath(Report $report, string $ext): string
    {
        $bucket = 'asip-reports';
        return "{$bucket}/{$report->workspace_id}/{$report->type}_{$report->id}.{$ext}";
    }
}
