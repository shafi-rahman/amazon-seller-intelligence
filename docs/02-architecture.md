# Architecture

## Pattern: Modular Monolith

ASIP is a **modular monolith** — one deployed application, internally organized into cohesive modules. Each module owns its domain logic and communicates with other modules only through well-defined service interfaces and Laravel events. No microservices, no distributed transactions.

```
app/
├── Modules/
│   ├── Identity/        # Users, auth, profile
│   ├── Workspace/       # Workspace management, members
│   ├── Imports/         # Universal import engine
│   ├── Finance/         # Orders, settlements, bank, GST
│   ├── Reconciliation/  # Matching engine + reports
│   ├── Products/        # Product data, listing scoring
│   ├── Competitors/     # Competitor data, HTML parsing
│   ├── AI/              # RAG, embeddings, AI Copilot, provider routing
│   └── Reports/         # Report generation, export
├── Core/                # Shared: DTOs, traits, enums, base classes
└── Admin/               # Platform admin panel
```

---

## Module Structure (per module)

Each module follows this internal layout:

```
Modules/Finance/
├── Controllers/         # Thin — validate input, call service, return response
├── Services/            # Business logic
├── Repositories/        # DB queries (interface + Eloquent implementation)
├── Models/              # Eloquent models (stay in module)
├── DTOs/                # Typed data transfer objects (PHP 8.4 readonly classes)
├── Jobs/                # Dispatchable queue jobs
├── Events/              # Domain events
├── Listeners/           # React to events (cross-module communication)
├── Requests/            # Form Request validation classes
├── Resources/           # API resources (JSON transformers)
├── Enums/               # Module-specific enums
└── Routes/              # routes/api.php for this module
```

---

## Request Lifecycle

```
HTTP Request
  → Nginx
    → PHP-FPM (Laravel)
      → Middleware (auth, CORS, rate limit)
        → FormRequest (validation)
          → Controller (thin: extract input, call service)
            → Service (business logic, calls repositories)
              → Repository (DB queries via Eloquent)
                → Model → PostgreSQL
              → Events dispatched (async side effects)
              → DTO returned to controller
            → API Resource (transform DTO → JSON)
          → JSON Response
```

---

## Data Flow: Full System

```
┌─────────────────────────────────────────────────────────────────┐
│                         FRONTEND (Vue 3)                        │
│  Upload CSV    │  Paste HTML    │  View Reports  │  AI Copilot  │
└────────┬───────┴───────┬────────┴───────┬────────┴──────┬───────┘
         │               │                │               │
         ▼               ▼                ▼               ▼
┌─────────────────────────────────────────────────────────────────┐
│                        API Layer (Laravel 13)                   │
│    /api/v1/imports  │  /api/v1/products  │  /api/v1/ai/chat    │
└────────┬───────────────────┬─────────────────────┬─────────────┘
         │                   │                     │
         ▼                   ▼                     ▼
┌────────────────┐  ┌─────────────────┐  ┌────────────────────────┐
│  Import Engine │  │ Product/Comp     │  │   AI Module            │
│  (Queue Jobs)  │  │ Intelligence     │  │   RAG Pipeline         │
└────────┬───────┘  └────────┬────────┘  └──────────┬─────────────┘
         │                   │                      │
         ▼                   ▼                      ▼
┌─────────────────────────────────────────────────────────────────┐
│                      PostgreSQL 16 + pgvector                   │
│  orders │ settlements │ bank_transactions │ products │ vectors  │
└─────────────────────────────────────────────────────────────────┘
         │                                          │
         ▼                                          ▼
┌────────────────────┐                  ┌───────────────────────┐
│ Reconciliation     │                  │  AI Providers         │
│ Engine             │                  │  Claude / OpenAI /    │
│ (match + report)   │                  │  Gemini / Groq        │
└────────────────────┘                  └───────────────────────┘
         │
         ▼
┌────────────────────┐
│  Reports Module    │
│  (PDF / Excel)     │
│  stored in MinIO   │
└────────────────────┘
```

---

## Queue Architecture

All heavy processing runs through queues. Queue workers are managed by Laravel Horizon.

```
Queue Definitions:
  imports        → CSV/HTML parsing jobs (high priority, many workers)
  reconciliation → Matching engine jobs (medium priority)
  ai             → AI inference jobs (low priority, throttled by provider limits)
  reports        → Report generation and export jobs (low priority)
  embeddings     → Vector embedding jobs (background, lowest priority)

Worker Config (horizon.php):
  imports:        3 workers, max-time: 300s, memory: 512MB
  reconciliation: 2 workers, max-time: 600s, memory: 256MB
  ai:             2 workers, max-time: 120s, memory: 256MB
  reports:        1 worker,  max-time: 300s, memory: 512MB
  embeddings:     1 worker,  max-time: 600s, memory: 256MB
```

---

## File Storage (MinIO)

```
Buckets:
  asip-uploads/
    ├── imports/{workspace_id}/{import_batch_id}/original.csv
    ├── imports/{workspace_id}/{import_batch_id}/original.xlsx
    └── competitors/{workspace_id}/{product_id}/{timestamp}.html

  asip-reports/
    ├── reconciliation/{workspace_id}/{report_id}.pdf
    ├── reconciliation/{workspace_id}/{report_id}.xlsx
    └── listings/{workspace_id}/{report_id}.pdf

  asip-exports/
    └── {workspace_id}/{export_id}.zip
```

---

## Cross-Module Communication

Modules communicate via **Laravel Events** — never direct service-to-service calls across module boundaries.

```
Example cross-module flows:

ImportCompleted event (dispatched by Imports module)
  → Finance\Listeners\ProcessImportedOrders
  → Finance\Listeners\ProcessImportedSettlements
  → Products\Listeners\ProcessImportedProducts

ReconciliationCompleted event (dispatched by Reconciliation module)
  → Reports\Listeners\GenerateReconciliationReport
  → AI\Listeners\EmbedReconciliationInsights

ProductAnalysisCompleted event (dispatched by Products module)
  → AI\Listeners\EmbedProductData
  → Reports\Listeners\UpdateListingReport
```

---

## Single Tenant Design

This application is **single tenant** — one installation serves one seller organization.

Implications:
- No `tenant_id` column on every table
- No row-level security policies
- No subdomain routing
- `workspace_id` still exists as an organizational grouping (e.g., multiple Amazon marketplaces or brands within one seller account)
- Auth is standard Laravel Sanctum — one user table, one login

---

## Caching Strategy

```
Redis key patterns:
  workspace:{id}:settings          → workspace config (TTL: 1h)
  product:{id}:listing_score       → computed score (TTL: 24h, busted on re-analysis)
  reconciliation:{id}:summary      → report summary (TTL: until next run)
  ai:rate_limit:{provider}:{date}  → daily token usage counter
  import:{id}:progress             → real-time progress (TTL: 1h after complete)
```

---

## Real-Time Updates

Import progress is tracked via polling (not WebSockets) in Phase 1.

```
Frontend polls: GET /api/v1/imports/{id}/status  (every 3s while status=processing)
Backend updates: import_batches.processed_rows incremented by job
Response includes: { status, total_rows, processed_rows, failed_rows, percent }
```

Phase 2 can upgrade to Laravel Reverb (WebSockets) without API changes.
