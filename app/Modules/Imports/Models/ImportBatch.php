<?php

namespace App\Modules\Imports\Models;

use App\Models\User;
use App\Modules\Workspace\Models\Workspace;
use Database\Factories\ImportBatchFactory;
use App\Traits\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImportBatch extends Model
{
    use HasPublicId;

    /** @use HasFactory<ImportBatchFactory> */
    use HasFactory;

    // Module models live outside App\Models, so the default factory-name guess
    // misses — point it explicitly.
    protected static function newFactory(): ImportBatchFactory
    {
        return ImportBatchFactory::new();
    }

    protected $fillable = [
        'workspace_id', 'user_id', 'type', 'original_filename', 'storage_path',
        'file_size_bytes', 'status', 'total_rows', 'processed_rows', 'failed_rows',
        'column_mapping', 'meta', 'started_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'column_mapping' => 'array',
            'meta'           => 'array',
            'started_at'     => 'datetime',
            'completed_at'   => 'datetime',
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

    public function errors(): HasMany
    {
        return $this->hasMany(ImportError::class);
    }

    public function incrementProgress(int $processed, int $failed = 0): void
    {
        $this->increment('processed_rows', $processed);
        if ($failed > 0) {
            $this->increment('failed_rows', $failed);
        }
    }

    public function markCompleted(): void
    {
        $this->update([
            'status'       => $this->failed_rows > 0 && $this->processed_rows === 0 ? 'failed' : ($this->failed_rows > 0 ? 'partial' : 'completed'),
            'completed_at' => now(),
        ]);
    }

    public function markFailed(string $reason): void
    {
        $this->update([
            'status'       => 'failed',
            'completed_at' => now(),
            'meta'         => array_merge($this->meta ?? [], ['error' => $reason]),
        ]);
    }
}
