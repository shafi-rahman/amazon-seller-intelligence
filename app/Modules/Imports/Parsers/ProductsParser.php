<?php

namespace App\Modules\Imports\Parsers;

use App\Modules\Imports\Models\ImportBatch;
use App\Modules\Imports\Models\ImportError;
use App\Modules\Products\Models\Product;
use League\Csv\Reader;

class ProductsParser
{
    private const CHUNK = 200;

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

                $asin = $this->nullIfEmpty($get('asin') ?? '');
                if (empty($asin)) {
                    throw new \InvalidArgumentException('ASIN is required');
                }

                $records[] = [
                    'workspace_id'    => $batch->workspace_id,
                    'import_batch_id' => $batch->id,
                    'asin'            => strtoupper($asin),
                    'sku'             => $this->nullIfEmpty($get('sku') ?? ''),
                    'title'           => $get('title'),
                    'brand'           => $get('brand'),
                    'category'        => $get('category'),
                    'bullet_1'        => $get('bullet_1'),
                    'bullet_2'        => $get('bullet_2'),
                    'bullet_3'        => $get('bullet_3'),
                    'bullet_4'        => $get('bullet_4'),
                    'bullet_5'        => $get('bullet_5'),
                    'description'     => $get('description'),
                    'price'           => $this->nullFloat($get('price')),
                    'currency'        => 'INR',
                    'rating'          => $this->nullFloat($get('rating')),
                    'review_count'    => (int) ($get('review_count') ?: 0),
                    'source_type'     => 'csv',
                    'raw_data'        => json_encode($row),
                    'created_at'      => now()->toDateTimeString(),
                    'updated_at'      => now()->toDateTimeString(),
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
            Product::upsert(
                $records,
                ['workspace_id', 'asin'],
                ['sku', 'title', 'brand', 'category', 'bullet_1', 'bullet_2',
                 'bullet_3', 'bullet_4', 'bullet_5', 'description', 'price',
                 'rating', 'review_count', 'import_batch_id', 'updated_at']
            );
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
        if (isset($row[$dbCol])) {
            $v = trim((string) $row[$dbCol]);
            return $v === '' ? null : $v;
        }
        return null;
    }

    private function nullIfEmpty(string $v): ?string
    {
        $v = trim($v);
        return $v === '' ? null : $v;
    }

    private function nullFloat(?string $v): ?float
    {
        if ($v === null) {
            return null;
        }
        $clean = preg_replace('/[₹$€£,\s]/', '', $v);
        return is_numeric($clean) ? (float) $clean : null;
    }
}
