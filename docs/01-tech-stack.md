# Tech Stack

## Backend

| Layer | Technology | Version | Purpose |
|-------|-----------|---------|---------|
| Framework | Laravel | 13.x | Application framework, routing, ORM, queues |
| Language | PHP | 8.4 | Runtime |
| ORM | Eloquent | (bundled) | Database abstraction |
| Auth | Laravel Sanctum | latest | SPA session-based auth |
| Queue | Laravel Horizon | latest | Queue dashboard + Redis-backed workers |
| Roles | Spatie Laravel Permission | latest | RBAC — roles and permissions |
| File | League/Flysystem + MinIO driver | latest | File upload/storage abstraction |
| CSV Parsing | League/CSV | 9.x | Parse CSV imports |
| HTML Parsing | Symfony DomCrawler + CSS Selector | latest | Parse competitor HTML paste |
| AI SDK | OpenAI PHP Client | latest | OpenAI + compatible providers |
| AI SDK | Anthropic PHP SDK | latest | Claude |
| Vector | pgvector PHP | latest | pgvector query helpers |
| Testing | PHPUnit + Pest | latest | Unit and feature tests |

## Frontend

| Layer | Technology | Version | Purpose |
|-------|-----------|---------|---------|
| Framework | Vue | 3.x | UI framework |
| Language | TypeScript | 5.x | Type-safe JS |
| State | Pinia | 2.x | Global state management |
| Router | Vue Router | 4.x | SPA routing |
| UI Library | Shadcn-Vue + Tailwind CSS | latest | Component library + styling |
| HTTP | Axios | latest | API calls with CSRF support |
| Charts | ApexCharts (vue3-apexcharts) | latest | Financial charts and graphs |
| Tables | TanStack Table (Vue adapter) | latest | Large dataset tables with sorting/filtering |
| Forms | VeeValidate + Zod | latest | Form validation with TypeScript schemas |
| Build | Vite | 5.x | Fast dev server and bundler |

## Database

| Technology | Version | Purpose |
|-----------|---------|---------|
| PostgreSQL | 16 | Primary relational database |
| pgvector | 0.7+ | Vector embeddings storage (HNSW index) |
| Redis | 7.x | Queue backend, session cache, rate limiting |

## Infrastructure

| Technology | Purpose |
|-----------|---------|
| Docker + Docker Compose | Full environment containerization |
| MinIO | S3-compatible local file storage (uploaded CSVs, HTML files, report exports) |
| Nginx | Reverse proxy to PHP-FPM |
| Supervisor | Process manager inside app container (Horizon, scheduler) |
| Mailhog | Dev email catching (no real mail needed in dev) |

## AI Providers

| Provider | Model | Use Case | Priority |
|----------|-------|----------|----------|
| Anthropic Claude | claude-sonnet-4-5 | Listing analysis, AI Copilot reasoning | Primary |
| OpenAI | text-embedding-3-small | Vector embeddings for RAG | Primary for embeddings |
| OpenAI | gpt-4o-mini | Fallback reasoning tasks | Fallback |
| Google Gemini | gemini-1.5-flash | Bulk extraction tasks (cost-efficient) | Optional |
| Groq | llama-3.1-8b-instant | Fast, cheap simple tasks | Optional |
| Ollama | nomic-embed-text | Local embeddings (no API cost) | Dev/offline |

## Key Library Decisions & Rationale

**Why Shadcn-Vue + Tailwind?**
Unstyled components with full control. No vendor lock-in on UI. Works well with Tailwind utility classes throughout.

**Why Spatie Permission?**
Mature, well-tested RBAC package for Laravel. Integrates cleanly with Sanctum. Avoids building custom role middleware.

**Why League/CSV?**
Memory-efficient streaming CSV reader. Handles malformed rows gracefully. Better than `fgetcsv` for large files.

**Why Symfony DomCrawler?**
Bundled with Laravel via Goutte heritage. Works offline (no headless browser). Fast enough for parsing pasted HTML.

**Why pgvector over a dedicated vector DB?**
Keeps the stack simple — one database for all data. pgvector with HNSW index is fast enough for the expected dataset size (<1M vectors). Avoids managing a separate Qdrant/Pinecone service.

**Why Sanctum for auth?**
This is a SPA. Sanctum cookie-based sessions are the correct Laravel-recommended approach for first-party SPAs. No token juggling.

## PHP 8.4 Features Used

- Property hooks (clean DTOs)
- Asymmetric visibility (`public private(set)`)
- `#[\Deprecated]` attribute
- Intersection types
- `array_find()`, `array_find_key()` native helpers

## Version Lock (start.sh will enforce these)

```
PHP=8.4
LARAVEL=13
NODE=22
POSTGRES=16
PGVECTOR=0.7.4
REDIS=7
MINIO=latest
```
