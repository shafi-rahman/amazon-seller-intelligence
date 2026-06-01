<?php

namespace App\Modules\Reconciliation\Models;

use App\Models\User;
use App\Modules\Workspace\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReconciliationRun extends Model
{
    protected $fillable = [
        'workspace_id', 'user_id', 'period_start', 'period_end',
        'status', 'summary', 'started_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'period_start'  => 'date',
            'period_end'    => 'date',
            'summary'       => 'array',
            'started_at'    => 'datetime',
            'completed_at'  => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function matches(): HasMany
    {
        return $this->hasMany(ReconciliationMatch::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(ReconciliationReport::class);
    }
}
