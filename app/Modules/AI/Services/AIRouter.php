<?php

namespace App\Modules\AI\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class AIRouter
{
    /**
     * Provider priority order. First configured provider wins.
     * Groq is primary (fast, cheap, OpenAI-compatible).
     * Anthropic Claude is higher-quality fallback.
     * OpenAI GPT-4o-mini is final fallback.
     */
    private array $providerChain = ['groq', 'anthropic', 'openai'];

    /**
     * Send a chat request and return the assistant response text.
     */
    public function chat(
        array  $messages,
        string $taskType = 'general',
        int    $maxTokens = 2048,
        int    $workspaceId = 0,
    ): array {
        $preferred = config('ai.default_provider', 'groq');

        // Reorder chain to try preferred provider first
        $chain = array_unique(array_merge([$preferred], $this->providerChain));

        $lastError = null;
        foreach ($chain as $provider) {
            if (!$this->isProviderConfigured($provider)) {
                continue;
            }

            try {
                $result = $this->callProvider($provider, $messages, $maxTokens);
                $this->trackTokens($workspaceId, $provider, $result);
                return $result;
            } catch (\Throwable $e) {
                $lastError = $e;
                Log::warning("AI provider [{$provider}] failed, trying next", [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        throw new \RuntimeException(
            'All AI providers failed or are unconfigured. '
            . ($lastError ? 'Last error: '.$lastError->getMessage() : 'Add GROQ_API_KEY, ANTHROPIC_API_KEY, or OPENAI_API_KEY to .env.')
        );
    }

    public function isAnyProviderConfigured(): bool
    {
        foreach ($this->providerChain as $provider) {
            if ($this->isProviderConfigured($provider)) {
                return true;
            }
        }
        return false;
    }

    public function activeProvider(): ?string
    {
        $preferred = config('ai.default_provider', 'groq');
        $chain     = array_unique(array_merge([$preferred], $this->providerChain));

        foreach ($chain as $provider) {
            if ($this->isProviderConfigured($provider)) {
                return $provider;
            }
        }
        return null;
    }

    // ─── Provider Implementations ──────────────────────────────────────

    private function callProvider(string $provider, array $messages, int $maxTokens): array
    {
        return match ($provider) {
            'groq'      => $this->callOpenAICompatible(
                config('ai.providers.groq.api_url').'/chat/completions',
                config('ai.providers.groq.api_key'),
                config('ai.providers.groq.model'),
                $messages,
                $maxTokens,
                $provider,
            ),
            'openai'    => $this->callOpenAICompatible(
                config('ai.providers.openai.api_url').'/chat/completions',
                config('ai.providers.openai.api_key'),
                config('ai.providers.openai.model', 'gpt-4o-mini'),
                $messages,
                $maxTokens,
                $provider,
            ),
            'anthropic' => $this->callAnthropic($messages, $maxTokens),
            default     => throw new \InvalidArgumentException("Unknown provider: {$provider}"),
        };
    }

    private function callOpenAICompatible(
        string $url,
        string $apiKey,
        string $model,
        array  $messages,
        int    $maxTokens,
        string $providerName,
    ): array {
        $response = Http::withHeaders([
            'Authorization' => "Bearer {$apiKey}",
            'Content-Type'  => 'application/json',
        ])
            ->timeout(90)
            ->post($url, [
                'model'      => $model,
                'messages'   => $messages,
                'max_tokens' => $maxTokens,
                'temperature'=> 0.7,
            ]);

        if (!$response->ok()) {
            throw new \RuntimeException(
                "{$providerName} API error {$response->status()}: ".$response->body()
            );
        }

        $data = $response->json();

        return [
            'content'           => $data['choices'][0]['message']['content'] ?? '',
            'provider'          => $providerName,
            'model'             => $model,
            'prompt_tokens'     => $data['usage']['prompt_tokens'] ?? 0,
            'completion_tokens' => $data['usage']['completion_tokens'] ?? 0,
        ];
    }

    private function callAnthropic(array $messages, int $maxTokens): array
    {
        // Anthropic requires system message to be separate
        $systemMsg = '';
        $filtered  = [];

        foreach ($messages as $msg) {
            if ($msg['role'] === 'system') {
                $systemMsg = $msg['content'];
            } else {
                $filtered[] = $msg;
            }
        }

        $payload = [
            'model'      => config('ai.providers.anthropic.model', 'claude-sonnet-4-5'),
            'max_tokens' => $maxTokens,
            'messages'   => $filtered,
        ];

        if ($systemMsg) {
            $payload['system'] = $systemMsg;
        }

        $response = Http::withHeaders([
            'x-api-key'         => config('ai.providers.anthropic.api_key'),
            'anthropic-version' => config('ai.providers.anthropic.version', '2023-06-01'),
            'Content-Type'      => 'application/json',
        ])
            ->timeout(90)
            ->post(config('ai.providers.anthropic.api_url'), $payload);

        if (!$response->ok()) {
            throw new \RuntimeException(
                'Anthropic API error '.$response->status().': '.$response->body()
            );
        }

        $data = $response->json();

        return [
            'content'           => $data['content'][0]['text'] ?? '',
            'provider'          => 'anthropic',
            'model'             => $data['model'] ?? config('ai.providers.anthropic.model'),
            'prompt_tokens'     => $data['usage']['input_tokens'] ?? 0,
            'completion_tokens' => $data['usage']['output_tokens'] ?? 0,
        ];
    }

    // ─── Helpers ──────────────────────────────────────────────────────────

    private function isProviderConfigured(string $provider): bool
    {
        return match ($provider) {
            'groq'      => !empty(config('ai.providers.groq.api_key')),
            'anthropic' => !empty(config('ai.providers.anthropic.api_key')),
            'openai'    => !empty(config('ai.providers.openai.api_key')),
            default     => false,
        };
    }

    private function trackTokens(int $workspaceId, string $provider, array $result): void
    {
        if ($workspaceId === 0) {
            return;
        }

        try {
            $date = now()->toDateString();
            $key  = "ai:tokens:{$workspaceId}:{$date}";

            Redis::connection('default')->pipeline(function ($pipe) use ($key, $result) {
                $pipe->incrby("{$key}:prompt",     $result['prompt_tokens'] ?? 0);
                $pipe->incrby("{$key}:completion", $result['completion_tokens'] ?? 0);
                $pipe->expire("{$key}:prompt",     86400 * 2); // 2-day TTL
                $pipe->expire("{$key}:completion", 86400 * 2);
            });
        } catch (\Throwable $e) {
            Log::warning('Failed to track AI tokens', ['error' => $e->getMessage()]);
        }
    }
}
