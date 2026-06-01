# Import Engine

The Universal Import Engine handles all data entry into the platform. No API integrations exist in Phase 1 — everything comes through file uploads or HTML paste.

---

## Supported Import Types

| Type | Input | Format |
|------|-------|--------|
| `orders` | Amazon Orders Report | CSV |
| `settlements` | Amazon Settlement Report | CSV (tab-separated) |
| `bank_statement` | Bank Statement | CSV (multiple bank formats) |
| `gst_report` | Amazon GST Report | CSV |
| `products` | Product listing data | CSV |
| `competitors_csv` | Competitor data | CSV |
| `competitors_html` | Amazon product page | HTML pasted into textarea |

---

## Import Flow

```
1. User uploads file or pastes HTML via frontend
2. API stores file to MinIO (raw, unmodified)
3. import_batches record created (status: pending)
4. DetectColumnsJob dispatched (fast, sync-ish)
   → reads first 10 rows
   → detects column names
   → suggests mapping
   → returns to frontend for user confirmation
5. User confirms or adjusts mapping
6. POST /imports/{id}/confirm-mapping
7. ProcessImportJob dispatched to `imports` queue
8. Job reads CSV in chunks of 500 rows
   → maps columns
   → validates each row
   → upserts to DB
   → updates processed_rows counter
   → logs errors to import_errors
9. ImportCompletedEvent dispatched on success
```

---

## Amazon Orders Report

### Source
Download from Seller Central: Reports → Order Reports → All Orders

### Format
Tab-separated CSV (`.txt` or `.csv`), UTF-8

### Column Mapping

| Amazon Column Header | DB Column | Required | Notes |
|---------------------|-----------|----------|-------|
| `amazon-order-id` | `amazon_order_id` | YES | Format: `403-XXXXXXX-XXXXXXX` |
| `merchant-order-id` | `merchant_order_id` | No | |
| `purchase-date` | `purchase_date` | YES | ISO 8601 or `YYYY-MM-DD HH:MM:SS` |
| `last-updated-date` | `last_updated_date` | No | |
| `order-status` | `order_status` | YES | Shipped/Unshipped/Cancelled/Pending |
| `fulfillment-channel` | `fulfillment_channel` | No | AFN=FBA, MFN=Self-ship |
| `sales-channel` | `sales_channel` | No | Amazon.in, Amazon.com, etc. |
| `ship-service-level` | `ship_service_level` | No | |
| `product-name` | `product_name` | No | |
| `sku` | `sku` | No | |
| `asin` | `asin` | No | |
| `item-status` | `item_status` | No | |
| `quantity` | `quantity` | No | Default 1 |
| `currency` | `currency` | No | Default INR |
| `item-price` | `item_price` | No | |
| `item-tax` | `item_tax` | No | |
| `shipping-price` | `shipping_price` | No | |
| `shipping-tax` | `shipping_tax` | No | |
| `gift-wrap-price` | `gift_wrap_price` | No | |
| `gift-wrap-tax` | `gift_wrap_tax` | No | |
| `item-promotion-discount` | `item_promotion_discount` | No | |
| `ship-promotion-discount` | `ship_promotion_discount` | No | |
| `ship-city` | `ship_city` | No | |
| `ship-state` | `ship_state` | No | |
| `ship-postal-code` | `ship_postal_code` | No | |
| `ship-country` | `ship_country` | No | |
| `is-business-order` | `is_business_order` | No | true/false → boolean |

### Validation Rules
- `amazon_order_id` must match pattern `[0-9]{3}-[0-9]{7}-[0-9]{7}`
- `purchase_date` must parse to a valid date
- `quantity` must be positive integer
- All price fields must be non-negative numeric
- Duplicate `(workspace_id, amazon_order_id, sku)` → upsert (not error)

---

## Amazon Settlement Report

### Source
Download from Seller Central: Reports → Payments → All Statements → Download

### Format
Tab-separated, UTF-8. Has a header section (first 6 rows contain metadata) before the column header row.

### Header Detection
```
Row 1: Settlement ID\t{value}
Row 2: Settlement Start Date\t{value}
Row 3: Settlement End Date\t{value}
Row 4: Deposit Date\t{value}
Row 5: Total Amount\t{value}
Row 6: Currency\t{value}
Row 7: (blank)
Row 8: [column headers]
Row 9+: data rows
```

The import engine must detect the column header row by scanning for `"transaction-type"` in a row.

### Column Mapping

| Amazon Column | DB Column | Notes |
|--------------|-----------|-------|
| `settlement-id` | `settlement_id` | From header section |
| `settlement-start-date` | `settlement_start_date` | From header section |
| `settlement-end-date` | `settlement_end_date` | From header section |
| `deposit-date` | `deposit_date` | From header section |
| `total-amount` | `deposited_amount` | From header section |
| `currency` | `currency` | From header section |
| `transaction-type` | `transaction_type` | Per data row |
| `order-id` | `order_id` | |
| `merchant-order-id` | `merchant_order_id` | |
| `adjustment-id` | `adjustment_id` | |
| `shipment-id` | `shipment_id` | |
| `marketplace-name` | `marketplace_name` | |
| `amount-type` | `amount_type` | |
| `amount-description` | `amount_description` | |
| `amount` | `amount` | Can be negative (fees, refunds) |
| `fulfillment-id` | `fulfillment_id` | |
| `posted-date` | `posted_date` | |
| `posted-date-time` | `posted_datetime` | |
| `sku` | `sku` | |
| `quantity-purchased` | `quantity_purchased` | |

---

## Bank Statement

### Format
CSV, format varies by bank. The engine auto-detects by column scanning.

### Supported Bank Formats

**Format A — ICICI/HDFC/Axis style:**
```
Date, Description/Narration, Ref No./Cheque No., Debit, Credit, Balance
```

**Format B — SBI style:**
```
Txn Date, Value Date, Description, Ref No./Cheque No., Debit, Credit, Balance
```

**Format C — Generic:**
```
Transaction Date, Narration/Particulars, Amount (positive=credit, negative=debit), Balance
```

### Auto-Detection Logic
```
1. Read column headers (row 1, or first non-empty row)
2. Score each candidate column:
   - Date column: any column named date/txn date/transaction date/posting date
   - Description: narration/description/particulars/details
   - Debit: debit/dr/withdrawal
   - Credit: credit/cr/deposit
   - Amount: amount (will need sign detection)
   - Balance: balance/closing balance
3. If debit+credit not found but amount found → detect by sign
4. Present detected mapping to user for confirmation
```

### Reference/UTR Extraction
After import, run regex on description to extract Amazon settlement references:
```
Patterns:
  - /NEFT.*AMAZON/i → Amazon transfer
  - /AMAZON.*PAY/i → Amazon Pay credit
  - /UTR:?\s*([A-Z0-9]{22})/i → UTR number
  - /\bIN\d{12}\b/ → IMPS reference
  - Settlement ID pattern: /\b\d{9,13}\b/ (matched against settlements.settlement_id)
```

---

## GST Report

### Source
Amazon Seller Central: Reports → Tax Document Library → Download GSTR reports

### Format
CSV, UTF-8

### Column Mapping

| Amazon Column | DB Column |
|--------------|-----------|
| `Transaction Type` | `transaction_type` |
| `Invoice Date` | `invoice_date` |
| `Invoice Number` | `invoice_number` |
| `Order Id` | `order_id` |
| `Transaction Id` | `transaction_id` |
| `ASIN` | `asin` |
| `Seller SKU` | `sku` |
| `Item Description` | `product_name` |
| `Qty` | `quantity` |
| `Ship From State` | `ship_from_state` |
| `Ship To State` | `ship_to_state` |
| `Taxable Value (₹)` | `taxable_value` |
| `IGST Rate` | `igst_rate` |
| `IGST Amount (₹)` | `igst_amount` |
| `CGST Rate` | `cgst_rate` |
| `CGST Amount (₹)` | `cgst_amount` |
| `SGST/UTGST Rate` | `sgst_rate` |
| `SGST/UTGST Amount (₹)` | `sgst_amount` |
| `Compensatory Cess Rate` | `cess_rate` |
| `Compensatory Cess Amount (₹)` | `cess_amount` |
| `Invoice Amount (₹)` | `invoice_amount` |
| `IRN` | `irn` |
| `HSN/SAC` | `hsn_sac` |

---

## Products CSV

### Purpose
Upload your own product listings for scoring and analysis.

### Required Columns
| Column | Required | Notes |
|--------|----------|-------|
| `asin` | YES | |
| `sku` | No | |
| `title` | YES | |
| `brand` | No | |
| `category` | No | |
| `bullet_1` through `bullet_5` | No | |
| `description` | No | |
| `price` | No | |
| `rating` | No | |
| `review_count` | No | |

### Auto-Mapping
The engine normalizes common variations:
- `ASIN` / `asin` / `product_id` → `asin`
- `Product Title` / `listing_title` / `name` → `title`
- `Feature 1-5` / `key_feature_1-5` → `bullet_1-5`
- `Product Description` / `desc` → `description`

---

## Competitors CSV

### Same columns as Products CSV with one additional field:
| Column | Notes |
|--------|-------|
| `competitor_asin` | The competitor's ASIN |

The `product_id` field links the competitor to your product. If not provided, user selects the product after upload.

---

## Competitors HTML (Textarea Paste)

### User Flow
1. User opens Amazon product page in browser
2. Right-click → View Page Source (Ctrl+U)
3. Select All → Copy
4. Paste into ASIP textarea
5. Submit

### HTML Parser — Extraction Targets

The parser uses Symfony DomCrawler with CSS selectors to extract:

```php
Extractors [
  'asin'         => '#ASIN' (hidden input) | URL pattern /dp/([A-Z0-9]{10})/
  'title'        => '#productTitle'
  'brand'        => '#bylineInfo' | '.po-brand .po-break-word'
  'price'        => '.a-price .a-offscreen' | '#priceblock_ourprice'
  'rating'       => '.a-icon-alt' (extract float from "4.3 out of 5 stars")
  'review_count' => '#acrCustomerReviewText' (extract int from "2,341 ratings")
  'bullets'      => '#feature-bullets ul li span:not(.aok-hidden)' (max 5)
  'description'  => '#productDescription p' | '#aplus .aplus-module'
  'category'     => '#wayfinding-breadcrumbs_feature_div a'
]
```

### Parser Confidence
Each extracted field gets a confidence score (0–100). Fields with confidence < 50 are flagged for user review before saving.

### Fallback for Parse Failures
If DomCrawler fails to extract ASIN or title (confidence = 0), the batch is marked `failed` with error: `"Could not detect Amazon product page structure. Please ensure you copied the full page source."`

---

## File Size & Performance Limits

| Import Type | Max File Size | Expected Rows | Processing Time |
|-------------|--------------|---------------|-----------------|
| Orders CSV | 50 MB | up to 100,000 | ~2 min |
| Settlements CSV | 20 MB | up to 50,000 | ~1 min |
| Bank Statement | 5 MB | up to 5,000 | <30s |
| GST Report | 10 MB | up to 20,000 | <1 min |
| Products CSV | 5 MB | up to 10,000 | <30s |
| Competitors CSV | 2 MB | up to 1,000 | <15s |
| Competitors HTML | 2 MB (text) | 1 per paste | <10s |

---

## Chunk Processing

Large files are processed in chunks to avoid memory exhaustion:

```php
// In ProcessImportJob
$reader = Reader::createFromPath($filePath);
$reader->setHeaderOffset(0);

foreach ($reader->getRecords() as $offset => $record) {
    $batch[] = $this->mapRow($record);
    
    if (count($batch) === 500) {
        $this->upsertBatch($batch);
        $this->updateProgress($offset);
        $batch = [];
    }
}
// flush remaining
if (!empty($batch)) {
    $this->upsertBatch($batch);
}
```

---

## Duplicate Handling

| Table | Duplicate Strategy |
|-------|-------------------|
| orders | UPSERT on `(workspace_id, amazon_order_id, sku)` |
| settlements | INSERT, skip on duplicate `(workspace_id, settlement_id, order_id, amount_type, amount_description)` |
| bank_transactions | INSERT, flag probable duplicates (same date+amount+description within 1 day) |
| gst_transactions | UPSERT on `(workspace_id, invoice_number, transaction_id)` |
| products | UPSERT on `(workspace_id, asin)` |
| competitors | UPSERT on `(workspace_id, product_id, asin)` |

---

## Post-Import Events

| Import Type | Event Dispatched | Listener Actions |
|-------------|-----------------|-----------------|
| orders | `OrdersImportCompleted` | Trigger reconciliation suggestions |
| settlements | `SettlementsImportCompleted` | Trigger reconciliation suggestions |
| bank_statement | `BankStatementImportCompleted` | Extract Amazon references, trigger recon |
| gst_report | `GstImportCompleted` | Link to orders by order_id |
| products | `ProductsImportCompleted` | Trigger listing score analysis job |
| competitors_csv | `CompetitorsImportCompleted` | Trigger keyword extraction + gap analysis |
| competitors_html | `CompetitorHtmlImportCompleted` | Trigger HTML parsing + keyword extraction |
