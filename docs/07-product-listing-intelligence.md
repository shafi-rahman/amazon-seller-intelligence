# Product & Listing Intelligence

Answers: **"Why aren't my products selling?"**

---

## Listing Score Algorithm

Each product is scored out of **100 points** across 5 dimensions.

### Score Breakdown

| Dimension | Max Points | Weight |
|-----------|-----------|--------|
| Title Quality | 25 | 25% |
| Bullet Points | 25 | 25% |
| Description | 20 | 20% |
| Reviews & Ratings | 15 | 15% |
| Keyword Coverage | 15 | 15% |
| **Total** | **100** | |

---

### 1. Title Quality (25 points)

| Rule | Points | Check |
|------|--------|-------|
| Title exists and is not empty | 5 | `strlen(title) > 0` |
| Title length 80–200 characters | 5 | optimal: 80-200 chars |
| Title length 200–250 characters | 3 | acceptable but long |
| Title length < 80 characters | 0 | too short |
| Title length > 250 characters | 2 | may be truncated |
| Brand name appears in title | 3 | `str_contains(title, brand)` |
| Primary keyword in first 80 chars | 7 | AI-detected primary keyword present near start |
| No ALL CAPS words (more than 2) | 3 | Amazon policy violation risk |
| No special characters overuse | 2 | no `!`, `#`, `$`, `*` as padding |

**Primary keyword** = the highest-frequency non-stopword bigram in the title and bullets combined, determined by simple TF analysis before AI runs.

---

### 2. Bullet Points (25 points)

| Rule | Points |
|------|--------|
| All 5 bullets present | 10 |
| 4 bullets present | 7 |
| 3 bullets present | 4 |
| < 3 bullets | 0 |
| Average bullet length ≥ 100 chars | 5 |
| Average bullet length ≥ 60 chars | 3 |
| Average bullet length < 60 chars | 0 |
| Each bullet starts with a key feature/benefit | 5 (1 per bullet) |
| No duplicate content across bullets | 3 |
| Numbers/specs present in at least 2 bullets | 2 |

**Key feature detection**: AI checks if the bullet leads with a benefit, a spec, or a use case (not filler like "Great product...").

---

### 3. Description (20 points)

| Rule | Points |
|------|--------|
| Description exists | 3 |
| Length ≥ 500 characters | 5 |
| Length ≥ 1000 characters | 8 |
| Length ≥ 2000 characters | 10 |
| Contains HTML formatting (`<br>`, `<b>`, `<p>`) | 3 |
| Keywords present in description | 4 |
| No keyword stuffing (same phrase > 3x) | 3 |

---

### 4. Reviews & Ratings (15 points)

| Rule | Points |
|------|--------|
| Rating ≥ 4.5 | 10 |
| Rating 4.0–4.49 | 7 |
| Rating 3.5–3.99 | 4 |
| Rating < 3.5 | 0 |
| Review count ≥ 100 | 5 |
| Review count 50–99 | 3 |
| Review count 10–49 | 1 |
| Review count < 10 | 0 |

Note: Reviews are imported via CSV — if not provided, this dimension scores 0 and a note is shown: *"Add review data via CSV to unlock this score."*

---

### 5. Keyword Coverage (15 points)

This dimension requires competitor data to be meaningful.

| Rule | Points |
|------|--------|
| Primary keyword density ≥ 2 appearances | 4 |
| ≤ 20% of competitor top-10 keywords missing | 5 |
| ≤ 40% of competitor top-10 keywords missing | 3 |
| > 40% missing | 0 |
| Long-tail keyword variety (≥ 5 unique 3+ word phrases) | 3 |
| No keyword in top-10 is severely underused (< 50% of competitor frequency) | 3 |

If no competitor data exists: this section scores 7/15 as a neutral default with note: *"Add competitor ASINs to unlock full keyword score."*

---

## Score Tiers

| Score | Tier | Action |
|-------|------|--------|
| 85–100 | Excellent | Minor tweaks only |
| 70–84 | Good | Optimize keywords |
| 50–69 | Needs Work | Fix title + bullets |
| 30–49 | Poor | Major rewrite needed |
| 0–29 | Critical | Listing may be suppressed |

---

## Keyword Extraction

### Process

```
1. Combine text: title + " " + bullet_1..5 + " " + description
2. Lowercase, remove HTML tags
3. Tokenize (split on whitespace + punctuation)
4. Remove stopwords (English + Hindi common stopwords)
5. Extract unigrams, bigrams, trigrams
6. Count frequency of each n-gram
7. Filter: minimum 1 character, no pure numbers
8. Store top 100 keywords in product_keywords table
```

### Stopwords to Remove
```
English: a, an, the, is, are, was, were, be, been, being, have, has, had, do, does,
         did, will, would, could, should, may, might, shall, must, can, to, of, in,
         for, on, with, at, by, from, as, into, through, during, before, after, above,
         below, between, each, this, that, these, those, our, your, and, or, but, if,
         very, just, also, only, more, most, some, any, all, both

Product-specific to remove: product, item, pack, set, piece, unit, quality
```

---

## AI-Powered Analysis

### Prompt: Listing Analysis
```
You are an Amazon listing optimization expert.

Analyze this product listing and provide structured feedback:

ASIN: {asin}
Title: {title}
Bullet 1: {bullet_1}
Bullet 2: {bullet_2}
Bullet 3: {bullet_3}
Bullet 4: {bullet_4}
Bullet 5: {bullet_5}
Description: {description}
Category: {category}
Current Price: {price} {currency}
Rating: {rating} ({review_count} reviews)

Respond with a JSON object containing:
{
  "primary_keyword": "the most important search term for this product",
  "secondary_keywords": ["list", "of", "5-10", "important", "keywords"],
  "title_issues": ["list of specific issues with the title"],
  "bullet_issues": ["list of specific issues with bullets"],
  "description_issues": ["list of specific issues with description"],
  "optimization_suggestions": [
    {
      "field": "title|bullet_1|description|etc",
      "priority": "high|medium|low",
      "issue": "specific problem",
      "suggestion": "specific actionable fix",
      "rewritten": "optimized version of this field"
    }
  ],
  "overall_assessment": "2-3 sentence summary"
}
```

### Prompt: AI Rewrite
```
You are an Amazon listing copywriter.

Rewrite the following listing to maximize search visibility and conversion.
Follow Amazon's guidelines: no promotional language, no competitor comparisons,
factual claims only.

Original listing:
Title: {title}
Bullets: {bullets}
Description: {description}
Category: {category}
Primary keyword: {primary_keyword}
Missing keywords to incorporate: {missing_keywords}

Return JSON:
{
  "title": "optimized title (under 200 chars)",
  "bullet_1": "...", "bullet_2": "...", "bullet_3": "...",
  "bullet_4": "...", "bullet_5": "...",
  "description": "optimized description"
}
```

---

## Analysis Job Flow

```
ProductsImportCompleted event
  → Queue: AnalyzeProductsJob (one job per product)
    → Step 1: Keyword extraction (pure PHP, fast)
    → Step 2: Score calculation (pure PHP, rule-based)
    → Step 3: AI analysis call (Claude/OpenAI — returns structured JSON)
    → Step 4: Store results:
        - products.listing_score updated
        - product_keywords table populated
        - product_analyses record created
    → ProductAnalysisCompleted event dispatched
      → AI\Listeners\EmbedProductData (queue embeddings job)
      → Reports\Listeners\UpdateListingReport
```

---

## Performance Optimization

- Keyword extraction runs synchronously within the import job (fast)
- Rule-based scoring runs synchronously (fast)
- AI analysis runs in `ai` queue (async, throttled)
- Results cached in Redis for 24 hours: `product:{id}:listing_score`
- Cache busted when product data is re-imported or re-analyzed

---

## Review Sentiment Analysis

When review data is imported, an additional AI job runs:

### Prompt: Review Sentiment Summary
```
Analyze these Amazon product reviews and provide a structured summary.

Product: {product_name}
Reviews (up to 50 most recent):
{reviews_text}

Return JSON:
{
  "overall_sentiment": "positive|mixed|negative",
  "avg_rating": float,
  "top_praise_themes": ["what customers love most"],
  "top_complaint_themes": ["what customers dislike most"],
  "quality_issues": ["specific quality/defect mentions"],
  "competitor_comparisons": ["reviews that mention competitors"],
  "seller_feedback": "1-paragraph actionable summary for the seller"
}
```

Results stored in `product_analyses` with `analysis_type = 'sentiment'`.
