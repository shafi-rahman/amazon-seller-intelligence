<?php

namespace App\Modules\Imports\Parsers;

use App\Modules\Competitors\Models\Competitor;
use App\Modules\Imports\Models\ImportBatch;
use Symfony\Component\DomCrawler\Crawler;

class CompetitorsHtmlParser
{
    public function parse(string $html, ImportBatch $batch): array
    {
        $crawler    = new Crawler($html);
        $confidence = [];

        // ASIN — method 1: hidden input
        $asin = $this->extract($crawler, 'input#ASIN', 'value', $confidence, 'asin', 100);

        // ASIN — method 2: canonical URL
        if (empty($asin)) {
            $canonical = $this->extract($crawler, 'link[rel="canonical"]', 'href', $confidence, '_canonical', 0);
            if ($canonical && preg_match('/\/dp\/([A-Z0-9]{10})/', $canonical, $m)) {
                $asin = $m[1];
                $confidence['asin'] = 75;
            }
        }

        // ASIN — method 3: current URL in og:url
        if (empty($asin)) {
            $ogUrl = $this->extract($crawler, 'meta[property="og:url"]', 'content', $confidence, '_ogurl', 0);
            if ($ogUrl && preg_match('/\/dp\/([A-Z0-9]{10})/', $ogUrl, $m)) {
                $asin = $m[1];
                $confidence['asin'] = 60;
            }
        }

        if (empty($asin)) {
            $confidence['asin'] = 0;
        }

        // Title
        $title = $this->extractText($crawler, '#productTitle', $confidence, 'title', 100)
            ?? $this->extractText($crawler, 'h1.a-size-large', $confidence, 'title', 75);

        // Brand
        $brand = $this->extractText($crawler, '#bylineInfo', $confidence, 'brand', 100)
            ?? $this->extractText($crawler, '.po-brand .po-break-word', $confidence, 'brand', 75);
        if ($brand) {
            $brand = preg_replace('/^(Brand:\s*|Visit the\s*|\s*Store$)/i', '', $brand);
            $brand = trim($brand);
        }

        // Price — try multiple selectors
        $priceRaw = $this->extractText($crawler, '.a-price.aok-align-center .a-offscreen', $confidence, 'price', 100)
            ?? $this->extractText($crawler, '#priceblock_ourprice', $confidence, 'price', 90)
            ?? $this->extractText($crawler, '.a-price .a-offscreen', $confidence, 'price', 75);
        $price = $priceRaw ? (float) preg_replace('/[₹$€£,\s]/', '', $priceRaw) : null;
        if ($price === null) {
            $confidence['price'] = 0;
        }

        // Rating
        $ratingText = $this->extractText($crawler, '#acrPopover .a-icon-alt', $confidence, 'rating', 100)
            ?? $this->extractText($crawler, '[data-hook="rating-out-of-text"]', $confidence, 'rating', 75)
            ?? $this->extractText($crawler, '.a-icon-star-small .a-icon-alt', $confidence, 'rating', 60);
        $rating = null;
        if ($ratingText && preg_match('/^([\d.]+)/', $ratingText, $m)) {
            $rating = (float) $m[1];
            if ($rating < 1 || $rating > 5) {
                $rating = null;
            }
        }
        if ($rating === null) {
            $confidence['rating'] = 0;
        }

        // Review count
        $reviewText = $this->extractText($crawler, '#acrCustomerReviewText', $confidence, 'review_count', 100);
        $reviewCount = null;
        if ($reviewText && preg_match('/^([\d,]+)/', $reviewText, $m)) {
            $reviewCount = (int) str_replace(',', '', $m[1]);
        }
        if ($reviewCount === null) {
            $confidence['review_count'] = 0;
        }

        // Bullets — up to 5
        $bullets = [];
        $crawler->filter('#feature-bullets .a-list-item')->each(function (Crawler $node) use (&$bullets) {
            if ($node->closest('.aok-hidden')->count() > 0) {
                return;
            }
            $text = trim($node->text());
            if (!empty($text) && count($bullets) < 5) {
                $bullets[] = $text;
            }
        });
        $confidence['bullets'] = count($bullets) > 0 ? 100 : 0;

        // Description
        $descParts = [];
        $crawler->filter('#productDescription p')->each(function (Crawler $node) use (&$descParts) {
            $text = trim($node->text());
            if (!empty($text)) {
                $descParts[] = $text;
            }
        });
        if (empty($descParts)) {
            $crawler->filter('#aplus .aplus-module-wrapper')->each(function (Crawler $node) use (&$descParts) {
                $text = trim($node->text());
                if (!empty($text)) {
                    $descParts[] = $text;
                }
            });
            $confidence['description'] = empty($descParts) ? 0 : 60;
        } else {
            $confidence['description'] = 100;
        }
        $description = empty($descParts) ? null : implode("\n\n", $descParts);

        return [
            'asin'             => $asin ?: null,
            'title'            => $title,
            'brand'            => $brand,
            'price'            => $price,
            'rating'           => $rating,
            'review_count'     => $reviewCount,
            'bullet_1'         => $bullets[0] ?? null,
            'bullet_2'         => $bullets[1] ?? null,
            'bullet_3'         => $bullets[2] ?? null,
            'bullet_4'         => $bullets[3] ?? null,
            'bullet_5'         => $bullets[4] ?? null,
            'description'      => $description,
            'parse_confidence' => $confidence,
        ];
    }

    public function store(array $data, ImportBatch $batch, ?int $productId): Competitor
    {
        return Competitor::updateOrCreate(
            [
                'workspace_id' => $batch->workspace_id,
                'product_id'   => $productId,
                'asin'         => $data['asin'],
            ],
            array_merge($data, [
                'import_batch_id' => $batch->id,
                'source_type'     => 'html',
                'raw_html'        => $batch->meta['html_content'] ?? null,
                'product_id'      => $productId,
            ])
        );
    }

    private function extractText(Crawler $crawler, string $selector, array &$confidence, string $field, int $score): ?string
    {
        try {
            $node = $crawler->filter($selector)->first();
            if ($node->count() === 0) {
                return null;
            }
            $text = trim($node->text());
            if (strlen($text) < 2) {
                $confidence[$field] = 25;
                return $text ?: null;
            }
            $confidence[$field] = $score;
            return $text;
        } catch (\Throwable) {
            return null;
        }
    }

    private function extract(Crawler $crawler, string $selector, string $attr, array &$confidence, string $field, int $score): ?string
    {
        try {
            $node = $crawler->filter($selector)->first();
            if ($node->count() === 0) {
                return null;
            }
            $val = trim($node->attr($attr) ?? '');
            if (!empty($val)) {
                $confidence[$field] = $score;
            }
            return $val ?: null;
        } catch (\Throwable) {
            return null;
        }
    }
}
