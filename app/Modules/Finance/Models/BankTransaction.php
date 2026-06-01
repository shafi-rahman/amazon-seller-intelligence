<?php

namespace App\Modules\Finance\Models;

use App\Modules\Imports\Models\ImportBatch;
use App\Modules\Workspace\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankTransaction extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'workspace_id', 'import_batch_id', 'transaction_date', 'value_date',
        'description', 'debit_amount', 'credit_amount', 'balance',
        'reference', 'bank_name', 'raw_row',
    ];

    protected function casts(): array
    {
        return [
            'transaction_date' => 'date',
            'value_date'       => 'date',
            'debit_amount'     => 'decimal:2',
            'credit_amount'    => 'decimal:2',
            'balance'          => 'decimal:2',
            'raw_row'          => 'array',
            'created_at'       => 'datetime',
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
