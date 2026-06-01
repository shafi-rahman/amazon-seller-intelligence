<?php

namespace App\Modules\Imports\Parsers;

use App\Modules\Finance\Models\Order;
use App\Modules\Imports\Models\ImportBatch;
use App\Modules\Imports\Models\ImportError;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class OrdersParser
{
    private const CHUNK = 500;

    public function process(ImportBatch $batch, \Closure $onProgress): void
    {
        $mapping = $batch->column_mapping ?? [];
        $path    = $batch->storage_path;

        $stream = \Storage::disk('s3')->readStream($path);
        $reader = \League\Csv\Reader::createFromStream($stream);
        $reader->setHeaderOffset(0);

        $this->detectDelimiter($reader, $batch);

        $rows   = [];
        $offset = 0;

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
                $mapped = $this->mapRow($row, $mapping);
                $this->validate($mapped);
                $records[] = array_merge($mapped, [
                    'workspace_id'    => $batch->workspace_id,
                    'import_batch_id' => $batch->id,
                    'raw_row'         => json_encode($row),
                    'created_at'      => now()->toDateTimeString(),
                ]);
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
            Order::upsert(
                $records,
                ['workspace_id', 'amazon_order_id', 'sku'],
                array_keys(array_diff_key($records[0], array_flip(['workspace_id', 'amazon_order_id', 'sku', 'import_batch_id', 'raw_row', 'created_at'])))
            );
        }

        return [$ok, $fail];
    }

    private function mapRow(array $row, array $mapping): array
    {
        $get = fn(string $col) => $this->findValue($row, $mapping, $col);

        return [
            'amazon_order_id'           => $get('amazon_order_id') ?? throw new \InvalidArgumentException('Missing amazon_order_id'),
            'merchant_order_id'         => $get('merchant_order_id'),
            'purchase_date'             => $this->parseDate($get('purchase_date')) ?? throw new \InvalidArgumentException('Missing purchase_date'),
            'last_updated_date'         => $this->parseDate($get('last_updated_date')),
            'order_status'              => $get('order_status') ?? 'Unknown',
            'fulfillment_channel'       => $get('fulfillment_channel'),
            'sales_channel'             => $get('sales_channel'),
            'ship_service_level'        => $get('ship_service_level'),
            'sku'                       => $get('sku') ?? '',
            'asin'                      => $get('asin'),
            'product_name'              => $get('product_name'),
            'item_status'               => $get('item_status'),
            'quantity'                  => (int) ($get('quantity') ?: 1),
            'currency'                  => $get('currency') ?: 'INR',
            'item_price'                => (float) ($get('item_price') ?: 0),
            'item_tax'                  => (float) ($get('item_tax') ?: 0),
            'shipping_price'            => (float) ($get('shipping_price') ?: 0),
            'shipping_tax'              => (float) ($get('shipping_tax') ?: 0),
            'gift_wrap_price'           => (float) ($get('gift_wrap_price') ?: 0),
            'gift_wrap_tax'             => (float) ($get('gift_wrap_tax') ?: 0),
            'item_promotion_discount'   => (float) ($get('item_promotion_discount') ?: 0),
            'ship_promotion_discount'   => (float) ($get('ship_promotion_discount') ?: 0),
            'ship_city'                 => $get('ship_city'),
            'ship_state'                => $get('ship_state'),
            'ship_postal_code'          => $get('ship_postal_code'),
            'ship_country'              => $get('ship_country'),
            'is_business_order'         => in_array(strtolower((string)$get('is_business_order')), ['true', '1', 'yes']),
        ];
    }

    private function validate(array $mapped): void
    {
        if (empty($mapped['amazon_order_id'])) {
            throw new \InvalidArgumentException('amazon_order_id is required');
        }
    }

    private function findValue(array $row, array $mapping, string $dbCol): mixed
    {
        // Try to find the CSV column that maps to this DB column
        $csvCol = array_search($dbCol, $mapping, true);
        if ($csvCol !== false && isset($row[$csvCol])) {
            $v = trim((string) $row[$csvCol]);
            return $v === '' ? null : $v;
        }
        // Fallback: try the DB column name directly
        if (isset($row[$dbCol])) {
            $v = trim((string) $row[$dbCol]);
            return $v === '' ? null : $v;
        }
        return null;
    }

    private function parseDate(?string $value): ?string
    {
        if (empty($value)) {
            return null;
        }
        try {
            return Carbon::parse($value)->toDateTimeString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function detectDelimiter(\League\Csv\Reader $reader, ImportBatch $batch): void
    {
        // Amazon reports use tab-separated .txt files sometimes
        $meta = $batch->meta ?? [];
        if (isset($meta['delimiter'])) {
            $reader->setDelimiter($meta['delimiter']);
        }
    }
}
