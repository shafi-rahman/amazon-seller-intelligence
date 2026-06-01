<?php

namespace App\Modules\AI\Services;

use App\Modules\AI\Models\AiConversation;
use App\Modules\AI\Models\AiMessage;

class CopilotService
{
    // Max messages to keep in prompt context (user + assistant turns)
    private const HISTORY_LIMIT = 10;

    public function __construct(
        private readonly AIRouter          $router,
        private readonly VectorSearchService $vectorSearch,
        private readonly SqlAssistService  $sqlAssist,
    ) {}

    /**
     * Send a message in a conversation and return the AI response.
     */
    public function chat(AiConversation $conversation, string $userMessage): AiMessage
    {
        // 1. Store the user message first
        AiMessage::create([
            'conversation_id' => $conversation->id,
            'role'            => 'user',
            'content'         => $userMessage,
        ]);

        // 2. Retrieve RAG context
        $ragResults = $this->retrieveContext($conversation, $userMessage);
        $ragSources = $this->buildRagSources($ragResults);
        $ragContext  = $this->vectorSearch->formatAsContext($ragResults);

        // 3. SQL-assist for structured data
        $sqlContext = $this->getSqlContext($conversation);

        // 4. Build conversation history (last N messages)
        $history = $conversation->messages()
            ->orderByDesc('created_at')
            ->limit(self::HISTORY_LIMIT)
            ->get()
            ->reverse()
            ->values();

        // 5. Build messages array for the AI
        $messages = $this->buildMessages(
            $conversation->context_type,
            $ragContext,
            $sqlContext,
            $history->toArray(),
            $userMessage,
        );

        // 6. Call AI
        $response = $this->router->chat($messages, $conversation->context_type, 2048, $conversation->workspace_id);

        // 7. Store assistant response
        $assistantMsg = AiMessage::create([
            'conversation_id'  => $conversation->id,
            'role'             => 'assistant',
            'content'          => $response['content'],
            'provider'         => $response['provider'],
            'model'            => $response['model'],
            'prompt_tokens'    => $response['prompt_tokens'],
            'completion_tokens'=> $response['completion_tokens'],
            'rag_sources'      => $ragSources,
        ]);

        // 8. Auto-title the conversation if this is the first exchange
        if ($conversation->title === null) {
            $conversation->update([
                'title' => $this->generateTitle($userMessage),
            ]);
        }

        return $assistantMsg;
    }

    // ─── Context retrieval ────────────────────────────────────────────────

    private function retrieveContext(AiConversation $conversation, string $query): array
    {
        if (!$this->vectorSearch->isAvailable()) {
            return [];
        }

        return match ($conversation->context_type) {
            'financial'  => $this->vectorSearch->search($query, $conversation->workspace_id, topK: 5),
            'listing'    => $this->vectorSearch->searchProducts($query, $conversation->workspace_id),
            'competitor' => $this->vectorSearch->searchCompetitors($query, $conversation->workspace_id),
            default      => $this->vectorSearch->search($query, $conversation->workspace_id, topK: 5),
        };
    }

    private function getSqlContext(AiConversation $conversation): string
    {
        try {
            return match ($conversation->context_type) {
                'financial' => $this->sqlAssist->formatFinancialContext(
                    $this->sqlAssist->getFinancialContext($conversation->workspace_id)
                ),
                'listing' => $conversation->context_id
                    ? json_encode($this->sqlAssist->getListingContext($conversation->workspace_id, $conversation->context_id))
                    : '',
                default => '',
            };
        } catch (\Throwable) {
            return '';
        }
    }

    private function buildRagSources(array $results): array
    {
        return collect($results)->map(fn($r) => [
            'type'       => class_basename($r->embeddable_type),
            'id'         => $r->embeddable_id,
            'similarity' => round($r->similarity * 100),
            'excerpt'    => mb_substr($r->chunk_text, 0, 150),
        ])->toArray();
    }

    // ─── Message building ──────────────────────────────────────────────────

    private function buildMessages(
        string $contextType,
        string $ragContext,
        string $sqlContext,
        array  $history,
        string $userMessage,
    ): array {
        $systemPrompt = $this->systemPrompt($contextType, $ragContext, $sqlContext);

        $messages = [['role' => 'system', 'content' => $systemPrompt]];

        // Add conversation history (skip the last user message — we add it fresh)
        foreach (array_slice($history, 0, -1) as $msg) {
            if (is_array($msg)) {
                $messages[] = ['role' => $msg['role'], 'content' => $msg['content']];
            } else {
                $messages[] = ['role' => $msg->role, 'content' => $msg->content];
            }
        }

        $messages[] = ['role' => 'user', 'content' => $userMessage];

        return $messages;
    }

    private function systemPrompt(string $contextType, string $ragContext, string $sqlContext): string
    {
        $contextBlock = '';

        if ($sqlContext) {
            $contextBlock .= "\n\nSTRUCTURED DATA FROM DATABASE:\n{$sqlContext}";
        }
        if ($ragContext) {
            $contextBlock .= "\n\nRELATED CONTENT FROM KNOWLEDGE BASE:\n{$ragContext}";
        }
        if (!$contextBlock) {
            $contextBlock = "\n\n(No additional context retrieved for this query)";
        }

        return match ($contextType) {
            'financial' => <<<PROMPT
You are ASIP, an AI assistant for Amazon sellers in India.

You have access to the seller's financial data: Orders, Settlement Reports, Bank Transactions, and GST Records.

Your role: help the seller understand their financial position, find missing payments, explain reconciliation gaps, and identify financial risks.

Rules:
1. Always cite specific numbers from the data provided below
2. Amounts are in INR unless stated otherwise
3. When you don't have sufficient data, say so and suggest what data to import
4. Keep responses concise and actionable — suggest next steps
{$contextBlock}
PROMPT,

            'listing' => <<<PROMPT
You are ASIP, an AI assistant for Amazon sellers.

You are helping this seller improve their product listings to increase visibility and conversion.

Your role: explain listing weaknesses, recommend specific improvements, and help outperform competitors.

Rules:
1. Be specific — reference actual fields from the listing data below
2. Provide rewritten examples when suggesting changes
3. Prioritize changes by expected impact (high/medium/low)
4. Reference competitor keyword data when available
{$contextBlock}
PROMPT,

            default => <<<PROMPT
You are ASIP, an AI assistant for Amazon sellers in India.

You help sellers understand their business performance on Amazon, improve their listings, and ensure their financials are in order.

Rules:
1. Answer only based on the provided context and conversation history
2. If data is insufficient, clearly say so and guide the seller on what to import
3. Keep responses practical and actionable
{$contextBlock}
PROMPT,
        };
    }

    private function generateTitle(string $firstMessage): string
    {
        // Take first 60 chars of the first user message as the title
        $title = trim($firstMessage);
        return mb_strlen($title) > 60
            ? mb_substr($title, 0, 57).'…'
            : $title;
    }
}
