<?php

namespace App\Modules\Finance\Models;

use App\Modules\Imports\Models\ImportBatch;
use App\Modules\Workspace\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GstTransaction extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'workspace_id', 'import_batch_id', 'transaction_type', 'invoice_date',
        'invoice_number', 'order_id', 'transaction_id', 'asin', 'sku',
        'product_name', 'quantity', 'ship_from_state', 'ship_to_state',
        'taxable_value', 'igst_rate', 'igst_amount', 'cgst_rate', 'cgst_amount',
        'sgst_rate', 'sgst_amount', 'cess_rate', 'cess_amount',
        'invoice_amount', 'irn', 'hsn_sac', 'raw_row',
    ];

    protected function casts(): array
    {
        return [
            'invoice_date'   => 'date',
            'taxable_value'  => 'decimal:2',
            'igst_amount'    => 'decimal:2',
            'cgst_amount'    => 'decimal:2',
            'sgst_amount'    => 'decimal:2',
            'cess_amount'    => 'decimal:2',
            'invoice_amount' => 'decimal:2',
            'raw_row'        => 'array',
            'created_at'     => 'datetime',
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
