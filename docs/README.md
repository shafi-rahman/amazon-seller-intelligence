# ASIP — Amazon Seller Intelligence Platform
## Documentation Index

> Version: 1.0 | Status: Pre-Development | Last Updated: 2026-06-01

---

## What Is ASIP?

ASIP is a single-tenant, AI-first platform that answers three questions for Amazon sellers:

- **"Where did my money go?"** — Financial Reconciliation: Orders → Settlements → Bank Credits
- **"Why aren't my products selling?"** — Listing Intelligence: Score, Keywords, Optimization
- **"What should I do next?"** — AI Copilot backed by RAG over seller data

All data enters the system via **file upload or HTML paste** — no Amazon API integration in this phase.

---

## Document Map

| # | Document | What It Covers |
|---|----------|---------------|
| 01 | [Tech Stack](./01-tech-stack.md) | Framework versions, libraries, rationale |
| 02 | [Architecture](./02-architecture.md) | Module structure, data flow, system design |
| 03 | [Database Schema](./03-database-schema.md) | All tables, columns, types, indexes, relationships |
| 04 | [API Contracts](./04-api-contracts.md) | Every endpoint, request/response shape, error format |
| 05 | [Import Engine](./05-import-engine.md) | CSV/HTML parsing, column mapping, validation, queuing |
| 06 | [Financial Reconciliation](./06-financial-reconciliation.md) | Matching algorithm, tolerance rules, all report types |
| 07 | [Product & Listing Intelligence](./07-product-listing-intelligence.md) | Scoring algorithm, keyword extraction, optimization |
| 08 | [Competitor Intelligence](./08-competitor-intelligence.md) | HTML parser, benchmark logic, gap analysis |
| 09 | [RAG & AI Copilot](./09-rag-ai-copilot.md) | Embedding pipeline, vector search, provider routing, prompts |
| 10 | [Auth & RBAC](./10-auth-rbac.md) | Authentication, roles, permissions matrix |
| 11 | [Docker & Infrastructure](./11-docker-infrastructure.md) | Docker Compose, start.sh, environment variables |
| 12 | [Sprint Plan](./12-sprint-plan.md) | 10-sprint breakdown with tasks, dependencies, acceptance criteria |

---

## Quick Facts

| Item | Value |
|------|-------|
| Backend | Laravel 13 / PHP 8.4 |
| Frontend | Vue 3 + TypeScript + Pinia |
| Database | PostgreSQL 16 + pgvector |
| Queue | Redis + Laravel Horizon |
| File Storage | MinIO |
| AI Providers | Claude (primary), OpenAI, Gemini, Groq, Ollama |
| Auth | Laravel Sanctum (SPA session) |
| Multi-tenancy | None — single tenant |
| Data Input | CSV upload + HTML textarea paste (no API) |
| Deployment | Docker Compose via `start.sh` |

---

## Phase 1 Scope (This Document Set)

**In scope:**
- User auth and workspace management
- CSV import: Orders, Settlements, Bank Statements, GST Reports
- Financial reconciliation engine and reports
- Product data via CSV upload
- Competitor data via CSV upload or HTML source paste
- Listing scoring and keyword gap analysis
- RAG-backed AI Copilot
- Report generation and export

**Out of scope (future):**
- Amazon SP-API / MWS direct integration
- Ads intelligence
- Inventory forecasting
- Multi-marketplace support
- Billing / subscription management
