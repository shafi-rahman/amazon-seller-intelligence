<?php

namespace App\Modules\Imports\Jobs;

use App\Modules\Imports\Events\ImportCompleted;
use App\Modules\Imports\Models\ImportBatch;
use App\Modules\Imports\Parsers\BankStatementParser;
use App\Modules\Imports\Parsers\CompetitorsCsvParser;
use App\Modules\Imports\Parsers\CompetitorsHtmlParser;
use App\Modules\Imports\Parsers\GstReportParser;
use App\Modules\Imports\Parsers\OrdersParser;
use App\Modules\Imports\Parsers\ProductsParser;
use App\Modules\Imports\Parsers\SettlementsParser;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessImportJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;
    public int $tries   = 3;

    public function backoff(): array
    {
        return [30, 60, 120]; // 30s, 1m, 2m between retries
    }

    public function __construct(private readonly int $batchId) {}

    public function handle(): void
    {
        $batch = ImportBatch::findOrFail($this->batchId);

        if (!in_array($batch->status, ['awaiting_mapping', 'pending'])) {
            return;
        }

        $batch->update(['status' => 'processing', 'started_at' => now()]);

        try {
            $this->runParser($batch);
            $batch->markCompleted();
            ImportCompleted::dispatch($batch->fresh());
        } catch (\Throwable $e) {
            $batch->markFailed($e->getMessage());
            throw $e; // Let Horizon record the failure
        }
    }

    private function runParser(ImportBatch $batch): void
    {
        $onProgress = function (int $ok, int $fail, int $offset) use ($batch) {
            $batch->update([
                'processed_rows' => $offset,
                'failed_rows'    => $batch->failed_rows + $fail,
            ]);
        };

        match ($batch->type) {
            'orders'           => (new OrdersParser())->process($batch, $onProgress),
            'settlements'      => (new SettlementsParser())->process($batch, $onProgress),
            'bank_statement'   => (new BankStatementParser())->process($batch, $onProgress),
            'gst_report'       => (new GstReportParser())->process($batch, $onProgress),
            'products'         => (new ProductsParser())->process($batch, $onProgress),
            'competitors_csv'  => (new CompetitorsCsvParser())->process($batch, $onProgress),
            'competitors_html' => $this->runHtmlParser($batch),
            default            => throw new \InvalidArgumentException("Unknown import type: {$batch->type}"),
        };
    }

    private function runHtmlParser(ImportBatch $batch): void
    {
        $html      = $batch->meta['html_content'] ?? '';
        $productId = $batch->meta['product_id'] ?? null;

        if (empty($html)) {
            throw new \InvalidArgumentException('No HTML content found in batch meta');
        }

        $parser     = new CompetitorsHtmlParser();
        $parsed     = $parser->parse($html, $batch);
        $competitor = $parser->store($parsed, $batch, $productId);

        $batch->update([
            'total_rows'     => 1,
            'processed_rows' => 1,
            'meta'           => array_merge($batch->meta, [
                'parsed_asin'       => $parsed['asin'],
                'confidence_scores' => $parsed['parse_confidence'],
                'competitor_id'     => $competitor->id,
            ]),
        ]);
    }
}
