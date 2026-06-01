<?php

namespace App\Modules\Reports\Models;

use App\Models\User;
use App\Modules\Workspace\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Report extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'workspace_id', 'user_id', 'type', 'title', 'parameters',
        'status', 'file_path', 'file_format', 'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'parameters'   => 'array',
            'generated_at' => 'datetime',
            'created_at'   => 'datetime',
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

    public function markCompleted(string $filePath, string $format): void
    {
        $this->update([
            'status'       => 'completed',
            'file_path'    => $filePath,
            'file_format'  => $format,
            'generated_at' => now(),
        ]);
    }

    public function markFailed(string $reason = ''): void
    {
        $this->update([
            'status'     => 'failed',
            'parameters' => array_merge($this->parameters ?? [], ['error' => $reason]),
        ]);
    }
}
