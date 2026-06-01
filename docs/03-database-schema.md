# Database Schema

PostgreSQL 16 + pgvector extension.

---

## Extensions

```sql
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
CREATE EXTENSION IF NOT EXISTS "vector";
CREATE EXTENSION IF NOT EXISTS "pg_trgm";  -- for fuzzy text matching in reconciliation
```

---

## 1. Identity & Auth

### users
```sql
CREATE TABLE users (
    id              BIGSERIAL PRIMARY KEY,
    name            VARCHAR(255) NOT NULL,
    email           VARCHAR(255) NOT NULL UNIQUE,
    password        VARCHAR(255) NOT NULL,
    role            VARCHAR(50) NOT NULL DEFAULT 'seller',
        -- enum: seller | accountant | agency | workspace_admin | platform_admin
    email_verified_at TIMESTAMPTZ,
    remember_token  VARCHAR(100),
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
```

### personal_access_tokens (Sanctum)
```sql
-- Laravel Sanctum migration — keep default
CREATE TABLE personal_access_tokens (
    id              BIGSERIAL PRIMARY KEY,
    tokenable_type  VARCHAR(255) NOT NULL,
    tokenable_id    BIGINT NOT NULL,
    name            VARCHAR(255) NOT NULL,
    token           VARCHAR(64) NOT NULL UNIQUE,
    abilities       TEXT,
    last_used_at    TIMESTAMPTZ,
    expires_at      TIMESTAMPTZ,
    created_at      TIMESTAMPTZ,
    updated_at      TIMESTAMPTZ
);
CREATE INDEX ON personal_access_tokens (tokenable_type, tokenable_id);
```

### password_reset_tokens
```sql
CREATE TABLE password_reset_tokens (
    email      VARCHAR(255) PRIMARY KEY,
    token      VARCHAR(255) NOT NULL,
    created_at TIMESTAMPTZ
);
```

### audit_logs
```sql
CREATE TABLE audit_logs (
    id          BIGSERIAL PRIMARY KEY,
    user_id     BIGINT REFERENCES users(id) ON DELETE SET NULL,
    action      VARCHAR(100) NOT NULL,
        -- enum: login | logout | upload | delete | export | run_reconciliation | etc.
    entity_type VARCHAR(100),
    entity_id   BIGINT,
    old_values  JSONB,
    new_values  JSONB,
    ip_address  INET,
    user_agent  TEXT,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX ON audit_logs (user_id);
CREATE INDEX ON audit_logs (entity_type, entity_id);
CREATE INDEX ON audit_logs (created_at DESC);
```

---

## 2. Workspaces

### workspaces
```sql
CREATE TABLE workspaces (
    id          BIGSERIAL PRIMARY KEY,
    name        VARCHAR(255) NOT NULL,
    slug        VARCHAR(100) NOT NULL UNIQUE,
    owner_id    BIGINT NOT NULL REFERENCES users(id),
    marketplace VARCHAR(10) NOT NULL DEFAULT 'IN',
        -- Amazon marketplace: IN | US | UK | AE | etc.
    currency    VARCHAR(3) NOT NULL DEFAULT 'INR',
    settings    JSONB NOT NULL DEFAULT '{}',
    created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at  TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
```

### workspace_users
```sql
CREATE TABLE workspace_users (
    id           BIGSERIAL PRIMARY KEY,
    workspace_id BIGINT NOT NULL REFERENCES workspaces(id) ON DELETE CASCADE,
    user_id      BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    role         VARCHAR(50) NOT NULL DEFAULT 'viewer',
        -- enum: owner | admin | editor | viewer | accountant
    created_at   TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    UNIQUE (workspace_id, user_id)
);
```

---

## 3. Imports

### import_batches
```sql
CREATE TABLE import_batches (
    id                BIGSERIAL PRIMARY KEY,
    workspace_id      BIGINT NOT NULL REFERENCES workspaces(id),
    user_id           BIGINT NOT NULL REFERENCES users(id),
    type              VARCHAR(50) NOT NULL,
        -- enum: orders | settlements | bank_statement | gst_report
        --       products | competitors_csv | competitors_html
    original_filename VARCHAR(500) NOT NULL,
    storage_path      VARCHAR(1000),   -- MinIO path
    file_size_bytes   BIGINT,
    status            VARCHAR(20) NOT NULL DEFAULT 'pending',
        -- enum: pending | processing | completed | failed | partial
    total_rows        INTEGER NOT NULL DEFAULT 0,
    processed_rows    INTEGER NOT NULL DEFAULT 0,
    failed_rows       INTEGER NOT NULL DEFAULT 0,
    column_mapping    JSONB,    -- user-confirmed column mapping
    meta              JSONB NOT NULL DEFAULT '{}',
        -- { detected_date_format, detected_currency, row_sample }
    started_at        TIMESTAMPTZ,
    completed_at      TIMESTAMPTZ,
    created_at        TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at        TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX ON import_batches (workspace_id, type);
CREATE INDEX ON import_batches (status);
```

### import_errors
```sql
CREATE TABLE import_errors (
    id              BIGSERIAL PRIMARY KEY,
    import_batch_id BIGINT NOT NULL REFERENCES import_batches(id) ON DELETE CASCADE,
    row_number      INTEGER NOT NULL,
    raw_data        JSONB,
    error_type      VARCHAR(100) NOT NULL,
        -- enum: missing_required | invalid_format | duplicate | parse_error
    error_message   TEXT NOT NULL,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX ON import_errors (import_batch_id);
```

---

## 4. Finance — Orders

### orders
```sql
CREATE TABLE orders (
    id                      BIGSERIAL PRIMARY KEY,
    workspace_id            BIGINT NOT NULL REFERENCES workspaces(id),
    import_batch_id         BIGINT NOT NULL REFERENCES import_batches(id),
    amazon_order_id         VARCHAR(20) NOT NULL,
    merchant_order_id       VARCHAR(100),
    purchase_date           TIMESTAMPTZ NOT NULL,
    last_updated_date       TIMESTAMPTZ,
    order_status            VARCHAR(50) NOT NULL,
        -- Shipped | Unshipped | Cancelled | Pending | PartiallyShipped
    fulfillment_channel     VARCHAR(10),   -- AFN (FBA) | MFN (Self-ship)
    sales_channel           VARCHAR(100),
    ship_service_level      VARCHAR(100),
    sku                     VARCHAR(200),
    asin                    VARCHAR(20),
    product_name            TEXT,
    item_status             VARCHAR(50),
    quantity                INTEGER NOT NULL DEFAULT 1,
    currency                VARCHAR(3) NOT NULL DEFAULT 'INR',
    item_price              NUMERIC(12,2) NOT NULL DEFAULT 0,
    item_tax                NUMERIC(12,2) NOT NULL DEFAULT 0,
    shipping_price          NUMERIC(12,2) NOT NULL DEFAULT 0,
    shipping_tax            NUMERIC(12,2) NOT NULL DEFAULT 0,
    gift_wrap_price         NUMERIC(12,2) NOT NULL DEFAULT 0,
    gift_wrap_tax           NUMERIC(12,2) NOT NULL DEFAULT 0,
    item_promotion_discount NUMERIC(12,2) NOT NULL DEFAULT 0,
    ship_promotion_discount NUMERIC(12,2) NOT NULL DEFAULT 0,
    ship_city               VARCHAR(200),
    ship_state              VARCHAR(100),
    ship_postal_code        VARCHAR(20),
    ship_country            VARCHAR(10),
    is_business_order       BOOLEAN NOT NULL DEFAULT FALSE,
    raw_row                 JSONB,   -- original CSV row preserved
    created_at              TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE UNIQUE INDEX ON orders (workspace_id, amazon_order_id, sku);
CREATE INDEX ON orders (workspace_id, purchase_date DESC);
CREATE INDEX ON orders (workspace_id, order_status);
CREATE INDEX ON orders (asin);
```

---

## 5. Finance — Settlements

### settlements
```sql
CREATE TABLE settlements (
    id                  BIGSERIAL PRIMARY KEY,
    workspace_id        BIGINT NOT NULL REFERENCES workspaces(id),
    import_batch_id     BIGINT NOT NULL REFERENCES import_batches(id),
    settlement_id       VARCHAR(100) NOT NULL,
    settlement_start_date DATE NOT NULL,
    settlement_end_date   DATE NOT NULL,
    deposit_date          DATE,
    deposited_amount      NUMERIC(14,2),
    currency              VARCHAR(3) NOT NULL DEFAULT 'INR',
    transaction_type      VARCHAR(100),
        -- Order | Refund | Transfer | FBA Inventory Fee | etc.
    order_id              VARCHAR(20),
    merchant_order_id     VARCHAR(100),
    adjustment_id         VARCHAR(100),
    shipment_id           VARCHAR(100),
    marketplace_name      VARCHAR(200),
    amount_type           VARCHAR(100),
        -- ItemPrice | ItemFees | Promotion | etc.
    amount_description    VARCHAR(200),
    amount                NUMERIC(12,2) NOT NULL DEFAULT 0,
    fulfillment_id        VARCHAR(100),
    posted_date           DATE,
    posted_datetime       TIMESTAMPTZ,
    sku                   VARCHAR(200),
    quantity_purchased    INTEGER,
    raw_row               JSONB,
    created_at            TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX ON settlements (workspace_id, settlement_id);
CREATE INDEX ON settlements (workspace_id, order_id);
CREATE INDEX ON settlements (workspace_id, deposit_date DESC);
CREATE INDEX ON settlements (workspace_id, transaction_type);
```

---

## 6. Finance — Bank Transactions

### bank_transactions
```sql
CREATE TABLE bank_transactions (
    id               BIGSERIAL PRIMARY KEY,
    workspace_id     BIGINT NOT NULL REFERENCES workspaces(id),
    import_batch_id  BIGINT NOT NULL REFERENCES import_batches(id),
    transaction_date DATE NOT NULL,
    value_date       DATE,
    description      TEXT,
    debit_amount     NUMERIC(14,2) NOT NULL DEFAULT 0,
    credit_amount    NUMERIC(14,2) NOT NULL DEFAULT 0,
    balance          NUMERIC(14,2),
    reference        VARCHAR(500),   -- UTR / transaction reference number
    bank_name        VARCHAR(200),
    raw_row          JSONB,
    created_at       TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX ON bank_transactions (workspace_id, transaction_date DESC);
CREATE INDEX ON bank_transactions (workspace_id, credit_amount)
    WHERE credit_amount > 0;
CREATE INDEX bank_transactions_desc_trgm ON bank_transactions
    USING GIN (description gin_trgm_ops);
```

---

## 7. Finance — GST

### gst_transactions
```sql
CREATE TABLE gst_transactions (
    id                BIGSERIAL PRIMARY KEY,
    workspace_id      BIGINT NOT NULL REFERENCES workspaces(id),
    import_batch_id   BIGINT NOT NULL REFERENCES import_batches(id),
    transaction_type  VARCHAR(100),
        -- SALE | RETURN | CANCELLATION
    invoice_date      DATE,
    invoice_number    VARCHAR(100),
    order_id          VARCHAR(20),
    transaction_id    VARCHAR(100),
    asin              VARCHAR(20),
    sku               VARCHAR(200),
    product_name      TEXT,
    quantity          INTEGER,
    ship_from_state   VARCHAR(100),
    ship_to_state     VARCHAR(100),
    taxable_value     NUMERIC(12,2),
    igst_rate         NUMERIC(5,2),
    igst_amount       NUMERIC(12,2),
    cgst_rate         NUMERIC(5,2),
    cgst_amount       NUMERIC(12,2),
    sgst_rate         NUMERIC(5,2),
    sgst_amount       NUMERIC(12,2),
    cess_rate         NUMERIC(5,2),
    cess_amount       NUMERIC(12,2),
    invoice_amount    NUMERIC(12,2),
    irn               VARCHAR(200),   -- Invoice Reference Number (e-invoicing)
    hsn_sac           VARCHAR(20),
    raw_row           JSONB,
    created_at        TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX ON gst_transactions (workspace_id, invoice_date DESC);
CREATE INDEX ON gst_transactions (workspace_id, order_id);
```

---

## 8. Reconciliation

### reconciliation_runs
```sql
CREATE TABLE reconciliation_runs (
    id               BIGSERIAL PRIMARY KEY,
    workspace_id     BIGINT NOT NULL REFERENCES workspaces(id),
    user_id          BIGINT NOT NULL REFERENCES users(id),
    period_start     DATE NOT NULL,
    period_end       DATE NOT NULL,
    status           VARCHAR(20) NOT NULL DEFAULT 'pending',
        -- pending | running | completed | failed
    summary          JSONB,
        -- { total_orders, matched_orders, unmatched_orders, total_value, ... }
    started_at       TIMESTAMPTZ,
    completed_at     TIMESTAMPTZ,
    created_at       TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX ON reconciliation_runs (workspace_id, created_at DESC);
```

### reconciliation_matches
```sql
CREATE TABLE reconciliation_matches (
    id                    BIGSERIAL PRIMARY KEY,
    reconciliation_run_id BIGINT NOT NULL REFERENCES reconciliation_runs(id) ON DELETE CASCADE,
    order_id              BIGINT REFERENCES orders(id),
    settlement_id         BIGINT REFERENCES settlements(id),
    bank_transaction_id   BIGINT REFERENCES bank_transactions(id),
    match_type            VARCHAR(20) NOT NULL,
        -- exact | fuzzy | manual | unmatched
    match_confidence      NUMERIC(5,2),  -- 0–100
    status                VARCHAR(20) NOT NULL,
        -- matched | partial | unmatched | disputed
    mismatch_amount       NUMERIC(12,2), -- difference if partial
    notes                 TEXT,
    created_at            TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX ON reconciliation_matches (reconciliation_run_id);
CREATE INDEX ON reconciliation_matches (order_id);
CREATE INDEX ON reconciliation_matches (status);
```

### reconciliation_reports
```sql
CREATE TABLE reconciliation_reports (
    id                    BIGSERIAL PRIMARY KEY,
    reconciliation_run_id BIGINT NOT NULL REFERENCES reconciliation_runs(id),
    workspace_id          BIGINT NOT NULL REFERENCES workspaces(id),
    report_type           VARCHAR(50) NOT NULL,
        -- missing_settlements | missing_credits | refund_impact
        -- return_impact | gst_mismatch | summary
    report_data           JSONB NOT NULL,
    export_path           VARCHAR(1000),  -- MinIO path for PDF/Excel
    created_at            TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX ON reconciliation_reports (workspace_id, report_type);
```

---

## 9. Products

### products
```sql
CREATE TABLE products (
    id               BIGSERIAL PRIMARY KEY,
    workspace_id     BIGINT NOT NULL REFERENCES workspaces(id),
    import_batch_id  BIGINT REFERENCES import_batches(id),
    asin             VARCHAR(20) NOT NULL,
    sku              VARCHAR(200),
    title            TEXT,
    brand            VARCHAR(500),
    category         VARCHAR(500),
    sub_category     VARCHAR(500),
    bullet_1         TEXT,
    bullet_2         TEXT,
    bullet_3         TEXT,
    bullet_4         TEXT,
    bullet_5         TEXT,
    description      TEXT,
    price            NUMERIC(12,2),
    currency         VARCHAR(3) DEFAULT 'INR',
    rating           NUMERIC(3,2),      -- 1.00 – 5.00
    review_count     INTEGER DEFAULT 0,
    listing_score    INTEGER,           -- 0–100, computed
    source_type      VARCHAR(20) NOT NULL DEFAULT 'csv',
        -- csv | html
    last_analyzed_at TIMESTAMPTZ,
    raw_data         JSONB,
    created_at       TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at       TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE UNIQUE INDEX ON products (workspace_id, asin);
CREATE INDEX ON products (workspace_id, listing_score DESC);
```

### product_keywords
```sql
CREATE TABLE product_keywords (
    id          BIGSERIAL PRIMARY KEY,
    product_id  BIGINT NOT NULL REFERENCES products(id) ON DELETE CASCADE,
    keyword     VARCHAR(500) NOT NULL,
    source      VARCHAR(30) NOT NULL,
        -- title | bullet | description | backend_search
    frequency   INTEGER NOT NULL DEFAULT 1,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX ON product_keywords (product_id);
CREATE INDEX ON product_keywords (keyword);
```

### product_reviews
```sql
CREATE TABLE product_reviews (
    id               BIGSERIAL PRIMARY KEY,
    product_id       BIGINT NOT NULL REFERENCES products(id) ON DELETE CASCADE,
    import_batch_id  BIGINT REFERENCES import_batches(id),
    external_id      VARCHAR(200),   -- Amazon review ID if available
    reviewer_name    VARCHAR(200),
    rating           SMALLINT NOT NULL,  -- 1–5
    title            TEXT,
    body             TEXT,
    verified_purchase BOOLEAN DEFAULT FALSE,
    review_date      DATE,
    helpful_votes    INTEGER DEFAULT 0,
    created_at       TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX ON product_reviews (product_id);
CREATE INDEX ON product_reviews (product_id, rating);
```

### product_analyses
```sql
CREATE TABLE product_analyses (
    id              BIGSERIAL PRIMARY KEY,
    product_id      BIGINT NOT NULL REFERENCES products(id) ON DELETE CASCADE,
    analysis_type   VARCHAR(50) NOT NULL,
        -- listing_score | keyword_extraction | optimization_suggestions
        -- sentiment | competitor_gap
    ai_provider     VARCHAR(50),
    ai_model        VARCHAR(100),
    prompt_tokens   INTEGER,
    completion_tokens INTEGER,
    analysis_data   JSONB NOT NULL,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX ON product_analyses (product_id, analysis_type);
CREATE INDEX ON product_analyses (created_at DESC);
```

---

## 10. Competitors

### competitors
```sql
CREATE TABLE competitors (
    id               BIGSERIAL PRIMARY KEY,
    workspace_id     BIGINT NOT NULL REFERENCES workspaces(id),
    product_id       BIGINT REFERENCES products(id),   -- our product being compared
    import_batch_id  BIGINT REFERENCES import_batches(id),
    asin             VARCHAR(20) NOT NULL,
    title            TEXT,
    brand            VARCHAR(500),
    category         VARCHAR(500),
    bullet_1         TEXT,
    bullet_2         TEXT,
    bullet_3         TEXT,
    bullet_4         TEXT,
    bullet_5         TEXT,
    description      TEXT,
    price            NUMERIC(12,2),
    currency         VARCHAR(3) DEFAULT 'INR',
    rating           NUMERIC(3,2),
    review_count     INTEGER,
    source_type      VARCHAR(20) NOT NULL DEFAULT 'html',
        -- csv | html
    raw_html         TEXT,       -- original pasted HTML (kept for re-parsing)
    last_analyzed_at TIMESTAMPTZ,
    created_at       TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at       TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE UNIQUE INDEX ON competitors (workspace_id, product_id, asin);
CREATE INDEX ON competitors (product_id);
```

### competitor_keywords
```sql
CREATE TABLE competitor_keywords (
    id            BIGSERIAL PRIMARY KEY,
    competitor_id BIGINT NOT NULL REFERENCES competitors(id) ON DELETE CASCADE,
    keyword       VARCHAR(500) NOT NULL,
    source        VARCHAR(30) NOT NULL,
    frequency     INTEGER NOT NULL DEFAULT 1,
    created_at    TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX ON competitor_keywords (competitor_id);
CREATE INDEX ON competitor_keywords (keyword);
```

### keyword_gaps
```sql
CREATE TABLE keyword_gaps (
    id            BIGSERIAL PRIMARY KEY,
    product_id    BIGINT NOT NULL REFERENCES products(id) ON DELETE CASCADE,
    competitor_id BIGINT NOT NULL REFERENCES competitors(id) ON DELETE CASCADE,
    keyword       VARCHAR(500) NOT NULL,
    gap_type      VARCHAR(20) NOT NULL,
        -- missing (competitor has, we don't)
        -- underused (both have, competitor uses more)
        -- advantage (we have, competitor doesn't)
    our_frequency     INTEGER NOT NULL DEFAULT 0,
    their_frequency   INTEGER NOT NULL DEFAULT 0,
    priority_score    INTEGER,  -- 0–100, computed
    created_at        TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX ON keyword_gaps (product_id);
CREATE INDEX ON keyword_gaps (product_id, gap_type, priority_score DESC);
```

### competitor_benchmarks
```sql
CREATE TABLE competitor_benchmarks (
    id             BIGSERIAL PRIMARY KEY,
    product_id     BIGINT NOT NULL REFERENCES products(id),
    competitor_id  BIGINT NOT NULL REFERENCES competitors(id),
    benchmark_data JSONB NOT NULL,
        -- { price_diff, rating_diff, review_count_diff,
        --   our_score, their_score, title_score, bullet_score, ... }
    created_at     TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX ON competitor_benchmarks (product_id);
```

---

## 11. AI & RAG

### embeddings
```sql
CREATE TABLE embeddings (
    id              BIGSERIAL PRIMARY KEY,
    embeddable_type VARCHAR(100) NOT NULL,
        -- App\Modules\Products\Models\Product
        -- App\Modules\Products\Models\ProductReview
        -- App\Modules\Competitors\Models\Competitor
    embeddable_id   BIGINT NOT NULL,
    chunk_index     INTEGER NOT NULL DEFAULT 0,  -- for multi-chunk documents
    chunk_text      TEXT NOT NULL,               -- the text that was embedded
    embedding       VECTOR(1536) NOT NULL,        -- OpenAI text-embedding-3-small
    model           VARCHAR(100) NOT NULL DEFAULT 'text-embedding-3-small',
    workspace_id    BIGINT NOT NULL REFERENCES workspaces(id),
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE UNIQUE INDEX ON embeddings (embeddable_type, embeddable_id, chunk_index);
-- HNSW index for fast approximate nearest neighbor search
CREATE INDEX embeddings_hnsw ON embeddings
    USING hnsw (embedding vector_cosine_ops)
    WITH (m = 16, ef_construction = 64);
CREATE INDEX ON embeddings (workspace_id);
```

### ai_conversations
```sql
CREATE TABLE ai_conversations (
    id            BIGSERIAL PRIMARY KEY,
    workspace_id  BIGINT NOT NULL REFERENCES workspaces(id),
    user_id       BIGINT NOT NULL REFERENCES users(id),
    title         VARCHAR(500),
    context_type  VARCHAR(30) NOT NULL DEFAULT 'general',
        -- financial | listing | competitor | general
    context_id    BIGINT,    -- optional: reconciliation_run_id, product_id, etc.
    created_at    TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at    TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX ON ai_conversations (workspace_id, user_id);
```

### ai_messages
```sql
CREATE TABLE ai_messages (
    id               BIGSERIAL PRIMARY KEY,
    conversation_id  BIGINT NOT NULL REFERENCES ai_conversations(id) ON DELETE CASCADE,
    role             VARCHAR(20) NOT NULL,   -- user | assistant
    content          TEXT NOT NULL,
    provider         VARCHAR(50),            -- claude | openai | gemini | groq
    model            VARCHAR(100),
    prompt_tokens    INTEGER,
    completion_tokens INTEGER,
    rag_sources      JSONB,   -- which embeddings were retrieved for this response
    created_at       TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX ON ai_messages (conversation_id, created_at ASC);
```

---

## 12. Reports

### reports
```sql
CREATE TABLE reports (
    id            BIGSERIAL PRIMARY KEY,
    workspace_id  BIGINT NOT NULL REFERENCES workspaces(id),
    user_id       BIGINT NOT NULL REFERENCES users(id),
    type          VARCHAR(50) NOT NULL,
        -- reconciliation_summary | missing_settlements | missing_credits
        -- refund_impact | return_impact | gst_mismatch
        -- listing_analysis | competitor_benchmark | keyword_gap
    title         VARCHAR(500) NOT NULL,
    parameters    JSONB NOT NULL DEFAULT '{}',
    status        VARCHAR(20) NOT NULL DEFAULT 'pending',
        -- pending | generating | completed | failed
    file_path     VARCHAR(1000),   -- MinIO path for exported file
    file_format   VARCHAR(10),     -- pdf | xlsx | csv
    generated_at  TIMESTAMPTZ,
    created_at    TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX ON reports (workspace_id, type, created_at DESC);
```

---

## 13. Spatie RBAC Tables

Standard Spatie Laravel Permission tables — created by package migration:
- `roles`
- `permissions`
- `model_has_roles`
- `model_has_permissions`
- `role_has_permissions`

---

## Column Naming Conventions

- Primary keys: `id` (BIGSERIAL)
- Foreign keys: `{table_singular}_id` (e.g., `workspace_id`)
- Timestamps: `created_at`, `updated_at` (TIMESTAMPTZ, always)
- Amounts: `NUMERIC(12,2)` for currency, `NUMERIC(14,2)` for large totals
- Enums: stored as `VARCHAR` with inline comments listing valid values
- Raw data preserved: `raw_row JSONB` on imported tables, `raw_html TEXT` on competitors
- Boolean columns: prefix `is_` (e.g., `is_business_order`)

---

## Migration Order

```
1. users
2. workspaces, workspace_users
3. personal_access_tokens, password_reset_tokens
4. spatie permission tables
5. audit_logs
6. import_batches, import_errors
7. orders, settlements, bank_transactions, gst_transactions
8. reconciliation_runs, reconciliation_matches, reconciliation_reports
9. products, product_keywords, product_reviews, product_analyses
10. competitors, competitor_keywords, keyword_gaps, competitor_benchmarks
11. embeddings (requires pgvector extension)
12. ai_conversations, ai_messages
13. reports
```
