<?php

namespace App\Modules\Products\Services;

class KeywordExtractorService
{
    private array $stopwords = [
        'a','an','the','is','are','was','were','be','been','being','have','has','had',
        'do','does','did','will','would','could','should','may','might','shall','must',
        'can','to','of','in','for','on','with','at','by','from','as','into','through',
        'during','before','after','above','below','between','each','this','that','these',
        'those','our','your','and','or','but','if','very','just','also','only','more',
        'most','some','any','all','both','its','it','i','we','you','he','she','they',
        'me','us','him','her','them','my','his','their','what','which','who','when',
        'where','how','not','no','so','up','out','about','than','then','there','here',
        // product-specific fillers
        'product','item','pack','set','piece','unit','quality','great','good','best',
        'high','low','top','new','made','use','used','using','ideal','perfect','easy',
        'design','designed','available','color','size','weight','material',
    ];

    /**
     * Extract keywords from product listing fields and return top N by frequency.
     */
    public function extract(string $title, array $bullets, ?string $description, int $limit = 100): array
    {
        $sources = [
            'title'       => $title,
            'bullet'      => implode(' ', array_filter($bullets)),
            'description' => (string) $description,
        ];

        $allNgrams = [];

        foreach ($sources as $source => $text) {
            $tokens = $this->tokenize($text);

            foreach ($this->ngrams($tokens, 1) as $ngram) {
                $key = "{$ngram}|||{$source}";
                $allNgrams[$key] = ($allNgrams[$key] ?? 0) + 1;
            }
            foreach ($this->ngrams($tokens, 2) as $ngram) {
                $key = "{$ngram}|||{$source}";
                $allNgrams[$key] = ($allNgrams[$key] ?? 0) + 1;
            }
            foreach ($this->ngrams($tokens, 3) as $ngram) {
                $key = "{$ngram}|||{$source}";
                $allNgrams[$key] = ($allNgrams[$key] ?? 0) + 1;
            }
        }

        arsort($allNgrams);

        $results = [];
        foreach (array_slice($allNgrams, 0, $limit * 3, true) as $key => $freq) {
            [$ngram, $source] = explode('|||', $key, 2);
            $results[] = [
                'keyword'   => $ngram,
                'source'    => $source,
                'frequency' => $freq,
            ];
            if (count($results) >= $limit) {
                break;
            }
        }

        return $results;
    }

    /**
     * Determine the primary keyword — the most frequent non-trivial unigram from the title.
     */
    public function primaryKeyword(string $title): string
    {
        $tokens = $this->tokenize($title);
        if (empty($tokens)) {
            return '';
        }

        $freq = array_count_values($tokens);
        arsort($freq);

        // Prefer a bigram (more specific)
        $bigrams = $this->ngrams($tokens, 2);
        if (!empty($bigrams)) {
            return $bigrams[0];
        }

        return array_key_first($freq) ?? '';
    }

    // ─── Internal ─────────────────────────────────────────────────────────

    private function tokenize(string $text): array
    {
        // Strip HTML
        $text = strip_tags($text);

        // Lowercase
        $text = mb_strtolower($text);

        // Remove non-alphabetic (keep spaces, hyphens between words)
        $text = preg_replace('/[^a-z\s\-]/', ' ', $text);

        // Normalize whitespace
        $tokens = preg_split('/\s+/', trim($text));

        // Filter stopwords, short tokens, pure numbers
        return array_values(array_filter($tokens, function ($t) {
            return strlen($t) >= 2
                && !in_array($t, $this->stopwords, true)
                && !is_numeric($t)
                && !preg_match('/^\-+$/', $t);
        }));
    }

    private function ngrams(array $tokens, int $n): array
    {
        $result = [];
        $count  = count($tokens);

        for ($i = 0; $i <= $count - $n; $i++) {
            $ngram = implode(' ', array_slice($tokens, $i, $n));
            $result[] = $ngram;
        }

        return $result;
    }
}
