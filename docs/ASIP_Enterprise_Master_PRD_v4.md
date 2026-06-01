# ASIP Enterprise Master PRD v4

## Amazon Seller Intelligence Platform
Enterprise Product Requirements Document + Architecture Blueprint + AI Development Specification

Version: 4.0

## Executive Summary
ASIP is an AI-first platform focused on:
- Financial Reconciliation Intelligence
- Listing & Competitor Intelligence

Core Questions:
- Where did my money go?
- Why are my products not selling?
- What should I optimize next?

## Product Vision
Build the operating system for marketplace intelligence.

## MVP Objectives
### Financial Intelligence
Inputs:
- Orders CSV
- Settlement CSV
- Bank Statements
- GST Exports

Outputs:
- Reconciliation Reports
- Audit Reports
- Mismatch Reports

### Listing Intelligence
Inputs:
- Seller ASIN
- Competitor ASINs

Outputs:
- Listing Score
- Keyword Gap Analysis
- Competitor Benchmarking

## User Roles
- Seller
- Accountant
- Agency
- Workspace Admin
- Platform Admin

## Technology Stack
Backend:
- Laravel 12
- PHP 8.4

Frontend:
- Vue 3
- TypeScript
- Pinia

Database:
- PostgreSQL
- pgvector

Infrastructure:
- Redis
- Horizon
- Docker
- MinIO

AI Providers:
- Claude
- OpenAI
- Gemini
- Groq
- Ollama

## Architecture
Modular Monolith

Modules:
- Identity
- Workspace
- Imports
- Finance
- Products
- Competitors
- AI
- Reports
- Admin

## Universal Import Engine
Features:
- CSV Import
- XLSX Import
- Auto Mapping
- Validation
- Queue Processing

## Financial Reconciliation Engine
Flow:
Orders -> Settlements -> Bank Credits

Reports:
- Missing Settlements
- Missing Credits
- Refund Impact
- Return Impact

## Product Intelligence Engine
Analyze:
- Titles
- Bullets
- Descriptions
- Reviews

Generate:
- Listing Scores
- Optimization Recommendations

## Competitor Intelligence
Compare:
- Keywords
- Features
- Pricing
- Reviews

Generate:
- Benchmark Reports
- Gap Analysis

## RAG Architecture
Embed:
- Products
- Reviews
- Policies
- Insights

Do Not Embed:
- Orders
- Settlements
- Transactions

## AI Copilot
Supports:
- Financial Analysis
- Listing Analysis
- Competitor Analysis
- Seller Guidance

## Core Database Domains
- users
- workspaces
- imports
- orders
- settlements
- bank_transactions
- products
- competitors
- reviews
- embeddings
- reports

## Security
- RBAC
- Tenant Isolation
- Audit Logs
- Encryption

## API Structure
/api/v1/auth
/api/v1/workspaces
/api/v1/imports
/api/v1/reconciliation
/api/v1/products
/api/v1/competitors
/api/v1/reports
/api/v1/ai

## Roadmap
Sprint 1: Foundation
Sprint 2: Imports
Sprint 3: Finance
Sprint 4: Reconciliation
Sprint 5: Listings
Sprint 6: Competitors
Sprint 7: RAG
Sprint 8: AI Copilot
Sprint 9: Reports
Sprint 10: Hardening

## Claude Development Rules
- Thin Controllers
- Service Layer
- Repository Pattern
- DTOs
- Events
- Queues
- Feature Tests

## Future Roadmap
- Amazon SP-API
- Ads Intelligence
- Inventory Forecasting
- Multi Marketplace Support
