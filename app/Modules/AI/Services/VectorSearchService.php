<?php

namespace App\Modules\AI\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class VectorSearchService
{
    private const DEFAULT_TOP_K    = 5;
    private const DEFAULT_THRESHOLD = 0.65;

    public function __construct(private readonly EmbeddingProviderService $provider) {}

    /**
     * Search for semantically similar documents.
     * Returns empty array if provider is not configured or query fails.
     *
     * @param string $query      Natural language query
     * @param int    $workspaceId Scope search to this workspace only
     * @param int    $topK       Number of results to return
     * @param float  $threshold  Minimum cosine similarity (0–1)
     * @param string|null $embeddableType Filter by type (e.g. Product::class)
     * @return array Each item: {embeddable_type, embeddable_id, chunk_text, similarity}
     */
    public function search(
        string  $query,
        int     $workspaceId,
        int     $topK      = self::DEFAULT_TOP_K,
        float   $threshold = self::DEFAULT_THRESHOLD,
        ?string $embeddableType = null,
    ): array {
        if (!$this->provider->isConfigured()) {
            Log::info('Vector search skipped: no embedding provider configured');
            return [];
        }

        try {
            $queryVector = $this->provider->embed($query);
            $pgVector    = EmbeddingProviderService::toPgVector($queryVector);

            $typeFilter  = $embeddableType
                ? "AND e.embeddable_type = :type"
                : "";

            $params = [
                'workspace_id' => $workspaceId,
                'vector1'      => $pgVector,
                'vector2'      => $pgVector,
                'threshold'    => $threshold,
                'top_k'        => $topK,
            ];

            if ($embeddableType) {
                $params['type'] = $embeddableType;
            }

            $sql = "
                SELECT
                    e.id,
                    e.embeddable_type,
                    e.embeddable_id,
                    e.chunk_text,
                    e.model,
                    (1 - (e.embedding <=> :vector1::vector)) AS similarity
                FROM embeddings e
                WHERE e.workspace_id = :workspace_id
                  {$typeFilter}
                  AND (1 - (e.embedding <=> :vector2::vector)) > :threshold
                ORDER BY e.embedding <=> :vector1::vector
                LIMIT :top_k
            ";

            return DB::select($sql, $params);
        } catch (\Throwable $e) {
            Log::warning('Vector search error', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Search restricted to a specific embeddable type and return model instances.
     */
    public function searchProducts(string $query, int $workspaceId, int $topK = 5): array
    {
        return $this->search($query, $workspaceId, $topK, 0.60,
            \App\Modules\Products\Models\Product::class
        );
    }

    public function searchCompetitors(string $query, int $workspaceId, int $topK = 5): array
    {
        return $this->search($query, $workspaceId, $topK, 0.60,
            \App\Modules\Competitors\Models\Competitor::class
        );
    }

    /**
     * Format search results as RAG context string for AI prompts.
     */
    public function formatAsContext(array $results): string
    {
        if (empty($results)) {
            return '';
        }

        return collect($results)->map(function ($r, $i) {
            $type  = class_basename($r->embeddable_type);
            $score = round($r->similarity * 100);
            return "[Source {$i}: {$type} #{$r->embeddable_id} (relevance {$score}%)]\n"
                  .trim($r->chunk_text);
        })->implode("\n\n---\n\n");
    }

    public function isAvailable(): bool
    {
        return $this->provider->isConfigured();
    }
}
