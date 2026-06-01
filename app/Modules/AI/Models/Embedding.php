<?php

namespace App\Modules\AI\Models;

use App\Modules\Workspace\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Embedding extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'embeddable_type', 'embeddable_id', 'chunk_index',
        'chunk_text', 'model', 'workspace_id',
    ];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function embeddable(): MorphTo
    {
        return $this->morphTo();
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
