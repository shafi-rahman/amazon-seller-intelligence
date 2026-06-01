<?php

namespace App\Modules\Reconciliation\Models;

use App\Modules\Finance\Models\BankTransaction;
use App\Modules\Finance\Models\Order;
use App\Modules\Finance\Models\Settlement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReconciliationMatch extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'reconciliation_run_id', 'order_id', 'settlement_id', 'bank_transaction_id',
        'match_type', 'match_confidence', 'status', 'mismatch_amount', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'match_confidence' => 'decimal:2',
            'mismatch_amount'  => 'decimal:2',
            'created_at'       => 'datetime',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(ReconciliationRun::class, 'reconciliation_run_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function settlement(): BelongsTo
    {
        return $this->belongsTo(Settlement::class);
    }

    public function bankTransaction(): BelongsTo
    {
        return $this->belongsTo(BankTransaction::class);
    }
}
