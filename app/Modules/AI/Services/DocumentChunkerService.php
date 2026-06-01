<?php

namespace App\Modules\AI\Services;

class DocumentChunkerService
{
    // ~512 tokens ≈ 2000 English characters
    private const MAX_CHARS = 2000;
    private const OVERLAP   = 200;  // ~50 tokens overlap between chunks

    /**
     * Split text into chunks of MAX_CHARS with OVERLAP.
     * Most product listings fit in a single chunk.
     */
    public function chunk(string $text): array
    {
        // Normalize whitespace and strip HTML tags
        $text = trim(preg_replace('/\s+/', ' ', strip_tags($text)));

        if (empty($text)) {
            return [];
        }

        if (mb_strlen($text) <= self::MAX_CHARS) {
            return [$text];
        }

        // Split at sentence boundaries (. ! ?)
        $sentences = preg_split('/(?<=[.!?])\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);

        $chunks  = [];
        $current = '';

        foreach ($sentences as $sentence) {
            $combined = $current !== '' ? $current.' '.$sentence : $sentence;

            if (mb_strlen($combined) <= self::MAX_CHARS) {
                $current = $combined;
            } else {
                if ($current !== '') {
                    $chunks[] = trim($current);
                }
                // Start new chunk with overlap from end of previous chunk
                $overlap  = mb_strlen($current) > self::OVERLAP
                    ? mb_substr($current, -self::OVERLAP)
                    : $current;
                $current  = trim($overlap.' '.$sentence);
            }
        }

        if ($current !== '') {
            $chunks[] = trim($current);
        }

        return array_values(array_filter($chunks, fn($c) => mb_strlen(trim($c)) > 20));
    }
}
