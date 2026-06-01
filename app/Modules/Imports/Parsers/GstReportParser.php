<?php

namespace App\Modules\Imports\Parsers;

use App\Modules\Finance\Models\GstTransaction;
use App\Modules\Imports\Models\ImportBatch;
use App\Modules\Imports\Models\ImportError;
use Illuminate\Support\Carbon;
use League\Csv\Reader;

class GstReportParser
{
    private const CHUNK = 500;

    public function process(ImportBatch $batch, \Closure $onProgress): void
    {
        $stream = \Storage::disk('s3')->readStream($batch->storage_path);
        $reader = Reader::createFromStream($stream);
        $reader->setHeaderOffset(0);

        $mapping = $batch->column_mapping ?? [];
        $rows    = [];
        $offset  = 0;

        foreach ($reader->getRecords() as $record) {
            $rows[] = $record;

            if (count($rows) === self::CHUNK) {
                [$ok, $fail] = $this->upsertChunk($rows, $mapping, $batch);
                $offset += count($rows);
                $onProgress($ok, $fail, $offset);
                $rows = [];
            }
        }

        if (!empty($rows)) {
            [$ok, $fail] = $this->upsertChunk($rows, $mapping, $batch);
            $onProgress($ok, $fail, $offset + count($rows));
        }
    }

    private function upsertChunk(array $rows, array $mapping, ImportBatch $batch): array
    {
        $ok = 0; $fail = 0;
        $records = [];

        foreach ($rows as $i => $row) {
            try {
                $get = fn(string $col) => $this->getValue($row, $mapping, $col);

                $records[] = [
                    'workspace_id'    => $batch->workspace_id,
                    'import_batch_id' => $batch->id,
                    'transaction_type'=> $get('transaction_type'),
                    'invoice_date'    => $this->parseDate($get('invoice_date')),
                    'invoice_number'  => $get('invoice_number'),
                    'order_id'        => $this->nullIfEmpty($get('order_id') ?? ''),
                    'transaction_id'  => $this->nullIfEmpty($get('transaction_id') ?? ''),
                    'asin'            => $this->nullIfEmpty($get('asin') ?? ''),
                    'sku'             => $this->nullIfEmpty($get('sku') ?? ''),
                    'product_name'    => $get('product_name'),
                    'quantity'        => $this->nullInt($get('quantity')),
                    'ship_from_state' => $get('ship_from_state'),
                    'ship_to_state'   => $get('ship_to_state'),
                    'taxable_value'   => $this->nullFloat($get('taxable_value')),
                    'igst_rate'       => $this->nullFloat($get('igst_rate')),
                    'igst_amount'     => $this->nullFloat($get('igst_amount')),
                    'cgst_rate'       => $this->nullFloat($get('cgst_rate')),
                    'cgst_amount'     => $this->nullFloat($get('cgst_amount')),
                    'sgst_rate'       => $this->nullFloat($get('sgst_rate')),
                    'sgst_amount'     => $this->nullFloat($get('sgst_amount')),
                    'cess_rate'       => $this->nullFloat($get('cess_rate')),
                    'cess_amount'     => $this->nullFloat($get('cess_amount')),
                    'invoice_amount'  => $this->nullFloat($get('invoice_amount')),
                    'irn'             => $this->nullIfEmpty($get('irn') ?? ''),
                    'hsn_sac'         => $this->nullIfEmpty($get('hsn_sac') ?? ''),
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
            GstTransaction::insert($records);
        }

        return [$ok, $fail];
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

    private function nullIfEmpty(string $v): ?string
    {
        $v = trim($v);
        return $v === '' ? null : $v;
    }

    private function nullInt(?string $v): ?int
    {
        return ($v !== null && is_numeric(trim($v))) ? (int) $v : null;
    }

    private function nullFloat(?string $v): ?float
    {
        if ($v === null) {
            return null;
        }
        $clean = preg_replace('/[₹,\s]/', '', $v);
        return is_numeric($clean) ? (float) $clean : null;
    }
}
