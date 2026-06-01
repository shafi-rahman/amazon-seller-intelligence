<?php

namespace App\Modules\Competitors\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompetitorKeyword extends Model
{
    public $timestamps = false;

    protected $fillable = ['competitor_id', 'keyword', 'source', 'frequency'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function competitor(): BelongsTo
    {
        return $this->belongsTo(Competitor::class);
    }
}
