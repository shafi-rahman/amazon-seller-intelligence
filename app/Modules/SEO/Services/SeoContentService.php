<?php

namespace App\Modules\SEO\Services;

use App\Modules\AI\Services\AIRouter;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\ProductKeyword;
use App\Modules\SEO\Models\SeoCampaign;
use App\Modules\SEO\Models\SeoPost;
use Illuminate\Support\Facades\Log;

class SeoContentService
{
    // NVIDIA models — all use the same NVIDIA_API_KEY
    private const MODEL_RESEARCH = 'nvidia/llama-3.1-nemotron-70b-instruct';
    private const MODEL_CONTENT   = 'nvidia/nemotron-3-nano-omni-30b-a3b-reasoning';
    private const MODEL_FAST      = 'meta/llama-3.1-8b-instruct';

    public function __construct(private readonly AIRouter $router) {}

    /**
     * Full pipeline: trend research → 4 platform posts → save all
     */
    public function generate(SeoCampaign $campaign): void
    {
        $product = $campaign->product;

        if (!$product) {
            throw new \InvalidArgumentException("Campaign #{$campaign->id} has no linked product");
        }

        $campaign->update(['status' => 'generating']);

        // Step 1: Research trends for this product category
        $trendData = $this->researchTrends($product);
        $campaign->update(['trend_data' => $trendData]);

        // Step 2: Build shared context
        $context = $this->buildProductContext($product, $trendData);

        // Step 3: Generate all 4 platform posts
        $platforms = [
            'instagram'       => fn() => $this->generateInstagram($context),
            'facebook'        => fn() => $this->generateFacebook($context),
            'linkedin'        => fn() => $this->generateLinkedIn($context),
            'google_business' => fn() => $this->generateGoogleBusiness($context),
        ];

        $postsData = [];
        foreach ($platforms as $platform => $generator) {
            try {
                $postsData[$platform] = $generator();
            } catch (\Throwable $e) {
                Log::warning("SEO content failed for {$platform}", ['error' => $e->getMessage()]);
                $postsData[$platform] = ['caption' => null, 'hashtags' => null, 'image_prompt' => null];
            }
        }

        // Step 4: Save posts
        foreach ($postsData as $platform => $data) {
            SeoPost::create([
                'campaign_id'  => $campaign->id,
                'platform'     => $platform,
                'caption'      => $data['caption'] ?? null,
                'hashtags'     => $data['hashtags'] ?? null,
                'image_prompt' => $data['image_prompt'] ?? null,
                'status'       => 'draft',
            ]);
        }

        // Step 5: Mark campaign ready for review
        $activeProvider = $this->router->activeProvider();
        $campaign->update([
            'status'       => 'awaiting_approval',
            'generated_at' => now(),
            'ai_provider'  => $activeProvider,
        ]);
    }

    // ─── Step 1: Trend Research ───────────────────────────────────────────

    // System message that helps reasoning models output clean JSON
    private const JSON_SYSTEM = 'You are a JSON API. You MUST respond with ONLY a valid JSON object. No explanations, no markdown, no text before or after the JSON. Output the JSON directly after your reasoning.';

    private function researchTrends(Product $product): array
    {
        $month    = now()->format('F');
        $season   = $this->indianSeason(now()->month);
        $keywords = ProductKeyword::where('product_id', $product->id)
            ->orderByDesc('frequency')
            ->limit(8)
            ->pluck('keyword')
            ->implode(', ');

        $prompt = <<<PROMPT
You are a social media marketing expert for Amazon India sellers.

Identify current trends and angles that would make social media posts for this product perform well right now.

Product: {$product->title}
Category: {$product->category}
Brand: {$product->brand}
Month: {$month} | Season/Context: {$season}
Top keywords: {$keywords}

Respond with ONLY valid JSON (no markdown, no explanation):
{
  "trending_topics": ["topic1", "topic2", "topic3"],
  "seasonal_context": "one sentence about current Indian context",
  "content_angle": "the best emotional/practical angle for this product right now",
  "target_audience": "specific Indian audience segment most likely to buy",
  "hook_idea": "one compelling hook sentence to open posts"
}
PROMPT;

        $result = $this->router->chat(
            [
                ['role' => 'system', 'content' => self::JSON_SYSTEM],
                ['role' => 'user', 'content' => $prompt],
            ],
            'seo',   // small reasoning budget for structured JSON output
            1024,
        );

        return $this->parseJson($result['content'], [
            'trending_topics' => [],
            'seasonal_context'=> $season,
            'content_angle'   => 'quality and value',
            'target_audience' => 'Indian consumers',
            'hook_idea'       => 'Upgrade your daily routine',
        ]);
    }

    // ─── Step 2: Platform Content Generation ────────────────────────────────

    private function generateInstagram(array $ctx): array
    {
        $prompt = <<<PROMPT
Write an Instagram post for this Amazon India product.

Product: {$ctx['title']}
Brand: {$ctx['brand']} | Price: ₹{$ctx['price']}
Key benefit: {$ctx['bullet_1']}
Rating: {$ctx['rating']}★ ({$ctx['review_count']} reviews)
Content angle: {$ctx['content_angle']}
Hook: {$ctx['hook_idea']}
Target audience: {$ctx['target_audience']}

Rules:
- Caption: 100-150 characters, punchy, use the hook
- Include 10 hashtags (mix popular + niche, include #AmazonIndia #Amazon)
- Max 2 emojis total
- End with: "Link in bio 🛒" or "Shop on Amazon 🛒"
- NO prices in caption hashtags

Also write an image generation prompt for AI image creation of this product.

Return ONLY valid JSON:
{
  "caption": "...",
  "hashtags": "#tag1 #tag2 ...",
  "image_prompt": "product photography style image description, no text in image, clean white background, professional lighting, Amazon product shot style, {$ctx['category']}"
}
PROMPT;

        $result = $this->router->chat(
            [
                ['role' => 'system', 'content' => self::JSON_SYSTEM],
                ['role' => 'user', 'content' => $prompt],
            ],
            'seo',
            1024,
        );

        return $this->parseJson($result['content'], ['caption' => '', 'hashtags' => '', 'image_prompt' => '']);
    }

    private function generateFacebook(array $ctx): array
    {
        $prompt = <<<PROMPT
Write a Facebook post for this Amazon India product.

Product: {$ctx['title']}
Brand: {$ctx['brand']} | Price: ₹{$ctx['price']}
Benefits: {$ctx['bullet_1']} | {$ctx['bullet_2']}
Rating: {$ctx['rating']}★ ({$ctx['review_count']} reviews)
Content angle: {$ctx['content_angle']}
Seasonal context: {$ctx['seasonal_context']}
Target: {$ctx['target_audience']}

Rules:
- Start with a relatable question or bold statement
- Tell a 2-sentence mini-story about the problem this solves
- Mention key benefit + price naturally in the text
- End with: "Available now on Amazon India 👇" + a CTA
- 200-280 words total
- Conversational Indian English (not overly formal)

Return ONLY valid JSON:
{
  "caption": "full post text here"
}
PROMPT;

        $result = $this->router->chat(
            [
                ['role' => 'system', 'content' => self::JSON_SYSTEM],
                ['role' => 'user', 'content' => $prompt],
            ],
            'seo',
            1500,
        );

        return $this->parseJson($result['content'], ['caption' => '']);
    }

    private function generateLinkedIn(array $ctx): array
    {
        $prompt = <<<PROMPT
Write a LinkedIn post about this product from a professional productivity perspective.

Product: {$ctx['title']}
Category: {$ctx['category']} | Price: ₹{$ctx['price']}
Key benefits: {$ctx['bullet_1']} | {$ctx['bullet_2']}
Content angle: {$ctx['content_angle']}

Rules:
- Open with a professional insight or surprising stat
- Connect the product to professional improvement / productivity / wellness at work
- Mention the product naturally (not salesy)
- 300-400 words
- End with a question to drive comments
- Include 4-5 professional hashtags like #ProductivityTips #WorkFromHome etc.

Return ONLY valid JSON:
{
  "caption": "full post text",
  "hashtags": "#tag1 #tag2 #tag3 #tag4"
}
PROMPT;

        $result = $this->router->chat(
            [['role' => 'user', 'content' => $prompt]],
            'general',
            1500,
        );

        return $this->parseJson($result['content'], ['caption' => '', 'hashtags' => '']);
    }

    private function generateGoogleBusiness(array $ctx): array
    {
        $prompt = <<<PROMPT
Write a Google Business Profile post for this Amazon India product.

Product: {$ctx['title']}
Price: ₹{$ctx['price']}
Main benefit: {$ctx['bullet_1']}

Rules:
- 100-150 characters maximum
- Keyword-rich for local/Google search
- Include price
- CTA: "Order on Amazon India"
- NO hashtags

Return ONLY valid JSON:
{
  "caption": "short keyword-rich post text"
}
PROMPT;

        $result = $this->router->chat(
            [
                ['role' => 'system', 'content' => self::JSON_SYSTEM],
                ['role' => 'user', 'content' => $prompt],
            ],
            'seo',
            512,
        );

        return $this->parseJson($result['content'], ['caption' => '']);
    }

    // ─── Helpers ───────────────────────────────────────────────────────────

    private function buildProductContext(Product $product, array $trend): array
    {
        return [
            'title'            => $product->title ?? '',
            'brand'            => $product->brand ?? '',
            'category'         => $product->category ?? '',
            'price'            => number_format((float) $product->price, 0),
            'rating'           => $product->rating ?? '4.0',
            'review_count'     => number_format($product->review_count ?? 0),
            'bullet_1'         => $product->bullet_1 ?? '',
            'bullet_2'         => $product->bullet_2 ?? '',
            'content_angle'    => $trend['content_angle'] ?? 'quality and value',
            'seasonal_context' => $trend['seasonal_context'] ?? '',
            'hook_idea'        => $trend['hook_idea'] ?? 'Upgrade your experience',
            'target_audience'  => $trend['target_audience'] ?? 'Indian consumers',
        ];
    }

    private function parseJson(string $text, array $fallback): array
    {
        // Strip markdown fences and find JSON
        $text = preg_replace('/^```(?:json)?\s*/m', '', $text);
        $text = preg_replace('/\s*```$/m', '', $text);

        if (preg_match('/\{.*\}/s', $text, $m)) {
            $decoded = json_decode($m[0], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }

        Log::warning('SEO content JSON parse failed', ['raw' => substr($text, 0, 300)]);
        return $fallback;
    }

    private function indianSeason(int $month): string
    {
        return match(true) {
            in_array($month, [3, 4])  => 'Holi season, summer approaching, pre-IPL excitement',
            in_array($month, [5, 6])  => 'Summer peak, back-to-school, monsoon prep',
            in_array($month, [7, 8])  => 'Monsoon season, Independence Day, Raksha Bandhan approaching',
            in_array($month, [9, 10]) => 'Navratri, Durga Puja, Diwali preparations, festive season',
            in_array($month, [11])    => 'Post-Diwali, wedding season, year-end deals',
            in_array($month, [12, 1]) => 'Christmas, New Year, winter sales, Republic Day approaching',
            default                   => 'February, Valentine\'s Day, pre-Holi, budget season',
        };
    }
}
