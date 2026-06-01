# Sprint Plan

10 sprints. Each sprint is ~2 weeks.

---

## Dependency Map

```
Sprint 1 (Foundation)
  └── Sprint 2 (Imports)
        ├── Sprint 3 (Finance Data)
        │     └── Sprint 4 (Reconciliation)
        │           └── Sprint 9 (Reports)
        └── Sprint 5 (Products)
              ├── Sprint 6 (Competitors)
              │     └── Sprint 7 (RAG + Embeddings)
              │           └── Sprint 8 (AI Copilot)
              └── Sprint 9 (Reports)
Sprint 10 (Hardening) — runs last, depends on all
```

---

## Sprint 1 — Foundation

**Goal:** Running Docker environment, auth, workspaces, base architecture.

**Deliverables:**
- [ ] `start.sh` script — one command brings up the full stack
- [ ] Docker Compose with all 8 services
- [ ] Laravel 13 application scaffolded
- [ ] Vue 3 + TypeScript + Pinia + Shadcn-Vue frontend scaffolded
- [ ] PostgreSQL + pgvector + pg_trgm extensions initialized
- [ ] MinIO buckets created
- [ ] Sanctum auth (register, login, logout, me)
- [ ] Workspace CRUD
- [ ] RBAC: roles + permissions seeded
- [ ] User invitation to workspace
- [ ] Audit log observer
- [ ] Base API structure (versioned, standard response envelope, error handler)
- [ ] Feature tests: auth, workspace CRUD

**Acceptance Criteria:**
- `bash start.sh` runs with no errors on a fresh machine
- Can register, login, logout via API
- Can create workspace and invite user
- Role-based access blocks unauthorized endpoints (verified by tests)

---

## Sprint 2 — Import Engine

**Goal:** Universal CSV and HTML import pipeline.

**Deliverables:**
- [ ] File upload endpoint (multipart, stored to MinIO)
- [ ] `import_batches` + `import_errors` tables
- [ ] `DetectColumnsJob` — reads header row, suggests mapping
- [ ] Column mapping confirmation flow
- [ ] `ProcessImportJob` — chunk-based CSV processing (500 rows/chunk)
- [ ] Import type handlers:
  - [ ] Orders CSV parser (Amazon format)
  - [ ] Settlements CSV parser (tab-separated with header block)
  - [ ] Bank Statement CSV parser (auto-detect format)
  - [ ] GST Report CSV parser
  - [ ] Products CSV parser
  - [ ] Competitors CSV parser
  - [ ] Competitors HTML paste parser (Symfony DomCrawler)
- [ ] Real-time import progress via polling (`GET /imports/{id}/status`)
- [ ] Import error log (`GET /imports/{id}/errors`)
- [ ] Import history list
- [ ] Frontend: drag-and-drop upload UI with progress bar
- [ ] Frontend: column mapping confirmation UI
- [ ] Frontend: HTML paste textarea for competitor input
- [ ] Feature tests: each import type with sample files

**Acceptance Criteria:**
- Orders CSV of 10,000 rows processes in under 2 minutes
- Settlements tab-separated file parses correctly (header block detected)
- Bank statement auto-detects ICICI and HDFC formats
- HTML paste extracts ASIN, title, bullets, price from a real Amazon product page
- Import errors are logged with row number and reason

**Sample Data Required:**
- Amazon Orders CSV (real export or anonymized)
- Amazon Settlement CSV (real export or anonymized)
- Bank statement CSV (ICICI or HDFC format)
- Amazon GST Report CSV
- Amazon product page HTML source (any product)

---

## Sprint 3 — Finance Data Layer

**Goal:** Orders, settlements, bank, and GST data browsable in the UI.

**Deliverables:**
- [ ] Orders table + API endpoints (list, summary, filter by date/status/SKU)
- [ ] Settlements table + API endpoints
- [ ] Bank transactions table + API endpoints
- [ ] GST transactions table + API endpoints
- [ ] UTR/reference extraction from bank descriptions (regex)
- [ ] Order ↔ GST link by `order_id`
- [ ] Frontend: Orders list with filters, pagination, summary cards
- [ ] Frontend: Settlements list with filters
- [ ] Frontend: Bank transactions list
- [ ] Frontend: GST transactions list
- [ ] Frontend: Financial dashboard (period summary: revenue, tax, status breakdown)
- [ ] Feature tests: all finance endpoints

**Acceptance Criteria:**
- All 4 data types browsable with pagination
- Date range filter works correctly
- Financial summary shows correct totals matching imported data
- Orders linked to GST records where order_id matches

---

## Sprint 4 — Reconciliation Engine

**Goal:** Automated matching and mismatch reports.

**Deliverables:**
- [ ] `reconciliation_runs`, `reconciliation_matches`, `reconciliation_reports` tables
- [ ] `ReconciliationJob`:
  - [ ] Pass A: Exact order ↔ settlement matching
  - [ ] Pass B: Fuzzy refund matching
  - [ ] Pass C: Settlement ↔ bank exact matching
  - [ ] Pass D: Settlement ↔ bank tolerance matching (TDS)
  - [ ] Step 3: GST cross-check
- [ ] Run reconciliation endpoint (`POST /reconciliation/run`)
- [ ] Run status polling endpoint
- [ ] 6 report types:
  - [ ] Missing settlements report
  - [ ] Missing credits report
  - [ ] Refund impact report
  - [ ] Return impact report
  - [ ] GST mismatch report
  - [ ] Summary report
- [ ] Report export (Excel + PDF) via queue
- [ ] Frontend: Reconciliation run wizard (select period → run → progress → results)
- [ ] Frontend: Report views for each type (table + charts)
- [ ] Frontend: Export button per report
- [ ] Feature tests: matching algorithm with known test data

**Acceptance Criteria:**
- Exact matches (order_id in settlement) correctly identified (100% on test dataset)
- TDS deducted settlements match within 2% tolerance
- All 6 report types generate correct data
- PDF export downloads with correct content
- Re-running for same period creates new run, preserves old

---

## Sprint 5 — Product Listing Intelligence

**Goal:** Listing scores, keyword extraction, AI-powered optimization.

**Deliverables:**
- [ ] `products`, `product_keywords`, `product_reviews`, `product_analyses` tables
- [ ] `AnalyzeProductJob`:
  - [ ] Keyword extraction (pure PHP tokenizer)
  - [ ] Rule-based listing score calculation
  - [ ] AI analysis call (Claude) — returns structured JSON
  - [ ] AI rewrite generation
- [ ] Products list + detail API endpoints
- [ ] Listing score breakdown endpoint
- [ ] Optimization suggestions endpoint
- [ ] Frontend: Products list with score badges
- [ ] Frontend: Product detail page (score breakdown, dimension analysis)
- [ ] Frontend: Optimization suggestions with AI rewrite preview
- [ ] Frontend: Review sentiment summary (if reviews imported)
- [ ] Feature tests: scoring algorithm (unit tests for each dimension)

**Acceptance Criteria:**
- Score calculation is deterministic (same input = same score)
- AI analysis completes and stores structured data
- Score dimensions show specific issues with actionable text
- AI rewrite can be accepted and saved back to product

---

## Sprint 6 — Competitor Intelligence

**Goal:** Keyword gap analysis and competitive benchmarking.

**Deliverables:**
- [ ] `competitors`, `competitor_keywords`, `keyword_gaps`, `competitor_benchmarks` tables
- [ ] `CompetitorAnalysisJob`:
  - [ ] Keyword extraction from competitor data
  - [ ] Keyword gap calculation
  - [ ] Priority scoring
  - [ ] Benchmark metric calculation
  - [ ] AI competitor comparison analysis
- [ ] Competitor list + detail API endpoints
- [ ] Keyword gaps endpoint (filterable by gap_type)
- [ ] Benchmark comparison endpoint
- [ ] HTML confidence review UI (fields with < 60% confidence flagged)
- [ ] Frontend: Add competitor (HTML paste or CSV)
- [ ] Frontend: Keyword gap table (missing / underused / advantages)
- [ ] Frontend: Competitor benchmark comparison card
- [ ] Frontend: AI insights panel
- [ ] Feature tests: gap analysis algorithm

**Acceptance Criteria:**
- Keyword normalization handles singular/plural correctly
- Priority scores rank "title keywords" higher than "description keywords"
- Benchmark shows correct deltas (price, score, reviews)
- HTML confidence flags low-confidence fields in UI

---

## Sprint 7 — RAG & Embeddings

**Goal:** Vector search pipeline over seller data.

**Deliverables:**
- [ ] `embeddings` table (pgvector HNSW index)
- [ ] `EmbedDocumentJob`:
  - [ ] Product listings
  - [ ] Product reviews
  - [ ] Competitor listings
  - [ ] Reconciliation summaries
  - [ ] AI analysis results
- [ ] Vector search service (cosine similarity, threshold 0.65, top-k=5)
- [ ] Embedding provider abstraction (OpenAI / Ollama)
- [ ] SQL-assist query templates for financial context
- [ ] Automatic embedding on import completion events
- [ ] Re-embed on update
- [ ] Feature tests: vector search returns relevant results

**Acceptance Criteria:**
- After product import, product data is embedded within 5 minutes
- Searching "mug" returns product records with similarity > 0.65
- Workspace isolation: search never returns other workspace's data
- Fallback to Ollama when OpenAI is unavailable (local dev)

---

## Sprint 8 — AI Copilot

**Goal:** Conversational AI assistant with RAG-backed responses.

**Deliverables:**
- [ ] `ai_conversations`, `ai_messages` tables
- [ ] AI provider routing (`AIRouter` class)
- [ ] Claude integration (primary)
- [ ] OpenAI fallback integration
- [ ] Conversation CRUD endpoints
- [ ] Message send endpoint (RAG retrieval + SQL assist + AI call)
- [ ] Provider fallback chain (Claude → GPT-4o-mini → Groq)
- [ ] Token usage tracking per workspace
- [ ] Rate limiting (30 req/min per workspace)
- [ ] System prompts per context type (financial / listing / general)
- [ ] Conversation history management (summarize old turns)
- [ ] Frontend: Chat UI (conversation list + message thread)
- [ ] Frontend: Context-aware chat launch (from product page, from reconciliation page)
- [ ] Frontend: RAG sources shown below response
- [ ] Frontend: Copy response button
- [ ] Feature tests: AI routing, rate limiting

**Acceptance Criteria:**
- Asking "What are my missing settlements?" returns a response citing actual data
- Asking about a product's listing issues references specific fields
- Fallback provider activates when primary returns error
- Rate limit returns 429 after 30 requests/minute

---

## Sprint 9 — Reports & Export

**Goal:** Exportable PDF and Excel reports for all modules.

**Deliverables:**
- [ ] `reports` table
- [ ] `GenerateReportJob` (queue-based)
- [ ] PDF generation (Laravel DomPDF or Browsershot)
- [ ] Excel generation (Laravel Excel / Maatwebsite)
- [ ] Report types:
  - [ ] Reconciliation summary (PDF + XLSX)
  - [ ] Missing settlements (XLSX)
  - [ ] Missing credits (XLSX)
  - [ ] Refund impact (XLSX)
  - [ ] GST mismatch (XLSX)
  - [ ] Listing analysis (PDF)
  - [ ] Keyword gap (XLSX)
  - [ ] Competitor benchmark (PDF)
- [ ] Report download endpoint (presigned MinIO URL)
- [ ] Reports list with status
- [ ] Frontend: Reports dashboard
- [ ] Frontend: Download buttons on all report views

**Acceptance Criteria:**
- PDF reports have correct header, data tables, and page numbering
- Excel exports have proper column headers and formatted numbers
- Download link works within 30 seconds of export request
- Reports stored in MinIO, accessible via presigned URL

---

## Sprint 10 — Hardening

**Goal:** Security, performance, testing, and polish for production readiness.

**Deliverables:**
- [ ] Security headers middleware
- [ ] CORS configuration (locked to frontend origin)
- [ ] Input sanitization review across all endpoints
- [ ] Rate limiting applied to all route groups
- [ ] Database indexes reviewed and optimized
- [ ] Slow query analysis (EXPLAIN ANALYZE on import + reconciliation jobs)
- [ ] Queue retry policy (exponential backoff, dead-letter logging)
- [ ] MinIO backup verification
- [ ] Feature test coverage: all endpoints (target ≥ 80% coverage)
- [ ] E2E tests: critical user flows (login → upload → reconcile → export)
- [ ] Error pages (404, 500, 403 with branded UI)
- [ ] Loading states and skeleton screens throughout frontend
- [ ] Empty states for all list views
- [ ] Pagination on all list views
- [ ] Mobile responsive check (iPad + mobile)
- [ ] Laravel Telescope enabled for dev
- [ ] `start.sh` tested on clean macOS + Linux environments
- [ ] `README.md` at project root (install + run instructions)

**Acceptance Criteria:**
- No SQL injection vulnerabilities in tested endpoints
- Import of 100k-row CSV completes in under 10 minutes
- Reconciliation of 3-month period (50k orders) completes in under 5 minutes
- All API responses return correct HTTP status codes
- Frontend shows loading states during all async operations

---

## Timeline Overview

| Sprint | Focus | Duration |
|--------|-------|----------|
| 1 | Foundation + Docker + Auth | Weeks 1-2 |
| 2 | Import Engine | Weeks 3-4 |
| 3 | Finance Data Layer | Weeks 5-6 |
| 4 | Reconciliation Engine | Weeks 7-8 |
| 5 | Product Listing Intelligence | Weeks 9-10 |
| 6 | Competitor Intelligence | Weeks 11-12 |
| 7 | RAG + Embeddings | Weeks 13-14 |
| 8 | AI Copilot | Weeks 15-16 |
| 9 | Reports + Export | Weeks 17-18 |
| 10 | Hardening | Weeks 19-20 |

**Total: ~20 weeks (5 months)**

---

## Pre-Sprint Checklist (Must Complete Before Sprint 1)

- [ ] Local machine has Docker Desktop installed and running
- [ ] Node.js 22 installed locally (for initial Vite scaffolding)
- [ ] PHP 8.4 installed locally (for Composer, optional)
- [ ] All API keys obtained:
  - [ ] `ANTHROPIC_API_KEY`
  - [ ] `OPENAI_API_KEY`
  - [ ] `GROQ_API_KEY` (optional)
  - [ ] `GEMINI_API_KEY` (optional)
- [ ] Sample data files obtained:
  - [ ] Amazon Orders CSV (from Seller Central)
  - [ ] Amazon Settlement CSV (from Seller Central)
  - [ ] Bank Statement CSV (ICICI or HDFC format)
  - [ ] Amazon GST Report CSV (from Seller Central)
  - [ ] At least 2 Amazon product page HTML sources
- [ ] GitHub repository created, team access granted
- [ ] Design system decision confirmed: **Shadcn-Vue + Tailwind CSS**
