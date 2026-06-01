# RAG & AI Copilot

---

## Overview

The AI Copilot uses Retrieval-Augmented Generation (RAG) to answer seller questions grounded in their actual data. Responses are not generic — they reference the seller's specific orders, listings, competitors, and reconciliation results.

```
User Question
  → Query → Embedding (OpenAI text-embedding-3-small)
    → Vector Search in pgvector (top-k=5 most relevant chunks)
      → Retrieve chunk text as context
        → Build prompt: system + context + conversation history + question
          → Send to AI provider (Claude primary)
            → Return answer with cited sources
```

---

## What Gets Embedded

| Data Type | Why | Table |
|-----------|-----|-------|
| Product titles + bullets + description | Listing intelligence queries | `products` |
| Product reviews | "What are customers complaining about?" | `product_reviews` |
| Competitor listings | Competitive comparison queries | `competitors` |
| Reconciliation summaries | Financial queries | `reconciliation_reports` |
| AI analysis results | "What did you find about my listing last time?" | `product_analyses` |

**Not embedded:**
- Raw orders rows (too many, too structured — use SQL queries instead)
- Raw settlements rows (same reason)
- Bank transactions (same reason)
- GST rows (same reason)

For financial queries, the AI uses a **SQL-assisted approach**: it runs predefined aggregate queries against the DB and injects structured numbers into the prompt context.

---

## Embedding Pipeline

### Chunking Strategy

| Content Type | Chunking Rule |
|-------------|---------------|
| Product listing | One chunk: title + bullets + description (fits in 512 tokens) |
| Product reviews | One chunk per review (body only) |
| Competitor listing | One chunk: title + bullets + description |
| Reconciliation summary | One chunk per report type per run |
| AI analysis | One chunk per analysis result |

If a single chunk exceeds 512 tokens: split at sentence boundaries with 50-token overlap between chunks.

### Embedding Model
- **Primary**: `text-embedding-3-small` (OpenAI) — 1536 dimensions, cost-efficient
- **Dev/Offline fallback**: `nomic-embed-text` via Ollama — 768 dimensions

When using Ollama (nomic-embed-text), the vector dimension is 768 instead of 1536. The `embeddings` table uses `VECTOR(1536)` — when running with Ollama, pad vectors to 1536 by appending zeros. This is a development-only concession.

### EmbedDocumentJob

```
Triggered by: ProductAnalysisCompleted, CompetitorsImportCompleted,
              ReconciliationCompleted, ProductsImportCompleted

For each document to embed:
  1. Build chunk text from model fields
  2. If chunk > 512 tokens → split into chunks with 50-token overlap
  3. For each chunk:
     a. Call embedding API
     b. Upsert to embeddings table (on conflict: update embedding + chunk_text)
  4. Handle API errors with exponential backoff (3 retries)
```

---

## Vector Search

### Query Flow

```php
public function search(string $query, int $workspaceId, int $topK = 5): array
{
    $queryEmbedding = $this->embed($query);  // 1536-dim vector

    return DB::select("
        SELECT
            e.id,
            e.embeddable_type,
            e.embeddable_id,
            e.chunk_text,
            1 - (e.embedding <=> :embedding) AS similarity
        FROM embeddings e
        WHERE e.workspace_id = :workspace_id
          AND 1 - (e.embedding <=> :embedding2) > 0.65
        ORDER BY e.embedding <=> :embedding3
        LIMIT :top_k
    ", [
        'embedding'  => $this->toVectorLiteral($queryEmbedding),
        'embedding2' => $this->toVectorLiteral($queryEmbedding),
        'embedding3' => $this->toVectorLiteral($queryEmbedding),
        'workspace_id' => $workspaceId,
        'top_k' => $topK,
    ]);
}
```

### Similarity Threshold: 0.65
Results below 0.65 cosine similarity are dropped — they're not relevant enough.

### Top-K: 5
Retrieve the 5 most relevant chunks. If context window allows, increase to 10 for complex queries.

---

## AI Provider Routing

### Provider Selection Logic

```php
class AIRouter
{
    public function selectProvider(string $taskType): string
    {
        return match($taskType) {
            // Reasoning-heavy tasks → Claude
            'copilot_chat', 'listing_analysis', 'competitor_analysis',
            'financial_analysis', 'rewrite'
                => 'claude',

            // Embeddings always → OpenAI (or Ollama locally)
            'embedding'
                => config('app.env') === 'local' ? 'ollama' : 'openai',

            // Bulk/cheap keyword extraction → Groq (fast + cheap)
            'keyword_extraction'
                => 'groq',

            // Structured data extraction from HTML fallback → Gemini
            'html_extraction'
                => 'gemini',

            default => 'claude',
        };
    }
}
```

### Provider Config

```php
// config/ai.php
[
    'claude' => [
        'api_key'  => env('ANTHROPIC_API_KEY'),
        'model'    => 'claude-sonnet-4-5',
        'max_tokens' => 4096,
    ],
    'openai' => [
        'api_key'  => env('OPENAI_API_KEY'),
        'model'    => 'gpt-4o-mini',
        'embedding_model' => 'text-embedding-3-small',
    ],
    'groq' => [
        'api_key'  => env('GROQ_API_KEY'),
        'model'    => 'llama-3.1-8b-instant',
        'base_url' => 'https://api.groq.com/openai/v1',
    ],
    'gemini' => [
        'api_key'  => env('GEMINI_API_KEY'),
        'model'    => 'gemini-1.5-flash',
    ],
    'ollama' => [
        'base_url' => env('OLLAMA_BASE_URL', 'http://ollama:11434'),
        'embedding_model' => 'nomic-embed-text',
    ],
]
```

### Fallback Chain

If the primary provider fails (API error, rate limit, timeout):

```
claude → openai (gpt-4o-mini) → groq (llama-3.1-8b)
openai embedding → ollama (nomic-embed-text)
```

---

## AI Copilot System Prompts

### Financial Context

```
You are ASIP, an AI assistant for Amazon sellers in India.

You have access to this seller's financial data:
- Amazon Orders
- Amazon Settlement Reports
- Bank Transaction Records
- GST Reports

Your role: help the seller understand their financial position,
find missing payments, explain reconciliation gaps, and identify
financial risks.

Rules:
1. Always cite specific numbers from the context provided
2. When you don't have data to answer, say so clearly
3. Suggest concrete next steps (e.g., "Download your settlement report for Jan 15")
4. Amounts are in INR unless stated otherwise
5. Keep responses concise and actionable

Context from seller's data:
{rag_context}

Conversation history:
{history}
```

### Listing Context

```
You are ASIP, an AI assistant for Amazon sellers.

You are helping this seller improve their product listings.
You have analyzed their listing data, competitor data, and customer reviews.

Your role: explain listing weaknesses, recommend specific improvements,
and help the seller outperform competitors.

Rules:
1. Be specific — quote actual text from their listing when pointing out issues
2. Provide rewritten versions when suggesting changes
3. Prioritize changes by expected impact (high/medium/low)
4. Reference competitor data when available

Context from seller's data:
{rag_context}

Conversation history:
{history}
```

### General Context

```
You are ASIP, an AI assistant for Amazon sellers in India.

You help sellers understand their business performance on Amazon,
improve their listings, and ensure their financials are in order.

Context from seller's data:
{rag_context}

Answer only based on the provided context and conversation history.
If data is not available to answer the question, say so directly.

Conversation history:
{history}
```

---

## Financial Query SQL Assist

For financial questions, the AI is augmented with pre-computed SQL results injected as context:

### Query Templates

**"How many orders did I have this month?"**
```sql
SELECT
    COUNT(*) as total_orders,
    SUM(item_price) as total_revenue,
    SUM(item_tax) as total_tax,
    COUNT(CASE WHEN order_status = 'Cancelled' THEN 1 END) as cancelled_orders
FROM orders
WHERE workspace_id = :wid
  AND purchase_date BETWEEN :start AND :end
```

**"What are my top selling products?"**
```sql
SELECT sku, asin, product_name,
       COUNT(*) as order_count,
       SUM(item_price) as total_revenue,
       SUM(quantity) as units_sold
FROM orders
WHERE workspace_id = :wid
  AND order_status = 'Shipped'
  AND purchase_date BETWEEN :start AND :end
GROUP BY sku, asin, product_name
ORDER BY total_revenue DESC
LIMIT 10
```

**"Show my reconciliation status"**
```sql
SELECT
    rr.period_start, rr.period_end,
    rr.summary->>'total_orders' as total_orders,
    rr.summary->>'matched_orders' as matched,
    rr.summary->>'unmatched_orders' as unmatched,
    rr.summary->>'total_order_value' as order_value
FROM reconciliation_runs rr
WHERE rr.workspace_id = :wid
ORDER BY rr.created_at DESC
LIMIT 3
```

The SQL result is formatted as a compact JSON summary and injected into the RAG context alongside vector search results.

---

## Conversation History Management

Keep the last **10 messages** (5 turns) in context. For longer conversations, summarize older turns:

```
If conversation.messages.count > 10:
  Summarize messages[0..n-10] into one summary line
  Replace with: { role: "system", content: "Earlier conversation summary: {summary}" }
  Keep messages[n-9..n] verbatim
```

---

## Token Budget

Per AI Copilot request:
| Component | Max Tokens |
|-----------|-----------|
| System prompt | ~200 |
| RAG context (5 chunks × ~300 tokens) | ~1500 |
| SQL assist results | ~500 |
| Conversation history (last 10 messages) | ~2000 |
| User question | ~200 |
| **Total input** | ~4400 |
| Response | 1024–2048 |

Model's context window (claude-sonnet-4-5 = 200k tokens) is far larger than needed — no risk of truncation.

---

## Rate Limiting

AI requests are rate-limited per workspace:

```php
RateLimiter::for('ai', function (Request $request) {
    return Limit::perMinute(30)->by($request->user()?->workspace_id);
});
```

Daily token usage is tracked in Redis:
```
ai:tokens:{provider}:{workspace_id}:{date}  → integer (total tokens used)
```

Alert at 80% of any configured daily budget.
