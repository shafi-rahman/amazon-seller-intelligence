<?php

namespace App\Modules\AI\Jobs;

use App\Modules\AI\Models\Embedding;
use App\Modules\AI\Services\DocumentChunkerService;
use App\Modules\AI\Services\EmbeddingProviderService;
use App\Modules\Competitors\Models\Competitor;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\ProductAnalysis;
use App\Modules\Products\Models\ProductReview;
use App\Modules\Reconciliation\Models\ReconciliationReport;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EmbedDocumentJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 120;
    public int $tries   = 3;

    public function backoff(): array
    {
        return [10, 30, 60]; // fast retries then back off (API rate limits)
    }

    public function __construct(
        private readonly string $embeddableType,
        private readonly int    $embeddableId,
        private readonly int    $workspaceId,
    ) {}

    public function handle(EmbeddingProviderService $provider, DocumentChunkerService $chunker): void
    {
        if (!$provider->isConfigured()) {
            Log::info('EmbedDocumentJob skipped: no embedding provider configured', [
                'type' => $this->embeddableType,
                'id'   => $this->embeddableId,
            ]);
            return;
        }

        $model = ($this->embeddableType)::find($this->embeddableId);
        if (!$model) {
            return;
        }

        $text = $this->extractText($model);
        if (empty(trim($text))) {
            return;
        }

        $chunks = $chunker->chunk($text);
        if (empty($chunks)) {
            return;
        }

        // Delete existing embeddings for this document
        Embedding::where('embeddable_type', $this->embeddableType)
            ->where('embeddable_id', $this->embeddableId)
            ->delete();

        $modelName = $provider->currentModel();

        foreach ($chunks as $index => $chunkText) {
            try {
                $vector   = $provider->embed($chunkText);
                $pgVector = EmbeddingProviderService::toPgVector($vector);

                // Use raw SQL for pgvector insert
                DB::insert(
                    "INSERT INTO embeddings (embeddable_type, embeddable_id, chunk_index, chunk_text, embedding, model, workspace_id, created_at)
                     VALUES (?, ?, ?, ?, ?::vector, ?, ?, NOW())",
                    [
                        $this->embeddableType,
                        $this->embeddableId,
                        $index,
                        $chunkText,
                        $pgVector,
                        $modelName,
                        $this->workspaceId,
                    ]
                );
            } catch (\Throwable $e) {
                Log::warning('Embedding chunk failed', [
                    'type'  => $this->embeddableType,
                    'id'    => $this->embeddableId,
                    'chunk' => $index,
                    'error' => $e->getMessage(),
                ]);
                // Don't rethrow — partial embeddings are acceptable
            }
        }
    }

    private function extractText(mixed $model): string
    {
        return match (true) {
            $model instanceof Product => trim(implode(' ', array_filter([
                $model->title,
                $model->brand,
                $model->bullet_1, $model->bullet_2, $model->bullet_3,
                $model->bullet_4, $model->bullet_5,
                $model->description,
            ]))),

            $model instanceof ProductReview => trim(implode(' ', array_filter([
                $model->title,
                $model->body,
            ]))),

            $model instanceof Competitor => trim(implode(' ', array_filter([
                $model->title,
                $model->brand,
                $model->bullet_1, $model->bullet_2, $model->bullet_3,
                $model->bullet_4, $model->bullet_5,
                $model->description,
            ]))),

            $model instanceof ReconciliationReport => $this->formatReport($model),

            $model instanceof ProductAnalysis => trim(
                $model->analysis_type.' '.json_encode($model->analysis_data)
            ),

            default => '',
        };
    }

    private function formatReport(ReconciliationReport $report): string
    {
        $data  = $report->report_data ?? [];
        $type  = str_replace('_', ' ', $report->report_type);
        $lines = ["Reconciliation report — {$type}"];

        if (isset($data['count'])) {
            $lines[] = "Count: {$data['count']} items";
        }
        if (isset($data['total_value'])) {
            $lines[] = "Total value: ₹{$data['total_value']}";
        }
        if (isset($data['rows']) && is_array($data['rows'])) {
            foreach (array_slice($data['rows'], 0, 20) as $row) {
                $lines[] = implode(' | ', array_map(
                    fn($k, $v) => "{$k}: {$v}",
                    array_keys($row),
                    array_values($row)
                ));
            }
        }

        return implode("\n", $lines);
    }
}
