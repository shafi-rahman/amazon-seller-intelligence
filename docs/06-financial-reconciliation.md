# Financial Reconciliation Engine

Answers: **"Where did my money go?"**

The engine matches Amazon Orders → Amazon Settlements → Bank Credits in a two-pass pipeline, then generates actionable mismatch reports.

---

## The Three Data Layers

```
Layer 1: ORDERS
  What the customer paid. Source: Amazon Orders Report.
  Key fields: amazon_order_id, purchase_date, item_price, quantity

Layer 2: SETTLEMENTS
  What Amazon says it paid you. Source: Amazon Settlement Report.
  Key fields: settlement_id, deposit_date, deposited_amount, order_id, amount, amount_type

Layer 3: BANK CREDITS
  What actually hit your bank account. Source: Bank Statement CSV.
  Key fields: transaction_date, credit_amount, description (contains settlement_id or UTR)
```

---

## Reconciliation Algorithm

### Step 1: Orders → Settlements Matching

For each order in the date range, find matching settlement rows.

```
Pass A — Exact Match:
  Match orders.amazon_order_id = settlements.order_id
  AND settlements.transaction_type IN ('Order', 'Shipment', 'ItemPrice')
  Status: matched (confidence: 100)

Pass B — Fuzzy Match (for Cancelled/Returned orders):
  Match orders where order_status = 'Cancelled'
  to settlements where transaction_type = 'Refund'
  AND ABS(settlements.amount) ≈ orders.item_price (tolerance ±1%)
  AND settlements.posted_date BETWEEN orders.purchase_date AND orders.purchase_date + 30 days
  Status: matched (confidence: 85)

Unmatched:
  Orders with no settlement match after both passes.
  Classify reason:
    - purchase_date within 14 days of period_end → "Pending settlement"
    - order_status = 'Cancelled' + no refund settlement → "Refund settlement missing"
    - else → "Settlement missing"
```

### Step 2: Settlement Cycles → Bank Credits

Amazon aggregates all settlements into a single bank transfer per settlement cycle.

```
For each unique (settlement_id, deposit_date, deposited_amount):

Pass A — Exact Match:
  Find bank_transactions where:
    credit_amount = settlements.deposited_amount (exact)
    AND transaction_date BETWEEN deposit_date - 3 AND deposit_date + 3
    AND (description ILIKE '%' || settlement_id || '%'
         OR description ILIKE '%amazon%')
  Status: matched (confidence: 100)

Pass B — Amount Match (description doesn't contain settlement_id):
  Find bank_transactions where:
    credit_amount = settlements.deposited_amount (exact)
    AND transaction_date BETWEEN deposit_date - 5 AND deposit_date + 5
    AND description ILIKE '%amazon%'
  Status: matched (confidence: 80)

Pass C — Tolerance Match (bank may show slightly different amount due to TDS):
  Find bank_transactions where:
    ABS(credit_amount - deposited_amount) <= deposited_amount * 0.02  (2% tolerance)
    AND transaction_date BETWEEN deposit_date - 5 AND deposit_date + 5
    AND description ILIKE '%amazon%'
  Status: partial (confidence: 70, mismatch_amount = credit_amount - deposited_amount)

Unmatched:
  Settlement cycles with no bank credit.
  Classify reason:
    - deposit_date within 5 days of today → "Credit may be in transit"
    - else → "Bank credit missing"
```

### Step 3: GST Reconciliation

For each order matched in Step 1:

```
  Find gst_transactions.order_id = orders.amazon_order_id
  Compare:
    Expected tax = orders.item_tax + orders.shipping_tax
    Reported tax = gst_transactions.igst_amount + cgst_amount + sgst_amount
    Mismatch threshold: ABS(expected - reported) > 1.00 INR
  Flag mismatches in gst_mismatch report
```

---

## Tolerance Rules

| Match Type | Amount Tolerance | Date Tolerance | Confidence |
|-----------|-----------------|----------------|------------|
| Order ↔ Settlement | Exact | — | 100 |
| Order ↔ Refund Settlement | ±1% | ±30 days | 85 |
| Settlement ↔ Bank (with ID in desc) | Exact | ±3 days | 100 |
| Settlement ↔ Bank (amount only) | Exact | ±5 days | 80 |
| Settlement ↔ Bank (TDS deducted) | ±2% | ±5 days | 70 |

---

## Report Types

### 1. Missing Settlements Report
Orders that have no corresponding settlement row.

```json
{
  "report_type": "missing_settlements",
  "period": { "start": "2024-01-01", "end": "2024-01-31" },
  "summary": {
    "total_unmatched_orders": 113,
    "total_unmatched_value": 67842.00,
    "by_reason": {
      "pending_settlement": 45,
      "refund_settlement_missing": 23,
      "settlement_missing": 45
    }
  },
  "rows": [
    {
      "amazon_order_id": "403-XXXXXXX-XXXXXXX",
      "order_date": "2024-01-05",
      "sku": "SKU-001",
      "order_value": 599.00,
      "order_status": "Shipped",
      "days_since_order": 26,
      "reason": "settlement_missing",
      "action": "Raise dispute with Amazon Seller Support"
    }
  ]
}
```

### 2. Missing Credits Report
Settlement cycles where no corresponding bank credit was found.

```json
{
  "report_type": "missing_credits",
  "rows": [
    {
      "settlement_id": "12345678",
      "deposit_date": "2024-01-15",
      "deposited_amount": 45230.00,
      "reason": "bank_credit_missing",
      "action": "Check bank statement for Jan 15-20. UTR may be delayed."
    }
  ]
}
```

### 3. Refund Impact Report
Shows how refunds reduced your net payout.

```json
{
  "report_type": "refund_impact",
  "summary": {
    "total_refunds": 23,
    "total_refund_value": 12400.00,
    "net_impact_on_settlement": -11800.00
  },
  "rows": [
    {
      "amazon_order_id": "...",
      "refund_date": "2024-01-18",
      "original_order_value": 1199.00,
      "refunded_amount": 1199.00,
      "settlement_deduction": 1139.05,
      "amazon_refund_fee": 59.95
    }
  ]
}
```

### 4. Return Impact Report
Orders marked as returned and their effect on inventory + revenue.

```json
{
  "report_type": "return_impact",
  "summary": {
    "total_returns": 18,
    "total_return_value": 9800.00,
    "fba_reimbursed": 15,
    "not_reimbursed": 3
  }
}
```

### 5. GST Mismatch Report
Orders where the GST in the settlement/report doesn't match the tax on the order.

```json
{
  "report_type": "gst_mismatch",
  "rows": [
    {
      "order_id": "403-...",
      "invoice_number": "IN-2024-001234",
      "expected_tax": 107.82,
      "reported_tax": 105.00,
      "mismatch_amount": 2.82,
      "tax_type": "IGST",
      "action": "Verify invoice against GSTR-1 filing"
    }
  ]
}
```

### 6. Reconciliation Summary Report
High-level dashboard numbers for the period.

```json
{
  "report_type": "summary",
  "period": "January 2024",
  "financials": {
    "total_orders": 1497,
    "total_order_value": 893421.50,
    "total_settled": 821340.00,
    "total_bank_credits": 819800.00,
    "settlement_rate_pct": 92.4,
    "unaccounted_value": 1540.00
  },
  "match_rates": {
    "orders_to_settlements_pct": 92.4,
    "settlements_to_bank_pct": 99.8
  },
  "tax": {
    "total_gst_collected": 160814.67,
    "total_gst_reported": 160740.00,
    "gst_mismatch_count": 8
  }
}
```

---

## Reconciliation Run Lifecycle

```
Status: pending
  → ReconciliationJob picked up
Status: running
  → Step 1 in progress (orders ↔ settlements)
  → Step 2 in progress (settlements ↔ bank)
  → Step 3 in progress (GST check)
  → Reports generated and saved
Status: completed
  → ReconciliationCompletedEvent dispatched
  → User notified (in-app notification)

On failure:
Status: failed
  → Error stored in reconciliation_runs.summary
  → Partial results preserved in reconciliation_matches
```

---

## Re-running Reconciliation

Running reconciliation for the same period again:
- Creates a new `reconciliation_runs` record
- Previous matches for that period are **not deleted** (historical record)
- Previous reports are superseded but preserved
- User can compare runs if they uploaded additional data between runs

---

## Business Rules

1. **Settlement cycle vs single order**: Amazon settles all orders in a 2-week cycle as a single bank transfer. One bank credit corresponds to potentially hundreds of orders.

2. **FBA fees**: Amazon deducts FBA fees from the settlement before paying. The `settlements` table contains negative `amount` rows for fees (`amount_type = 'ItemFees'`). These are included in the `deposited_amount` calculation.

3. **TDS deduction**: Amazon deducts 1% TDS (for sellers with turnover > ₹10L/year). This causes `credit_amount ≠ deposited_amount`. The ±2% tolerance in Pass C handles this.

4. **Split shipments**: One order can have multiple settlement rows (one per shipment). The engine sums all settlement amounts for an order_id.

5. **Marketplace fee structure (India)**:
   - Referral fee: 2–45% of item price (varies by category)
   - FBA fulfillment fee: per unit weight/size slab
   - Closing fee: fixed per category
   - The reconciliation engine **does not validate fees** — that's a future feature.
