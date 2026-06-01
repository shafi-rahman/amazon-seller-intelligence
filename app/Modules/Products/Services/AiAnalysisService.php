<?php

namespace App\Modules\Products\Services;

use App\Modules\Products\Models\Product;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiAnalysisService
{
    public function isConfigured(): bool
    {
        return !empty(config('ai.providers.anthropic.api_key'));
    }

    /**
     * Call Claude for structured listing analysis. Returns null if not configured
     * or if the call fails — callers should fall back to rule-based data.
     */
    public function analyzeListingWithClaude(Product $product, array $scoredData): ?array
    {
        if (!$this->isConfigured()) {
            return null;
        }

        $prompt = $this->buildListingPrompt($product, $scoredData);

        try {
            $response = Http::withHeaders([
                'x-api-key'         => config('ai.providers.anthropic.api_key'),
                'anthropic-version' => config('ai.providers.anthropic.version'),
                'content-type'      => 'application/json',
            ])
                ->timeout(60)
                ->post(config('ai.providers.anthropic.api_url'), [
                    'model'      => config('ai.providers.anthropic.model'),
                    'max_tokens' => config('ai.providers.anthropic.max_tokens'),
                    'messages'   => [
                        ['role' => 'user', 'content' => $prompt],
                    ],
                ]);

            if (!$response->ok()) {
                Log::warning('Anthropic API error', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                return null;
            }

            $text   = $response->json('content.0.text', '');
            $parsed = $this->extractJson($text);

            return [
                'analysis_data'     => $parsed,
                'prompt_tokens'     => $response->json('usage.input_tokens', 0),
                'completion_tokens' => $response->json('usage.output_tokens', 0),
            ];
        } catch (\Throwable $e) {
            Log::warning('AI listing analysis failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Generate AI-powered rewrites for title, bullets, and description.
     */
    public function generateRewrite(Product $product, array $missingKeywords): ?array
    {
        if (!$this->isConfigured()) {
            return null;
        }

        $prompt = $this->buildRewritePrompt($product, $missingKeywords);

        try {
            $response = Http::withHeaders([
                'x-api-key'         => config('ai.providers.anthropic.api_key'),
                'anthropic-version' => config('ai.providers.anthropic.version'),
                'content-type'      => 'application/json',
            ])
                ->timeout(90)
                ->post(config('ai.providers.anthropic.api_url'), [
                    'model'      => config('ai.providers.anthropic.model'),
                    'max_tokens' => 4096,
                    'messages'   => [
                        ['role' => 'user', 'content' => $prompt],
                    ],
                ]);

            if (!$response->ok()) {
                return null;
            }

            $text = $response->json('content.0.text', '');
            return $this->extractJson($text);
        } catch (\Throwable $e) {
            Log::warning('AI rewrite generation failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    // ─── Prompts ──────────────────────────────────────────────────────────

    private function buildListingPrompt(Product $product, array $scored): string
    {
        $bullets = array_filter([
            $product->bullet_1, $product->bullet_2, $product->bullet_3,
            $product->bullet_4, $product->bullet_5,
        ]);

        $issues = collect($scored['dimensions'] ?? [])
            ->flatMap(fn($d) => $d['issues'] ?? [])
            ->implode("\n- ");

        return <<<PROMPT
You are an Amazon listing optimization expert. Analyze this product listing and provide structured feedback.

PRODUCT DATA:
ASIN: {$product->asin}
Title: {$product->title}
Brand: {$product->brand}
Category: {$product->category}
Bullets:
{$this->formatBullets($bullets)}
Description (first 500 chars): {$this->truncate($product->description, 500)}
Price: {$product->price} {$product->currency}
Rating: {$product->rating} ({$product->review_count} reviews)

RULE-BASED ISSUES FOUND:
- {$issues}

CURRENT SCORE: {$scored['total']}/100

Respond with ONLY a valid JSON object (no markdown, no explanation outside the JSON):
{
  "primary_keyword": "the most important search term for this product",
  "secondary_keywords": ["list of 5-10 important keywords"],
  "title_issues": ["specific title problems"],
  "bullet_issues": ["specific bullet problems"],
  "description_issues": ["specific description problems"],
  "optimization_suggestions": [
    {
      "field": "title|bullet_1|description|etc",
      "priority": "high|medium|low",
      "issue": "specific problem",
      "suggestion": "actionable fix",
      "rewritten": "optimized version of this field (keep it realistic)"
    }
  ],
  "overall_assessment": "2-3 sentence summary for the seller"
}
PROMPT;
    }

    private function buildRewritePrompt(Product $product, array $missingKeywords): string
    {
        $bullets = array_filter([
            $product->bullet_1, $product->bullet_2, $product->bullet_3,
            $product->bullet_4, $product->bullet_5,
        ]);
        $missingList = implode(', ', array_slice($missingKeywords, 0, 15));

        return <<<PROMPT
You are an Amazon listing copywriter. Rewrite the following listing to maximize search visibility and conversion.
Follow Amazon guidelines: factual claims only, no competitor comparisons, no promotional language.

ORIGINAL LISTING:
Title: {$product->title}
Bullets:
{$this->formatBullets($bullets)}
Description (first 800 chars): {$this->truncate($product->description, 800)}
Category: {$product->category}
Missing keywords to incorporate naturally: {$missingList}

Return ONLY a valid JSON object:
{
  "title": "optimized title under 200 chars",
  "bullet_1": "optimized first bullet",
  "bullet_2": "optimized second bullet",
  "bullet_3": "optimized third bullet",
  "bullet_4": "optimized fourth bullet",
  "bullet_5": "optimized fifth bullet",
  "description": "optimized description (aim for 1500+ chars)"
}
PROMPT;
    }

    private function formatBullets(array $bullets): string
    {
        return collect($bullets)->map(fn($b, $i) => ($i + 1).'. '.$b)->implode("\n");
    }

    private function truncate(?string $text, int $max): string
    {
        if (empty($text)) {
            return '';
        }
        return mb_strlen($text) > $max ? mb_substr($text, 0, $max).'…' : $text;
    }

    private function extractJson(string $text): array
    {
        // Strip any markdown code blocks
        $text = preg_replace('/^```(?:json)?\s*/m', '', $text);
        $text = preg_replace('/\s*```$/m', '', $text);

        // Find the JSON object
        if (preg_match('/\{.*\}/s', $text, $match)) {
            $decoded = json_decode($match[0], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }

        return ['raw_response' => $text, 'parse_error' => json_last_error_msg()];
    }
}
