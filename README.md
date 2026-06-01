# ASIP — Amazon Seller Intelligence Platform

> AI-first platform answering three questions for Amazon sellers:
> **"Where did my money go?"** · **"Why aren't my products selling?"** · **"What should I do next?"**

---

## Quick Start

```bash
git clone https://github.com/shafi-rahman/amazon-seller-intelligence.git
cd amazon-seller-intelligence
bash start.sh
```

App runs at **http://localhost** — default login: `seller@asip.local` / `password`

| Service | URL |
|---------|-----|
| App | http://localhost |
| Queue (Horizon) | http://localhost/horizon |
| File Storage (MinIO) | http://localhost:9001 (asip / secret123) |
| Email (MailHog) | http://localhost:8025 |
| Debug (Telescope) | http://localhost/telescope |

---

## What It Does

**Financial Reconciliation** — Import Orders, Settlements, Bank Statements, GST Reports. 4-pass matching engine identifies missing settlements, missing bank credits, refund/return impact, and GST mismatches. Exports as PDF or CSV.

**Listing Intelligence** — 100-point listing score across 5 dimensions (Title, Bullets, Description, Reviews, Keywords). AI-powered optimization suggestions and full listing rewrites via Groq.

**Competitor Intelligence** — Keyword gap analysis with priority scoring (missing/underused/advantage). Benchmark comparisons with score, price, rating, and review deltas.

**AI Copilot** — Conversational AI backed by pgvector RAG search over your data. Context-aware prompts for financial, listing, and competitor queries. Powered by Groq llama-3.3-70b-versatile.

---

## Stack

| Layer | Technology |
|-------|-----------|
| Backend | Laravel 13 / PHP 8.4 (modular monolith) |
| Frontend | Vue 3 + TypeScript + Tailwind CSS v4 + Pinia |
| Database | PostgreSQL 16 + pgvector (HNSW embeddings) |
| Queue | Redis 7 + Laravel Horizon |
| Files | MinIO (S3-compatible) |
| AI | Groq (primary) · Claude · OpenAI |
| Dev tools | Laravel Telescope · MailHog |

---

## Data Entry — No API Required

| Data | Format | Where to Get |
|------|--------|-------------|
| Orders | CSV | Seller Central → Reports → Order Reports |
| Settlements | Tab-separated | Seller Central → Reports → Payments |
| Bank Statement | CSV | Your bank's CSV export |
| GST Report | CSV | Seller Central → Reports → Tax Document Library |
| Products | CSV | Any spreadsheet with ASIN, title, bullets |
| Competitors | HTML paste | Browser Ctrl+U → Select All → Paste |

---

## Environment Setup

```dotenv
# .env — copy from .env.example

# AI (Groq is configured and primary)
GROQ_API_KEY=your_key
GROQ_MODEL=llama-3.3-70b-versatile
AI_DEFAULT_PROVIDER=groq

# Optional: Anthropic Claude (higher-quality fallback)
ANTHROPIC_API_KEY=

# Required for RAG embeddings
OPENAI_API_KEY=

# All database/Redis/MinIO settings are pre-configured for Docker
```

---

## Architecture

```
app/Modules/
├── Identity/        Auth + registration
├── Workspace/       Multi-workspace, RBAC (5 roles, 18 permissions)
├── Imports/         Universal CSV/HTML import engine (7 parsers)
├── Finance/         Orders, Settlements, Bank, GST APIs
├── Reconciliation/  4-pass matching engine, 6 report types
├── Products/        Listing scoring, keyword extraction, AI rewrites
├── Competitors/     Keyword gap analysis, competitive benchmarks
├── AI/              pgvector RAG, embeddings, AI Copilot
└── Reports/         PDF/CSV exports (8 report types)
```

All sprints complete (Sprints 1–10). See [`/docs`](./docs/) for full technical documentation.

---

## Development Commands

```bash
# Run tests
docker compose exec app php artisan test

# Fresh database
docker compose exec app php artisan migrate:fresh --seed

# Run a specific test
docker compose exec app php artisan test --filter=ReconciliationEngineTest

# Queue dashboard
open http://localhost/horizon

# Debug requests
open http://localhost/telescope
```

---

## Requirements

- Docker Desktop · 4GB RAM minimum · 20GB disk
- No PHP, Node, or Composer needed on the host machine
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

In addition, [Laracasts](https://laracasts.com) contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

You can also watch bite-sized lessons with real-world projects on [Laravel Learn](https://laravel.com/learn), where you will be guided through building a Laravel application from scratch while learning PHP fundamentals.

## Agentic Development

Laravel's predictable structure and conventions make it ideal for AI coding agents like Claude Code, Cursor, and GitHub Copilot. Install [Laravel Boost](https://laravel.com/docs/ai) to supercharge your AI workflow:

```bash
composer require laravel/boost --dev

php artisan boost:install
```

Boost provides your agent 15+ tools and skills that help agents build Laravel applications while following best practices.

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
