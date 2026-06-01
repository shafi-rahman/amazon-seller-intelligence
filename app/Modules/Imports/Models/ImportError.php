<?php

namespace App\Modules\Imports\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportError extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'import_batch_id', 'row_number', 'raw_data', 'error_type', 'error_message',
    ];

    protected function casts(): array
    {
        return [
            'raw_data'   => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class);
    }
}
