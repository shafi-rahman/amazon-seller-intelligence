<?php

namespace App\Modules\Competitors\Services;

class KeywordGapCalculator
{
    // Common English + e-commerce filler words that are never useful SEO gaps.
    private const STOP_WORDS = [
        'the','a','an','and','or','but','for','to','of','in','on','at','by','with','from','as',
        'is','are','was','were','be','been','it','its','this','that','these','those','your','our',
        'you','we','they','will','can','has','have','had','not','no','yes','all','any','each','more',
        'most','new','best','top','pack','set','pcs','pc','piece','pieces','item','items','product',
        'products','quality','premium','free','offer','sale','buy','online','amazon','brand','genuine',
        'original','pack of','combo','x','ml','cm','mm','kg','gm','gms','inch','inches','size','color',
        'colour','black','white','blue','red','green','grey','gray','pink',
    ];

    /**
     * Calculate keyword gaps between our product keywords and competitor keywords.
     * Returns rows ready for insert into keyword_gaps.
     *
     * @param string[] $excludeBrands brand names to strip from gaps (product + competitor brand)
     */
    public function calculate(
        array $ourKeywords,     // [['keyword' => '', 'source' => '', 'frequency' => 0], ...]
        array $theirKeywords,   // same shape
        int   $productId,
        int   $competitorId,
        string $competitorTitle,
        array  $competitorBullets,
        array  $excludeBrands = [],
    ): array {
        $this->buildDropTokens($excludeBrands);

        // Build lookup maps: normalized_keyword → [frequency, source]
        $ourMap   = $this->buildMap($ourKeywords);
        $theirMap = $this->buildMap($theirKeywords);

        // Position bonus: which keywords appear in title/first 3 bullets?
        $titleWords   = $this->extractWords(mb_strtolower($competitorTitle));
        $bullet3Words = $this->extractWords(mb_strtolower(
            implode(' ', array_slice($competitorBullets, 0, 3))
        ));

        $gaps = [];

        // Pass 1: scan competitor keywords for missing/underused
        foreach ($theirMap as $normalized => $theirData) {
            if ($this->shouldDrop($normalized)) {
                continue;
            }
            $ourData = $ourMap[$normalized] ?? null;

            if ($ourData === null) {
                // Also try singular/plural variants
                $variant = $this->singularize($normalized);
                $ourData = $ourMap[$variant] ?? null;
            }

            if ($ourData === null) {
                // We don't have this keyword at all → missing
                $gap = [
                    'product_id'     => $productId,
                    'competitor_id'  => $competitorId,
                    'keyword'        => $normalized,
                    'gap_type'       => 'missing',
                    'our_frequency'  => 0,
                    'their_frequency'=> $theirData['frequency'],
                    'priority_score' => $this->priorityScore('missing', $theirData, $normalized, $titleWords, $bullet3Words),
                ];
            } elseif ($theirData['frequency'] > $ourData['frequency'] * 1.5) {
                // We have it but they use it much more → underused
                $gap = [
                    'product_id'     => $productId,
                    'competitor_id'  => $competitorId,
                    'keyword'        => $normalized,
                    'gap_type'       => 'underused',
                    'our_frequency'  => $ourData['frequency'],
                    'their_frequency'=> $theirData['frequency'],
                    'priority_score' => $this->priorityScore('underused', $theirData, $normalized, $titleWords, $bullet3Words),
                ];
            } else {
                continue; // Not a meaningful gap
            }

            $gaps[] = $gap;
        }

        // Pass 2: scan our keywords for advantages (we have, they don't)
        foreach ($ourMap as $normalized => $ourData) {
            if ($this->shouldDrop($normalized)) {
                continue;
            }
            $theirData = $theirMap[$normalized] ?? $theirMap[$this->singularize($normalized)] ?? null;

            if ($theirData === null) {
                $gaps[] = [
                    'product_id'     => $productId,
                    'competitor_id'  => $competitorId,
                    'keyword'        => $normalized,
                    'gap_type'       => 'advantage',
                    'our_frequency'  => $ourData['frequency'],
                    'their_frequency'=> 0,
                    'priority_score' => $this->priorityScore('advantage', $ourData, $normalized, [], []),
                ];
            }
        }

        return $gaps;
    }

    private function priorityScore(
        string $type,
        array  $data,
        string $keyword,
        array  $titleWords,
        array  $bullet3Words,
    ): int {
        $base = match ($type) {
            'missing'   => 60,
            'underused' => 40,
            default     => 20, // advantage
        };

        $freq = $data['frequency'] ?? 0;
        $frequencyBonus = match(true) {
            $freq >= 5 => 20,
            $freq >= 3 => 10,
            $freq >= 2 => 5,
            default    => 0,
        };

        // Position bonus: keyword in competitor's title?
        $keywordWords = explode(' ', $keyword);
        $inTitle      = !empty(array_intersect($keywordWords, $titleWords));
        $inFirst3     = !$inTitle && !empty(array_intersect($keywordWords, $bullet3Words));

        $positionBonus = match(true) {
            $inTitle  => 15,
            $inFirst3 => 5,
            default   => 0,
        };

        return min(95, $base + $frequencyBonus + $positionBonus);
    }

    /** @var array<string,bool> generic stop words */
    private array $stopSet = [];
    /** @var string[] brand words (len>=3), matched by prefix/substring */
    private array $brandTokens = [];

    private function buildDropTokens(array $excludeBrands): void
    {
        $this->stopSet = array_fill_keys(self::STOP_WORDS, true);
        $this->brandTokens = [];
        foreach ($excludeBrands as $brand) {
            foreach ($this->extractWords(mb_strtolower((string) $brand)) as $w) {
                if (mb_strlen($w) >= 3 && !isset($this->stopSet[$w])) {
                    $this->brandTokens[] = $w;
                }
            }
        }
        $this->brandTokens = array_unique($this->brandTokens);
    }

    /** True if a keyword word relates to a brand token (handles "Rival" vs "RivalAudio"). */
    private function isBrandWord(string $w): bool
    {
        if (mb_strlen($w) < 4) {
            return false;
        }
        foreach ($this->brandTokens as $b) {
            // exact, or one is a prefix of / contained in the other
            if ($w === $b || str_starts_with($b, $w) || str_starts_with($w, $b) || str_contains($b, $w) || str_contains($w, $b)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Drop a keyword gap if it's noise: contains a brand word, is a stop word,
     * numeric, too short, or a phrase where every word is a brand/stop word.
     */
    private function shouldDrop(string $keyword): bool
    {
        $keyword = trim($keyword);
        if ($keyword === '' || mb_strlen($keyword) < 3 || !preg_match('/[a-z]/i', $keyword)) {
            return true;
        }

        $words = array_values(array_filter(explode(' ', $keyword)));
        if (empty($words)) {
            return true;
        }

        $allDrop = true;
        foreach ($words as $w) {
            if ($this->isBrandWord($w)) {
                return true; // any brand word anywhere → drop the whole phrase
            }
            if (!isset($this->stopSet[$w])) {
                $allDrop = false;
            }
        }
        return $allDrop; // every word was a stop word
    }

    private function buildMap(array $keywords): array
    {
        $map = [];
        foreach ($keywords as $kw) {
            $normalized = $this->normalize($kw['keyword']);
            if (!isset($map[$normalized]) || $kw['frequency'] > $map[$normalized]['frequency']) {
                $map[$normalized] = [
                    'frequency' => $kw['frequency'],
                    'source'    => $kw['source'],
                ];
            }
        }
        return $map;
    }

    private function normalize(string $keyword): string
    {
        return trim(mb_strtolower($keyword));
    }

    private function singularize(string $word): string
    {
        // Simple English singularizer — handles the most common cases
        if (str_ends_with($word, 'ies') && strlen($word) > 4) {
            return substr($word, 0, -3) . 'y';
        }
        if (str_ends_with($word, 'es') && strlen($word) > 3) {
            return substr($word, 0, -2);
        }
        if (str_ends_with($word, 's') && strlen($word) > 2 && !str_ends_with($word, 'ss')) {
            return substr($word, 0, -1);
        }
        return $word;
    }

    private function extractWords(string $text): array
    {
        return array_filter(explode(' ', preg_replace('/[^a-z\s]/', ' ', $text)));
    }
}
