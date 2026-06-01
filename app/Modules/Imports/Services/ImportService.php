<?php

namespace App\Modules\Imports\Services;

use App\Modules\Imports\Jobs\ProcessImportJob;
use App\Modules\Imports\Models\ImportBatch;
use App\Modules\Workspace\Models\Workspace;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use League\Csv\Reader;

class ImportService
{
    private const ALLOWED_TYPES = [
        'orders', 'settlements', 'bank_statement', 'gst_report',
        'products', 'competitors_csv',
    ];

    public function __construct(private readonly ColumnDetector $detector) {}

    public function upload(Workspace $workspace, int $userId, string $type, UploadedFile $file): ImportBatch
    {
        $path = "imports/{$workspace->id}/" . uniqid() . '_' . $file->getClientOriginalName();

        Storage::disk('s3')->put($path, $file->get());

        $batch = ImportBatch::create([
            'workspace_id'      => $workspace->id,
            'user_id'           => $userId,
            'type'              => $type,
            'original_filename' => $file->getClientOriginalName(),
            'storage_path'      => $path,
            'file_size_bytes'   => $file->getSize(),
            'status'            => 'detecting',
        ]);

        // Detect columns synchronously (reads only the first 10 rows — fast)
        $detection = $this->detectColumns($batch, $file, $type);

        $batch->update([
            'status'       => 'awaiting_mapping',
            'total_rows'   => $detection['total_rows'],
            'meta'         => $detection['meta'],
            'column_mapping' => $detection['suggested_mapping'],
        ]);

        return $batch->fresh();
    }

    public function uploadHtml(Workspace $workspace, int $userId, string $html, ?int $productId, ?string $hintAsin): ImportBatch
    {
        $batch = ImportBatch::create([
            'workspace_id'    => $workspace->id,
            'user_id'         => $userId,
            'type'            => 'competitors_html',
            'original_filename'=> 'html_paste_' . now()->format('Ymd_His') . '.html',
            'status'          => 'pending',
            'total_rows'      => 1,
            'meta'            => [
                'html_content' => $html,
                'product_id'   => $productId,
                'hint_asin'    => $hintAsin,
            ],
        ]);

        // Dispatch immediately — no mapping step needed for HTML
        ProcessImportJob::dispatch($batch->id)->onQueue('imports');

        return $batch->fresh();
    }

    public function confirmMapping(ImportBatch $batch, array $mapping): ImportBatch
    {
        $batch->update([
            'status'         => 'pending',
            'column_mapping' => $mapping,
        ]);

        ProcessImportJob::dispatch($batch->id)->onQueue('imports');

        return $batch->fresh();
    }

    private function detectColumns(ImportBatch $batch, UploadedFile $file, string $type): array
    {
        if ($type === 'settlements') {
            return $this->detectSettlementColumns($file);
        }

        $reader = Reader::createFromString($file->get());
        $reader->setHeaderOffset(0);

        // Detect delimiter (try tab first for Amazon reports)
        $sample = substr($file->get(), 0, 1024);
        $delimiter = substr_count($sample, "\t") > substr_count($sample, ",") ? "\t" : ",";
        $reader->setDelimiter($delimiter);

        $headers = $reader->getHeader();
        $suggested = $type === 'bank_statement'
            ? $this->detector->suggestBankColumns($headers)
            : $this->detector->suggest($type, $headers);

        // Count rows quickly
        $totalRows = iterator_count($reader->getRecords());

        return [
            'total_rows'        => $totalRows,
            'suggested_mapping' => $suggested,
            'meta'              => [
                'detected_columns' => $headers,
                'delimiter'        => $delimiter,
                'row_sample'       => $this->sampleRows($reader, 3),
            ],
        ];
    }

    private function detectSettlementColumns(UploadedFile $file): array
    {
        $lines   = explode("\n", $file->get());
        $headers = [];
        $total   = 0;

        foreach ($lines as $i => $line) {
            $lower = strtolower($line);
            if (str_contains($lower, 'settlement-id') && str_contains($lower, 'transaction-type')) {
                $headers = str_getcsv($line, "\t");
                $total   = count($lines) - $i - 1;
                break;
            }
        }

        $suggested = $this->detector->suggest('settlements', $headers);

        return [
            'total_rows'        => max(0, $total),
            'suggested_mapping' => $suggested,
            'meta'              => [
                'detected_columns' => $headers,
                'delimiter'        => "\t",
                'format'           => 'amazon_settlement',
            ],
        ];
    }

    private function sampleRows(Reader $reader, int $n): array
    {
        $rows = [];
        foreach ($reader->getRecords() as $record) {
            $rows[] = $record;
            if (count($rows) >= $n) {
                break;
            }
        }
        return $rows;
    }
}
