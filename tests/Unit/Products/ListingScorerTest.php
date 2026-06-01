<?php

namespace Tests\Unit\Products;

use App\Modules\Products\Models\Product;
use App\Modules\Products\Services\KeywordExtractorService;
use App\Modules\Products\Services\ListingScorerService;
use Tests\TestCase;

class ListingScorerTest extends TestCase
{
    private ListingScorerService $scorer;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $extractor    = new KeywordExtractorService();
        $this->scorer = new ListingScorerService($extractor);

        // Build a representative product without touching the DB
        $this->product = new Product([
            'asin'        => 'B09TEST001',
            'sku'         => 'TEST-SKU-001',
            'title'       => 'Blue Ceramic Coffee Mug 350ml | Dishwasher Safe | Gift for Coffee Lovers',
            'brand'       => 'MugCo',
            'category'    => 'Kitchen & Dining',
            'bullet_1'    => 'PREMIUM CERAMIC MATERIAL: Made from high-quality food-grade ceramic that is 100% BPA free and non-toxic.',
            'bullet_2'    => 'PERFECT SIZE: 350ml capacity — ideal for your morning coffee, tea, or hot chocolate without spilling.',
            'bullet_3'    => 'EASY TO CLEAN: Dishwasher safe for easy maintenance. Microwave safe up to 800W for quick reheating.',
            'bullet_4'    => 'THOUGHTFUL GIFT: Comes in premium gift-ready packaging, perfect for birthdays, anniversaries, or Christmas.',
            'bullet_5'    => 'SATISFACTION GUARANTEED: If you are not 100% satisfied, contact us and we will make it right.',
            'description' => '<p>Elevate your coffee experience with the MugCo Ceramic Coffee Mug. This beautifully crafted 350ml mug is made from premium food-grade ceramic, ensuring your beverages stay at the perfect temperature longer. The ergonomic handle provides a comfortable grip, while the smooth glaze finish makes it easy to clean. Whether you enjoy a morning cup of coffee, afternoon tea, or hot cocoa, this mug is your perfect companion. Dishwasher and microwave safe for everyday convenience.</p>
<p>The MugCo ceramic mug makes an excellent gift for coffee lovers, tea enthusiasts, or anyone who appreciates quality kitchenware. Available in multiple colors to match your kitchen decor. Each mug is individually inspected for quality before shipping.</p>',
            'rating'      => '4.2',
            'review_count'=> 128,
        ]);
        // Make id non-null so competitor check doesn't fail
        $this->product->id = 0;
    }

    // ─── Determinism ─────────────────────────────────────────────────────

    public function test_score_is_deterministic(): void
    {
        $score1 = $this->scorer->score($this->product);
        $score2 = $this->scorer->score($this->product);

        $this->assertEquals($score1['total'], $score2['total'], 'Score must be the same for the same input');
        $this->assertEquals(
            $score1['dimensions']['title']['score'],
            $score2['dimensions']['title']['score'],
        );
    }

    public function test_score_returns_all_five_dimensions(): void
    {
        $result = $this->scorer->score($this->product);
        $dims   = $result['dimensions'];

        $this->assertArrayHasKey('title', $dims);
        $this->assertArrayHasKey('bullets', $dims);
        $this->assertArrayHasKey('description', $dims);
        $this->assertArrayHasKey('reviews', $dims);
        $this->assertArrayHasKey('keywords', $dims);
    }

    public function test_total_is_sum_of_dimensions(): void
    {
        $result = $this->scorer->score($this->product);
        $dims   = $result['dimensions'];

        $expected = $dims['title']['score'] + $dims['bullets']['score']
                  + $dims['description']['score'] + $dims['reviews']['score']
                  + $dims['keywords']['score'];

        $this->assertEquals($expected, $result['total']);
    }

    public function test_score_never_exceeds_100(): void
    {
        $result = $this->scorer->score($this->product);
        $this->assertLessThanOrEqual(100, $result['total']);
    }

    public function test_score_is_never_negative(): void
    {
        $result = $this->scorer->score($this->product);
        $this->assertGreaterThanOrEqual(0, $result['total']);
    }

    // ─── Title dimension ──────────────────────────────────────────────────

    public function test_title_max_25_points(): void
    {
        $result = $this->scorer->score($this->product);
        $this->assertLessThanOrEqual(25, $result['dimensions']['title']['score']);
    }

    public function test_missing_title_scores_zero(): void
    {
        $this->product->title = null;
        $result = $this->scorer->score($this->product);
        $this->assertEquals(0, $result['dimensions']['title']['score']);
        $this->assertNotEmpty($result['dimensions']['title']['issues']);
    }

    public function test_title_gets_brand_points_when_brand_in_title(): void
    {
        // Brand 'MugCo' is in the title
        $result  = $this->scorer->score($this->product);
        $passes  = $result['dimensions']['title']['passes'];
        $hasBrandPass = collect($passes)->contains(fn($p) => str_contains($p, 'Brand name'));
        $this->assertTrue($hasBrandPass, 'Title should get brand credit');
    }

    public function test_short_title_loses_length_points(): void
    {
        $this->product->title = 'Mug';
        $result = $this->scorer->score($this->product);
        $issues = $result['dimensions']['title']['issues'];
        $hasLengthIssue = collect($issues)->contains(fn($i) => str_contains($i, 'short') || str_contains($i, 'chars'));
        $this->assertTrue($hasLengthIssue, 'Short title should produce a length issue');
    }

    public function test_all_caps_title_loses_points(): void
    {
        $this->product->title = 'BLUE CERAMIC COFFEE MUG 350ML DISHWASHER SAFE MICROWAVE SAFE';
        $result = $this->scorer->score($this->product);
        $issues = $result['dimensions']['title']['issues'];
        $hasCapsIssue = collect($issues)->contains(fn($i) => str_contains(strtolower($i), 'caps'));
        $this->assertTrue($hasCapsIssue, 'Excessive ALL CAPS should produce an issue');
    }

    // ─── Bullets dimension ────────────────────────────────────────────────

    public function test_bullets_max_25_points(): void
    {
        $result = $this->scorer->score($this->product);
        $this->assertLessThanOrEqual(25, $result['dimensions']['bullets']['score']);
    }

    public function test_five_bullets_get_full_count_credit(): void
    {
        $result = $this->scorer->score($this->product);
        // 5 bullets = +10
        $this->assertGreaterThanOrEqual(10, $result['dimensions']['bullets']['score'],
            '5 bullets should award at least 10 base points');
    }

    public function test_missing_bullets_scores_zero(): void
    {
        $this->product->bullet_1 = null;
        $this->product->bullet_2 = null;
        $this->product->bullet_3 = null;
        $this->product->bullet_4 = null;
        $this->product->bullet_5 = null;
        $result = $this->scorer->score($this->product);
        $this->assertEquals(0, $result['dimensions']['bullets']['score']);
    }

    public function test_bullets_with_numbers_get_spec_credit(): void
    {
        // Our bullets contain numbers (350ml, 800W, 100%)
        $result = $this->scorer->score($this->product);
        $passes = $result['dimensions']['bullets']['passes'];
        $hasSpec = collect($passes)->contains(fn($p) => str_contains($p, 'numbers') || str_contains($p, 'spec'));
        $this->assertTrue($hasSpec, 'Bullets with numbers/specs should get credit');
    }

    // ─── Description dimension ────────────────────────────────────────────

    public function test_description_max_20_points(): void
    {
        $result = $this->scorer->score($this->product);
        $this->assertLessThanOrEqual(20, $result['dimensions']['description']['score']);
    }

    public function test_html_description_gets_formatting_credit(): void
    {
        $result = $this->scorer->score($this->product);
        $passes = $result['dimensions']['description']['passes'];
        $hasHtml = collect($passes)->contains(fn($p) => str_contains(strtolower($p), 'html'));
        $this->assertTrue($hasHtml, 'HTML description should get formatting credit');
    }

    public function test_missing_description_scores_zero(): void
    {
        $this->product->description = null;
        $result = $this->scorer->score($this->product);
        $this->assertEquals(0, $result['dimensions']['description']['score']);
    }

    // ─── Reviews dimension ────────────────────────────────────────────────

    public function test_reviews_max_15_points(): void
    {
        $result = $this->scorer->score($this->product);
        $this->assertLessThanOrEqual(15, $result['dimensions']['reviews']['score']);
    }

    public function test_high_rating_scores_well(): void
    {
        $this->product->rating       = '4.5';
        $this->product->review_count = 200;
        $result = $this->scorer->score($this->product);
        $this->assertEquals(15, $result['dimensions']['reviews']['score'],
            'Rating 4.5 + 200 reviews = maximum 15 points');
    }

    public function test_no_reviews_scores_zero_with_issue(): void
    {
        $this->product->rating       = null;
        $this->product->review_count = 0;
        $result = $this->scorer->score($this->product);
        $this->assertEquals(0, $result['dimensions']['reviews']['score']);
        $this->assertNotEmpty($result['dimensions']['reviews']['issues']);
    }

    // ─── Keywords dimension ───────────────────────────────────────────────

    public function test_keywords_max_15_points(): void
    {
        $result = $this->scorer->score($this->product);
        $this->assertLessThanOrEqual(15, $result['dimensions']['keywords']['score']);
    }

    // ─── Issue/pass text quality ──────────────────────────────────────────

    public function test_each_issue_is_actionable_text(): void
    {
        $this->product->title    = 'Mug'; // trigger title issues
        $this->product->bullet_1 = null;
        $this->product->bullet_2 = null;
        $this->product->bullet_3 = null;
        $this->product->bullet_4 = null;
        $this->product->bullet_5 = null;

        $result = $this->scorer->score($this->product);

        foreach ($result['dimensions'] as $dim) {
            foreach ($dim['issues'] as $issue) {
                $this->assertIsString($issue);
                $this->assertGreaterThan(10, strlen($issue), 'Issues must be descriptive (>10 chars)');
            }
        }
    }
}
