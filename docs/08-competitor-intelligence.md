# Competitor Intelligence

Compares your product listings against competitors to identify keyword gaps, pricing opportunities, and listing weaknesses.

---

## Data Entry Methods

### Method 1: Competitors CSV
Upload a CSV with competitor ASIN data in the same format as the products CSV.
See [Import Engine — Competitors CSV](./05-import-engine.md#competitors-csv).

### Method 2: HTML Source Paste
1. Open the competitor's Amazon product page in a browser
2. Press `Ctrl+U` (View Source) or right-click → View Page Source
3. Select All (`Ctrl+A`) → Copy (`Ctrl+C`)
4. Paste into ASIP's HTML textarea
5. System parses the HTML and extracts product data

This method works on any Amazon marketplace. No browser extension or scraper needed.

---

## HTML Parser

### Technology
Symfony DomCrawler + CssSelector

### Extraction Selectors

```php
[
    'asin' => [
        // Method 1: hidden input (most reliable)
        'selector' => 'input#ASIN',
        'attribute' => 'value',
        // Method 2: URL in canonical link
        'fallback' => 'link[rel="canonical"]',
        'fallback_attr' => 'href',
        'fallback_pattern' => '/\/dp\/([A-Z0-9]{10})/',
    ],
    'title' => [
        'selector' => '#productTitle',
        'fallback' => 'h1.a-size-large',
        'trim' => true,
    ],
    'brand' => [
        'selector' => '#bylineInfo',
        'fallback' => '.po-brand .po-break-word',
        'strip_prefix' => ['Brand: ', 'Visit the ', ' Store'],
    ],
    'price' => [
        'selector' => '.a-price.aok-align-center .a-offscreen',
        'fallback' => '#priceblock_ourprice',
        'fallback2' => '.a-price .a-offscreen',
        'extract_number' => true,  // strip ₹ and commas
    ],
    'rating' => [
        'selector' => '#acrPopover .a-icon-alt',
        'fallback' => '[data-hook="rating-out-of-text"]',
        'extract_pattern' => '/^([\d.]+)/',  // "4.3 out of 5 stars" → 4.3
    ],
    'review_count' => [
        'selector' => '#acrCustomerReviewText',
        'extract_pattern' => '/^([\d,]+)/',  // "2,341 ratings" → 2341
        'remove_commas' => true,
    ],
    'bullets' => [
        'selector' => '#feature-bullets ul li span.a-list-item',
        'exclude' => '.aok-hidden',
        'max' => 5,
        'map' => 'trim',
    ],
    'description' => [
        'selector' => '#productDescription p',
        'fallback' => '#aplus .aplus-module-wrapper',
        'join' => "\n\n",
        'strip_tags' => true,
    ],
    'category' => [
        'selector' => '#wayfinding-breadcrumbs_feature_div a',
        'last_two' => true,   // "Electronics > Headphones" → take last node
    ],
]
```

### Parsing Confidence Scoring

After extraction, each field gets a confidence score:

| Confidence | Meaning |
|-----------|---------|
| 100 | Exact selector match, value looks valid |
| 75 | Fallback selector used, value looks valid |
| 50 | Value extracted but looks short/suspicious |
| 25 | Value extracted from pattern matching only |
| 0 | Field not found |

Fields with confidence < 60 are highlighted in the UI for user review before saving.

### Minimum Parse Requirements
A parse attempt is rejected entirely if:
- ASIN not found (confidence = 0)
- Title not found (confidence = 0)

Error shown: *"Could not detect Amazon product page structure. Make sure you copied the complete page source."*

---

## Keyword Gap Analysis

### Process

```
For each (our product, competitor) pair:

1. Get our product keywords (from product_keywords)
2. Get competitor keywords (from competitor_keywords)
3. For each competitor keyword:
   a. Find matching keyword in our set (exact or normalized match)
   b. If not found → gap_type = 'missing'
   c. If found but their_frequency > our_frequency * 1.5 → gap_type = 'underused'
4. For each of our keywords not in competitor → gap_type = 'advantage'
5. Calculate priority_score for each gap (see below)
6. Store in keyword_gaps table
```

### Keyword Normalization
Before comparison, normalize keywords:
- Lowercase
- Singularize common nouns: `mugs` → `mug`, `bottles` → `bottle`
- Remove stopwords
- Trim whitespace

### Priority Score Formula

```
priority_score = base_score + frequency_bonus + position_bonus

base_score:
  gap_type = 'missing'   → 60
  gap_type = 'underused' → 40
  gap_type = 'advantage' → 20

frequency_bonus:
  their_frequency >= 5   → +20
  their_frequency >= 3   → +10
  their_frequency >= 2   → +5

position_bonus:
  keyword appears in competitor's title → +15
  keyword appears in first 3 bullets   → +5
  keyword in description only          → +0

max priority_score = 95 (capped)
```

---

## Benchmark Report

### Metrics Compared Per Competitor

```json
{
  "our_product": {
    "asin": "B09XXXXXX",
    "listing_score": 72,
    "title_length": 145,
    "bullets_count": 5,
    "description_length": 1240,
    "price": 599.00,
    "rating": 4.2,
    "review_count": 128
  },
  "competitor": {
    "asin": "B09YYYYYYY",
    "listing_score": 85,
    "title_length": 198,
    "bullets_count": 5,
    "description_length": 2100,
    "price": 649.00,
    "rating": 4.5,
    "review_count": 2341
  },
  "deltas": {
    "listing_score": -13,
    "price": +50.00,
    "rating": -0.3,
    "review_count": -2213,
    "keyword_overlap": 34,
    "keywords_we_lack": 18,
    "keywords_we_have_they_dont": 5
  },
  "verdict": {
    "price_position": "lower",
    "listing_quality": "worse",
    "review_authority": "much weaker",
    "opportunity": "Add 18 missing keywords to close listing gap quickly"
  }
}
```

---

## AI Competitor Analysis Prompt

```
You are an Amazon competitive intelligence analyst.

Compare these two Amazon product listings and identify actionable insights.

OUR PRODUCT (ASIN: {our_asin}):
Title: {our_title}
Bullets: {our_bullets}
Description: {our_description}
Price: {our_price}
Rating: {our_rating} ({our_review_count} reviews)

COMPETITOR (ASIN: {comp_asin}):
Title: {comp_title}
Bullets: {comp_bullets}
Description: {comp_description}
Price: {comp_price}
Rating: {comp_rating} ({comp_review_count} reviews)

TOP KEYWORD GAPS (missing from our listing):
{top_10_missing_keywords}

Respond with JSON:
{
  "competitive_summary": "2-3 sentence overall assessment",
  "our_strengths": ["what we do better"],
  "our_weaknesses": ["what competitor does better"],
  "quick_wins": [
    {
      "action": "specific thing to change",
      "expected_impact": "expected outcome",
      "effort": "low|medium|high"
    }
  ],
  "keyword_recommendations": [
    {
      "keyword": "the keyword",
      "where_to_add": "title|bullet_2|description",
      "context": "how to naturally add it"
    }
  ],
  "pricing_insight": "assessment of price positioning"
}
```

---

## Multi-Competitor Aggregation

When a product has 3+ competitors, aggregate insights:

```
Top competitor keywords = keywords that appear in ≥ 50% of competitors but not in our listing
Pricing position = our price vs median competitor price
Review gap = our review count vs median competitor review count
Average competitor score = mean of competitor listing scores

Consensus quick wins = quick_wins that appear in ≥ 2 competitor analyses
```

---

## Update Cadence

Competitor data does not auto-update (no API). The user must:
1. Re-visit the competitor page and paste HTML again, or
2. Upload a fresh CSV

The UI shows `last_analyzed_at` and prompts re-import if data is older than 30 days.
