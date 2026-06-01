<?php

namespace App\Modules\Imports\Parsers;

use App\Modules\Finance\Models\BankTransaction;
use App\Modules\Imports\Models\ImportBatch;
use App\Modules\Imports\Models\ImportError;
use App\Modules\Imports\Services\ColumnDetector;
use Illuminate\Support\Carbon;
use League\Csv\Reader;

class BankStatementParser
{
    private const CHUNK = 500;

    public function process(ImportBatch $batch, \Closure $onProgress): void
    {
        $stream = \Storage::disk('s3')->readStream($batch->storage_path);
        $reader = Reader::createFromStream($stream);

        // Auto-detect delimiter (most bank statements are CSV or Excel-exported CSV)
        $delimiter = $batch->meta['delimiter'] ?? ',';
        $reader->setDelimiter($delimiter);

        // Find the header row — first row where one cell looks like a date column
        $headerOffset = $this->findHeaderRow($reader);
        $reader->setHeaderOffset($headerOffset);

        // Use confirmed mapping from column_mapping, or auto-suggest
        $mapping = $batch->column_mapping;
        if (empty($mapping)) {
            $detector = new ColumnDetector();
            $mapping  = $detector->suggestBankColumns(array_keys(iterator_to_array($reader->getRecords())[0] ?? []));
        }

        $bankName = $batch->meta['bank_name'] ?? null;

        $rows   = [];
        $offset = 0;

        foreach ($reader->getRecords() as $record) {
            $rows[] = $record;

            if (count($rows) === self::CHUNK) {
                [$ok, $fail] = $this->upsertChunk($rows, $mapping, $batch, $bankName);
                $offset += count($rows);
                $onProgress($ok, $fail, $offset);
                $rows = [];
            }
        }

        if (!empty($rows)) {
            [$ok, $fail] = $this->upsertChunk($rows, $mapping, $batch, $bankName);
            $onProgress($ok, $fail, $offset + count($rows));
        }
    }

    private function upsertChunk(array $rows, array $mapping, ImportBatch $batch, ?string $bankName): array
    {
        $ok = 0; $fail = 0;
        $records = [];

        foreach ($rows as $i => $row) {
            try {
                $get = fn(string $col) => $this->getValue($row, $mapping, $col);

                $txnDate = $this->parseDate($get('transaction_date'));
                if ($txnDate === null) {
                    continue; // skip header-looking rows (totals, empty lines)
                }

                // Handle single-column "amount" with sign
                $debit  = (float) abs((float) $this->stripCurrency($get('debit_amount') ?? '0'));
                $credit = (float) abs((float) $this->stripCurrency($get('credit_amount') ?? '0'));

                if ($debit === 0.0 && $credit === 0.0 && isset($mapping['amount'])) {
                    $amount = (float) $this->stripCurrency($get('amount') ?? '0');
                    $debit  = $amount < 0 ? abs($amount) : 0.0;
                    $credit = $amount > 0 ? $amount : 0.0;
                }

                $records[] = [
                    'workspace_id'    => $batch->workspace_id,
                    'import_batch_id' => $batch->id,
                    'transaction_date'=> $txnDate,
                    'value_date'      => $this->parseDate($get('value_date')),
                    'description'     => $get('description'),
                    'debit_amount'    => $debit,
                    'credit_amount'   => $credit,
                    'balance'         => $this->nullFloat($get('balance')),
                    'reference'       => $get('reference'),
                    'bank_name'       => $bankName,
                    'raw_row'         => json_encode($row),
                    'created_at'      => now()->toDateTimeString(),
                ];
                $ok++;
            } catch (\Throwable $e) {
                $fail++;
                ImportError::create([
                    'import_batch_id' => $batch->id,
                    'row_number'      => $i + 1,
                    'raw_data'        => $row,
                    'error_type'      => 'parse_error',
                    'error_message'   => $e->getMessage(),
                ]);
            }
        }

        if (!empty($records)) {
            BankTransaction::insert($records);
        }

        return [$ok, $fail];
    }

    private function findHeaderRow(Reader $reader): int
    {
        $dateWords = ['date', 'txn', 'transaction', 'posting', 'value'];
        foreach ($reader->getRecords() as $offset => $row) {
            $values = array_map('strtolower', array_values($row));
            foreach ($values as $v) {
                foreach ($dateWords as $word) {
                    if (str_contains($v, $word)) {
                        return $offset;
                    }
                }
            }
            if ($offset > 10) {
                break;
            }
        }
        return 0;
    }

    private function getValue(array $row, array $mapping, string $dbCol): ?string
    {
        $csvCol = array_search($dbCol, $mapping, true);
        if ($csvCol !== false && isset($row[$csvCol])) {
            $v = trim((string) $row[$csvCol]);
            return $v === '' ? null : $v;
        }
        return null;
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

    private function stripCurrency(?string $v): string
    {
        if ($v === null) {
            return '0';
        }
        return preg_replace('/[₹$€£,\s]/', '', $v) ?: '0';
    }

    private function nullFloat(?string $v): ?float
    {
        if ($v === null || trim($v) === '') {
            return null;
        }
        $clean = $this->stripCurrency($v);
        return is_numeric($clean) ? (float) $clean : null;
    }
}
