<?php

namespace App\Modules\Finance\Services;

use App\Modules\Finance\Models\BankTransaction;
use App\Modules\Imports\Models\ImportBatch;
use Illuminate\Support\Facades\DB;

class UtrExtractorService
{
    // Patterns ordered by specificity
    private array $patterns = [
        // NEFT/RTGS UTR — 22-char alphanumeric
        'utr'        => '/\bUTR[:\s#]*([A-Z0-9]{10,22})\b/i',
        // IMPS reference number
        'imps'       => '/\bIMPS[:\s#]*(\d{12,15})\b/i',
        // Amazon settlement ID in description (numeric, 10–13 digits)
        'settlement' => '/\b(IN\d{10,12}|[0-9]{10,13})\b/',
        // Amazon Pay narration pattern
        'amazon_pay' => '/AMAZON\s?PAY/i',
        // Generic Amazon identifier
        'amazon'     => '/\bAMAZON\b/i',
    ];

    public function processImportBatch(ImportBatch $batch): void
    {
        BankTransaction::where('import_batch_id', $batch->id)
            ->chunkById(500, function ($rows) {
                $updates = [];
                foreach ($rows as $tx) {
                    $ref = $this->extract($tx->description ?? '');
                    if ($ref !== null) {
                        $updates[] = ['id' => $tx->id, 'reference' => $ref];
                    }
                }
                foreach ($updates as $update) {
                    BankTransaction::where('id', $update['id'])
                        ->update(['reference' => $update['reference']]);
                }
            });
    }

    public function extract(string $description): ?string
    {
        // Try UTR first (most specific)
        if (preg_match($this->patterns['utr'], $description, $m)) {
            return 'UTR:' . strtoupper($m[1]);
        }

        // IMPS reference
        if (preg_match($this->patterns['imps'], $description, $m)) {
            return 'IMPS:' . $m[1];
        }

        // Amazon settlement ID pattern
        if (preg_match($this->patterns['settlement'], $description, $m)) {
            // Only capture if description looks Amazon-related
            if (preg_match($this->patterns['amazon'], $description)) {
                return 'SETT:' . $m[1];
            }
        }

        return null;
    }

    public function isAmazonCredit(string $description): bool
    {
        return (bool) preg_match($this->patterns['amazon'], $description);
    }
}
