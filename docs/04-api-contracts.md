# API Contracts

Base URL: `/api/v1`
Content-Type: `application/json`
Auth: Laravel Sanctum session cookie (`XSRF-TOKEN` header for all non-GET requests)

---

## Standard Response Envelope

### Success
```json
{
  "data": { ... },
  "meta": { "page": 1, "per_page": 20, "total": 145 }
}
```

### Error
```json
{
  "message": "Human-readable error message",
  "errors": {
    "field_name": ["Validation error detail"]
  },
  "code": "ERROR_CODE"
}
```

### HTTP Status Codes
| Code | Meaning |
|------|---------|
| 200 | Success |
| 201 | Created |
| 202 | Accepted (queued job) |
| 400 | Bad request / validation error |
| 401 | Unauthenticated |
| 403 | Unauthorized (wrong role) |
| 404 | Not found |
| 409 | Conflict (duplicate) |
| 422 | Unprocessable entity |
| 429 | Rate limited |
| 500 | Server error |

---

## Auth

### POST /api/v1/auth/register
```json
// Request
{
  "name": "Ravi Kumar",
  "email": "ravi@example.com",
  "password": "secret123",
  "password_confirmation": "secret123"
}

// Response 201
{
  "data": {
    "user": { "id": 1, "name": "Ravi Kumar", "email": "ravi@example.com", "role": "seller" },
    "workspace": { "id": 1, "name": "Ravi's Store", "slug": "ravis-store" }
  }
}
```

### POST /api/v1/auth/login
```json
// Request
{ "email": "ravi@example.com", "password": "secret123" }

// Response 200
{
  "data": {
    "user": { "id": 1, "name": "Ravi Kumar", "email": "ravi@example.com", "role": "seller" }
  }
}
// Sets session cookie
```

### POST /api/v1/auth/logout
```
// Response 204 No Content
```

### GET /api/v1/auth/me
```json
// Response 200
{
  "data": {
    "id": 1,
    "name": "Ravi Kumar",
    "email": "ravi@example.com",
    "role": "seller",
    "workspace": { "id": 1, "name": "Ravi's Store" }
  }
}
```

### POST /api/v1/auth/forgot-password
```json
// Request
{ "email": "ravi@example.com" }
// Response 200 { "message": "Reset link sent" }
```

### POST /api/v1/auth/reset-password
```json
// Request
{ "token": "...", "email": "...", "password": "...", "password_confirmation": "..." }
// Response 200 { "message": "Password reset successfully" }
```

---

## Workspaces

### GET /api/v1/workspaces
```json
// Response 200
{
  "data": [
    { "id": 1, "name": "Ravi's Store", "slug": "ravis-store", "marketplace": "IN", "currency": "INR" }
  ]
}
```

### POST /api/v1/workspaces
```json
// Request
{ "name": "My Brand", "marketplace": "IN", "currency": "INR" }
// Response 201 { "data": { "id": 2, ... } }
```

### GET /api/v1/workspaces/{id}
```json
// Response 200
{
  "data": {
    "id": 1,
    "name": "Ravi's Store",
    "marketplace": "IN",
    "currency": "INR",
    "settings": {},
    "members": [
      { "user_id": 1, "name": "Ravi Kumar", "role": "owner" }
    ]
  }
}
```

### PUT /api/v1/workspaces/{id}
```json
// Request
{ "name": "Updated Name", "marketplace": "US" }
// Response 200 { "data": { ... updated workspace ... } }
```

---

## Imports

### POST /api/v1/imports/upload
```
// Multipart form-data
// Fields:
//   workspace_id: integer (required)
//   type: string (required) — orders|settlements|bank_statement|gst_report|products|competitors_csv
//   file: file (required, max 50MB, accept .csv .xlsx)

// Response 202
{
  "data": {
    "import_batch_id": 42,
    "status": "pending",
    "detected_columns": ["amazon-order-id", "purchase-date", ...],
    "suggested_mapping": {
      "amazon_order_id": "amazon-order-id",
      "purchase_date": "purchase-date"
    },
    "requires_mapping_confirmation": false
  }
}
```

### POST /api/v1/imports/{id}/confirm-mapping
```json
// Request — user confirms or adjusts the column mapping
{
  "mapping": {
    "amazon_order_id": "order id",
    "purchase_date": "date",
    "item_price": "selling price"
  }
}
// Response 202
{ "data": { "import_batch_id": 42, "status": "processing" } }
```

### GET /api/v1/imports/{id}/status
```json
// Response 200 (poll every 3s while status=processing)
{
  "data": {
    "id": 42,
    "type": "orders",
    "status": "processing",
    "total_rows": 1500,
    "processed_rows": 720,
    "failed_rows": 3,
    "percent": 48,
    "started_at": "2026-06-01T10:00:00Z",
    "completed_at": null
  }
}
```

### GET /api/v1/imports/{id}/errors
```json
// Response 200
{
  "data": [
    {
      "row_number": 34,
      "error_type": "invalid_format",
      "error_message": "purchase_date '2024-13-01' is not a valid date",
      "raw_data": { "amazon-order-id": "403-1234567-1234567", ... }
    }
  ],
  "meta": { "page": 1, "per_page": 50, "total": 3 }
}
```

### GET /api/v1/workspaces/{workspace_id}/imports
```json
// Query: ?type=orders&status=completed&page=1
// Response 200
{
  "data": [
    {
      "id": 42,
      "type": "orders",
      "original_filename": "orders_jan2024.csv",
      "status": "completed",
      "total_rows": 1500,
      "failed_rows": 3,
      "created_at": "2026-06-01T10:00:00Z"
    }
  ],
  "meta": { "page": 1, "per_page": 20, "total": 8 }
}
```

### POST /api/v1/imports/competitors/html
```json
// Request — paste raw HTML from Amazon product page
{
  "workspace_id": 1,
  "product_id": 15,   // our product to compare against
  "html_content": "<html>...</html>",
  "asin": "B09XYZ1234"   // optional hint
}
// Response 202
{
  "data": {
    "import_batch_id": 55,
    "status": "processing",
    "detected_asin": "B09XYZ1234"
  }
}
```

---

## Finance

### GET /api/v1/workspaces/{workspace_id}/orders
```json
// Query: ?page=1&per_page=50&status=Shipped&date_from=2024-01-01&date_to=2024-01-31
//         &search=403-123
// Response 200
{
  "data": [
    {
      "id": 1,
      "amazon_order_id": "403-1234567-1234567",
      "purchase_date": "2024-01-15",
      "order_status": "Shipped",
      "sku": "SKU-001",
      "asin": "B09XXXXXX",
      "product_name": "Blue Ceramic Mug",
      "quantity": 2,
      "item_price": 599.00,
      "item_tax": 107.82,
      "fulfillment_channel": "AFN",
      "currency": "INR"
    }
  ],
  "meta": { "page": 1, "per_page": 50, "total": 1497 }
}
```

### GET /api/v1/workspaces/{workspace_id}/orders/summary
```json
// Query: ?date_from=2024-01-01&date_to=2024-01-31
// Response 200
{
  "data": {
    "total_orders": 1497,
    "total_revenue": 893421.50,
    "total_tax": 160814.67,
    "by_status": { "Shipped": 1420, "Cancelled": 45, "Pending": 32 },
    "by_fulfillment": { "AFN": 1200, "MFN": 297 }
  }
}
```

### GET /api/v1/workspaces/{workspace_id}/settlements
```json
// Query: ?settlement_id=12345&date_from=...
// Response 200 — same pagination envelope
```

### GET /api/v1/workspaces/{workspace_id}/bank-transactions
```json
// Query: ?date_from=...&date_to=...&type=credit
// Response 200 — same pagination envelope
```

---

## Reconciliation

### POST /api/v1/workspaces/{workspace_id}/reconciliation/run
```json
// Request
{
  "period_start": "2024-01-01",
  "period_end": "2024-01-31"
}
// Response 202
{
  "data": {
    "reconciliation_run_id": 7,
    "status": "pending"
  }
}
```

### GET /api/v1/workspaces/{workspace_id}/reconciliation/{run_id}
```json
// Response 200
{
  "data": {
    "id": 7,
    "period_start": "2024-01-01",
    "period_end": "2024-01-31",
    "status": "completed",
    "summary": {
      "total_orders": 1497,
      "matched_orders": 1384,
      "unmatched_orders": 113,
      "total_order_value": 893421.50,
      "total_settled": 821340.00,
      "total_bank_credits": 819800.00,
      "settlement_gap": 1540.00,
      "missing_credits_count": 2
    },
    "completed_at": "2026-06-01T10:15:00Z"
  }
}
```

### GET /api/v1/workspaces/{workspace_id}/reconciliation/{run_id}/reports
```json
// Response 200 — list of available reports for this run
{
  "data": [
    { "type": "missing_settlements", "count": 113 },
    { "type": "missing_credits", "count": 2 },
    { "type": "refund_impact", "total_refund_value": 12400.00 },
    { "type": "gst_mismatch", "mismatch_count": 8 }
  ]
}
```

### GET /api/v1/workspaces/{workspace_id}/reconciliation/{run_id}/reports/{type}
```json
// type: missing_settlements | missing_credits | refund_impact | return_impact | gst_mismatch
// Query: ?page=1&export=false
// Response 200
{
  "data": {
    "report_type": "missing_settlements",
    "generated_at": "2026-06-01T10:15:00Z",
    "rows": [
      {
        "amazon_order_id": "403-9876543-7654321",
        "order_date": "2024-01-05",
        "order_value": 1199.00,
        "sku": "SKU-002",
        "days_since_order": 26,
        "likely_reason": "Order may be pending settlement in next cycle"
      }
    ]
  },
  "meta": { "page": 1, "per_page": 50, "total": 113 }
}
```

### POST /api/v1/workspaces/{workspace_id}/reconciliation/{run_id}/reports/{type}/export
```json
// Request
{ "format": "xlsx" }   // xlsx | pdf | csv
// Response 202
{
  "data": { "report_id": 23, "status": "generating" }
}
```

### GET /api/v1/workspaces/{workspace_id}/reports/{id}/download
```
// Response: file download (PDF/XLSX stream) or redirect to MinIO presigned URL
```

---

## Products

### GET /api/v1/workspaces/{workspace_id}/products
```json
// Query: ?page=1&sort=listing_score&order=desc
// Response 200
{
  "data": [
    {
      "id": 1,
      "asin": "B09XXXXXX",
      "sku": "SKU-001",
      "title": "Blue Ceramic Mug 350ml",
      "brand": "MugCo",
      "listing_score": 72,
      "rating": 4.2,
      "review_count": 128,
      "last_analyzed_at": "2026-06-01T09:00:00Z"
    }
  ],
  "meta": { "page": 1, "per_page": 20, "total": 45 }
}
```

### GET /api/v1/workspaces/{workspace_id}/products/{id}
```json
// Response 200
{
  "data": {
    "id": 1,
    "asin": "B09XXXXXX",
    "title": "Blue Ceramic Mug 350ml",
    "brand": "MugCo",
    "category": "Kitchen & Dining",
    "bullet_1": "...", "bullet_2": "...", "bullet_3": "...",
    "bullet_4": "...", "bullet_5": "...",
    "description": "...",
    "listing_score": 72,
    "score_breakdown": {
      "title": { "score": 22, "max": 25, "issues": ["Missing primary keyword in first 80 chars"] },
      "bullets": { "score": 18, "max": 25, "issues": ["Bullet 4 is under 80 characters"] },
      "description": { "score": 14, "max": 20, "issues": [] },
      "reviews": { "score": 12, "max": 15, "issues": [] },
      "keywords": { "score": 6, "max": 15, "issues": ["Missing 12 competitor keywords"] }
    },
    "top_keywords": [
      { "keyword": "ceramic mug", "frequency": 3, "source": "title" }
    ],
    "competitors_count": 3
  }
}
```

### POST /api/v1/workspaces/{workspace_id}/products/{id}/analyze
```json
// Trigger re-analysis
// Response 202
{ "data": { "product_id": 1, "status": "queued" } }
```

### GET /api/v1/workspaces/{workspace_id}/products/{id}/optimization
```json
// Response 200
{
  "data": {
    "suggestions": [
      {
        "field": "title",
        "priority": "high",
        "issue": "Primary keyword 'ceramic mug gift' not in title",
        "suggestion": "Add 'Ceramic Mug Gift' within the first 80 characters",
        "expected_impact": "Title score +8 points"
      }
    ],
    "ai_rewrite": {
      "title": "Blue Ceramic Mug 350ml | Gift for Coffee Lovers | Dishwasher Safe",
      "bullet_1": "..."
    }
  }
}
```

---

## Competitors

### GET /api/v1/workspaces/{workspace_id}/products/{product_id}/competitors
```json
// Response 200
{
  "data": [
    {
      "id": 1,
      "asin": "B09YYYYYYY",
      "title": "Premium Ceramic Coffee Mug",
      "brand": "CafeCo",
      "price": 649.00,
      "rating": 4.5,
      "review_count": 2341,
      "source_type": "html"
    }
  ]
}
```

### GET /api/v1/workspaces/{workspace_id}/products/{product_id}/keyword-gaps
```json
// Query: ?gap_type=missing&sort=priority_score
// Response 200
{
  "data": [
    {
      "keyword": "microwave safe mug",
      "gap_type": "missing",
      "our_frequency": 0,
      "their_frequency": 4,
      "priority_score": 87,
      "found_in": ["bullet_2", "description"]
    }
  ]
}
```

### GET /api/v1/workspaces/{workspace_id}/products/{product_id}/benchmark
```json
// Response 200
{
  "data": {
    "our_listing_score": 72,
    "avg_competitor_score": 81,
    "competitors": [
      {
        "asin": "B09YYYYYYY",
        "listing_score": 85,
        "price": 649.00,
        "rating": 4.5,
        "review_count": 2341,
        "keyword_overlap": 34,
        "unique_keywords": 18
      }
    ],
    "gaps_summary": {
      "missing_keywords": 18,
      "underused_keywords": 12,
      "our_advantages": 5
    }
  }
}
```

---

## AI Copilot

### GET /api/v1/workspaces/{workspace_id}/ai/conversations
```json
// Response 200
{
  "data": [
    {
      "id": 1,
      "title": "January reconciliation query",
      "context_type": "financial",
      "last_message_at": "2026-06-01T11:00:00Z"
    }
  ]
}
```

### POST /api/v1/workspaces/{workspace_id}/ai/conversations
```json
// Request
{
  "title": "Listing analysis for B09XXXXXX",
  "context_type": "listing",
  "context_id": 1   // product_id
}
// Response 201 { "data": { "id": 5, "title": "...", ... } }
```

### POST /api/v1/workspaces/{workspace_id}/ai/conversations/{id}/messages
```json
// Request
{
  "message": "Why does my reconciliation show 113 unmatched orders?"
}
// Response 200
{
  "data": {
    "role": "assistant",
    "content": "Based on your January 2024 reconciliation, the 113 unmatched orders break down as follows: ...",
    "rag_sources": [
      { "type": "reconciliation_summary", "id": 7 }
    ],
    "provider": "claude",
    "model": "claude-sonnet-4-5"
  }
}
```

### GET /api/v1/workspaces/{workspace_id}/ai/conversations/{id}/messages
```json
// Response 200 — full conversation history
{
  "data": [
    { "id": 1, "role": "user", "content": "...", "created_at": "..." },
    { "id": 2, "role": "assistant", "content": "...", "created_at": "..." }
  ]
}
```

---

## Reports

### GET /api/v1/workspaces/{workspace_id}/reports
```json
// Query: ?type=listing_analysis&status=completed
// Response 200 — paginated list of reports
```

### GET /api/v1/workspaces/{workspace_id}/reports/{id}/download
```
// Streams PDF/XLSX or returns presigned MinIO URL
```

---

## Admin (Platform Admin only)

### GET /api/v1/admin/users
### PUT /api/v1/admin/users/{id}
### GET /api/v1/admin/workspaces
### DELETE /api/v1/admin/workspaces/{id}
### GET /api/v1/admin/imports   (all imports across all workspaces)
### GET /api/v1/admin/metrics   (platform usage stats)

---

## Rate Limiting

| Endpoint Group | Limit |
|---------------|-------|
| Auth endpoints | 10 req/min per IP |
| Import upload | 5 req/min per workspace |
| AI messages | 30 req/min per workspace |
| All other APIs | 120 req/min per user |

---

## Pagination

All list endpoints accept:
- `page` (integer, default 1)
- `per_page` (integer, default 20, max 100)

Response includes `meta.page`, `meta.per_page`, `meta.total`.
