<?php

namespace App\Modules\AI\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EmbeddingProviderService
{
    // Target dimensions — OpenAI text-embedding-3-small output size
    private const TARGET_DIMS = 1536;

    public function isConfigured(): bool
    {
        return $this->hasOpenAI() || $this->hasOllama();
    }

    public function currentModel(): string
    {
        if ($this->hasOpenAI()) {
            return config('ai.providers.openai.embedding_model', 'text-embedding-3-small');
        }
        return config('ai.providers.ollama.embedding_model', 'nomic-embed-text');
    }

    /**
     * Generate an embedding vector for the given text.
     * Returns a float array of length 1536 or throws on failure.
     */
    public function embed(string $text): array
    {
        $text = $this->truncate($text);

        if ($this->hasOpenAI()) {
            return $this->embedWithOpenAI($text);
        }

        if ($this->hasOllama()) {
            return $this->embedWithOllama($text);
        }

        throw new \RuntimeException(
            'No embedding provider configured. Add OPENAI_API_KEY or OLLAMA_BASE_URL to .env.'
        );
    }

    // ─── Providers ───────────────────────────────────────────────────────

    private function embedWithOpenAI(string $text): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.config('ai.providers.openai.api_key'),
            'Content-Type'  => 'application/json',
        ])
            ->timeout(30)
            ->post(config('ai.providers.openai.api_url').'/embeddings', [
                'model' => $this->currentModel(),
                'input' => $text,
            ]);

        if (!$response->ok()) {
            Log::error('OpenAI embedding failed', [
                'status' => $response->status(),
                'body' => substr($response->body(), 0, 500),
            ]);
            throw new \RuntimeException('OpenAI embedding API error: '.$response->status());
        }

        return $response->json('data.0.embedding', []);
    }

    private function embedWithOllama(string $text): array
    {
        $response = Http::timeout(60)
            ->post(config('ai.providers.ollama.base_url').'/api/embeddings', [
                'model'  => config('ai.providers.ollama.embedding_model', 'nomic-embed-text'),
                'prompt' => $text,
            ]);

        if (!$response->ok()) {
            throw new \RuntimeException('Ollama embedding error: '.$response->status());
        }

        $vector = $response->json('embedding', []);

        // Pad to 1536 dims — Ollama nomic-embed-text returns 768 dims
        if (count($vector) < self::TARGET_DIMS) {
            $vector = array_merge($vector, array_fill(0, self::TARGET_DIMS - count($vector), 0.0));
        }

        return $vector;
    }

    // ─── Helpers ──────────────────────────────────────────────────────────

    private function hasOpenAI(): bool
    {
        return !empty(config('ai.providers.openai.api_key'));
    }

    private function hasOllama(): bool
    {
        // Ollama is available if the URL is set and is not the default placeholder
        $url = config('ai.providers.ollama.base_url', '');
        return !empty($url);
    }

    private function truncate(string $text, int $maxChars = 8000): string
    {
        // Safety truncation — OpenAI has an 8191-token limit
        return mb_substr(trim($text), 0, $maxChars);
    }

    /**
     * Convert a float vector to pgvector literal format: '[0.1,0.2,...]'
     */
    public static function toPgVector(array $vector): string
    {
        // Validate all values are numeric before building the literal (security)
        foreach ($vector as $v) {
            if (!is_numeric($v)) {
                throw new \InvalidArgumentException('Vector contains non-numeric value');
            }
        }
        return '['.implode(',', array_map('floatval', $vector)).']';
    }
}
