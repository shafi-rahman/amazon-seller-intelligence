<?php

namespace App\Modules\Imports\Parsers;

use App\Modules\Finance\Models\Settlement;
use App\Modules\Imports\Models\ImportBatch;
use App\Modules\Imports\Models\ImportError;
use Illuminate\Support\Carbon;
use League\Csv\Reader;

class SettlementsParser
{
    private const CHUNK = 500;

    public function process(ImportBatch $batch, \Closure $onProgress): void
    {
        $stream  = \Storage::disk('s3')->readStream($batch->storage_path);
        $content = stream_get_contents($stream);

        // Settlements are tab-separated with a metadata header block
        // Find the row containing the column header by scanning for 'settlement-id'
        $lines      = explode("\n", $content);
        $headerRow  = null;
        $headerIdx  = 0;
        $metaFields = [];

        foreach ($lines as $i => $line) {
            $lower = strtolower($line);

            // Extract metadata from header block (first 6 rows: key\tvalue)
            if ($i < 10 && str_contains($line, "\t")) {
                $parts = explode("\t", $line, 2);
                if (count($parts) === 2) {
                    $metaFields[strtolower(trim($parts[0]))] = trim($parts[1]);
                }
            }

            if (str_contains($lower, 'settlement-id') && str_contains($lower, 'transaction-type')) {
                $headerRow = $line;
                $headerIdx = $i;
                break;
            }
        }

        if ($headerRow === null) {
            $batch->markFailed('Could not detect column header row in settlement file');
            return;
        }

        // Rebuild CSV from header row onwards
        $dataLines = array_slice($lines, $headerIdx);
        $csv       = implode("\n", $dataLines);

        $reader = Reader::createFromString($csv);
        $reader->setDelimiter("\t");
        $reader->setHeaderOffset(0);

        // Shared settlement metadata
        $settlementId    = $metaFields['settlement id'] ?? '';
        $startDate       = $this->parseDate($metaFields['settlement start date'] ?? '');
        $endDate         = $this->parseDate($metaFields['settlement end date'] ?? '');
        $depositDate     = $this->parseDate($metaFields['deposit date'] ?? '');
        $depositedAmount = (float) str_replace(',', '', $metaFields['total amount'] ?? '0');
        $currency        = $metaFields['currency'] ?? 'INR';

        $rows   = [];
        $offset = 0;

        foreach ($reader->getRecords() as $record) {
            // Skip rows where the amount is 0 and order_id is empty (summary rows)
            if (empty(trim((string)($record['order-id'] ?? ''))) &&
                empty(trim((string)($record['transaction-type'] ?? '')))) {
                continue;
            }

            $rows[] = array_merge($record, compact(
                'settlementId', 'startDate', 'endDate',
                'depositDate', 'depositedAmount', 'currency'
            ));

            if (count($rows) === self::CHUNK) {
                [$ok, $fail] = $this->upsertChunk($rows, $batch);
                $offset += count($rows);
                $onProgress($ok, $fail, $offset);
                $rows = [];
            }
        }

        if (!empty($rows)) {
            [$ok, $fail] = $this->upsertChunk($rows, $batch);
            $onProgress($ok, $fail, $offset + count($rows));
        }
    }

    private function upsertChunk(array $rows, ImportBatch $batch): array
    {
        $ok = 0; $fail = 0;
        $records = [];

        foreach ($rows as $i => $row) {
            try {
                $records[] = [
                    'workspace_id'          => $batch->workspace_id,
                    'import_batch_id'       => $batch->id,
                    'settlement_id'         => $row['settlementId'] ?? ($row['settlement-id'] ?? ''),
                    'settlement_start_date' => $row['startDate'] ?? null,
                    'settlement_end_date'   => $row['endDate'] ?? null,
                    'deposit_date'          => $row['depositDate'] ?? null,
                    'deposited_amount'      => $row['depositedAmount'] ?? null,
                    'currency'              => $row['currency'] ?? 'INR',
                    'transaction_type'      => $row['transaction-type'] ?? null,
                    'order_id'              => $this->nullIfEmpty($row['order-id'] ?? ''),
                    'merchant_order_id'     => $this->nullIfEmpty($row['merchant-order-id'] ?? ''),
                    'adjustment_id'         => $this->nullIfEmpty($row['adjustment-id'] ?? ''),
                    'shipment_id'           => $this->nullIfEmpty($row['shipment-id'] ?? ''),
                    'marketplace_name'      => $this->nullIfEmpty($row['marketplace-name'] ?? ''),
                    'amount_type'           => $this->nullIfEmpty($row['amount-type'] ?? ''),
                    'amount_description'    => $this->nullIfEmpty($row['amount-description'] ?? ''),
                    'amount'                => (float) ($row['amount'] ?? 0),
                    'fulfillment_id'        => $this->nullIfEmpty($row['fulfillment-id'] ?? ''),
                    'posted_date'           => $this->parseDate($row['posted-date'] ?? ''),
                    'posted_datetime'       => $this->parseDate($row['posted-date-time'] ?? ''),
                    'sku'                   => $this->nullIfEmpty($row['sku'] ?? ''),
                    'quantity_purchased'    => $this->nullInt($row['quantity-purchased'] ?? ''),
                    'raw_row'               => json_encode(array_diff_key($row, array_flip([
                        'settlementId','startDate','endDate','depositDate','depositedAmount','currency'
                    ]))),
                    'created_at'            => now()->toDateTimeString(),
                ];
                $ok++;
            } catch (\Throwable $e) {
                $fail++;
                ImportError::create([
                    'import_batch_id' => $batch->id,
                    'row_number'      => $i + 1,
                    'raw_data'        => array_slice($row, 0, 20), // truncate large rows
                    'error_type'      => 'parse_error',
                    'error_message'   => $e->getMessage(),
                ]);
            }
        }

        if (!empty($records)) {
            Settlement::insert($records); // settlements are append-only
        }

        return [$ok, $fail];
    }

    private function parseDate(?string $v): ?string
    {
        if (empty($v)) {
            return null;
        }
        try {
            return Carbon::parse($v)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function nullIfEmpty(string $v): ?string
    {
        $v = trim($v);
        return $v === '' ? null : $v;
    }

    private function nullInt(?string $v): ?int
    {
        if ($v === null || trim($v) === '') {
            return null;
        }
        return (int) $v;
    }
}
