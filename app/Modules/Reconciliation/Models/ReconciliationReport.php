<?php

namespace App\Modules\Reconciliation\Models;

use App\Modules\Workspace\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReconciliationReport extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'reconciliation_run_id', 'workspace_id', 'report_type', 'report_data', 'export_path',
    ];

    protected function casts(): array
    {
        return [
            'report_data' => 'array',
            'created_at'  => 'datetime',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(ReconciliationRun::class, 'reconciliation_run_id');
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
