<?php

namespace App\Modules\Finance\Models;

use App\Modules\Imports\Models\ImportBatch;
use App\Modules\Workspace\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Settlement extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'workspace_id', 'import_batch_id', 'settlement_id', 'settlement_start_date',
        'settlement_end_date', 'deposit_date', 'deposited_amount', 'currency',
        'transaction_type', 'order_id', 'merchant_order_id', 'adjustment_id',
        'shipment_id', 'marketplace_name', 'amount_type', 'amount_description',
        'amount', 'fulfillment_id', 'posted_date', 'posted_datetime',
        'sku', 'quantity_purchased', 'raw_row',
    ];

    protected function casts(): array
    {
        return [
            'settlement_start_date' => 'date',
            'settlement_end_date'   => 'date',
            'deposit_date'          => 'date',
            'posted_date'           => 'date',
            'posted_datetime'       => 'datetime',
            'deposited_amount'      => 'decimal:2',
            'amount'                => 'decimal:2',
            'raw_row'               => 'array',
            'created_at'            => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class);
    }
}
