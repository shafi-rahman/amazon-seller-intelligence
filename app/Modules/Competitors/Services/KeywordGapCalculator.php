<?php

namespace App\Modules\Competitors\Services;

class KeywordGapCalculator
{
    /**
     * Calculate keyword gaps between our product keywords and competitor keywords.
     * Returns rows ready for insert into keyword_gaps.
     */
    public function calculate(
        array $ourKeywords,     // [['keyword' => '', 'source' => '', 'frequency' => 0], ...]
        array $theirKeywords,   // same shape
        int   $productId,
        int   $competitorId,
        string $competitorTitle,
        array  $competitorBullets,
    ): array {
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
            $ourData = $ourMap[$normalized] ?? null;

            if ($ourData === null) {
                // Also try singular/plural variants
                $variant = $this->singularize($normalized);
                $ourData = $ourMap[$variant] ?? ($variant !== $normalized ? null : null);
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
