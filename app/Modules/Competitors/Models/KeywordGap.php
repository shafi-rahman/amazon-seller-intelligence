<?php

namespace App\Modules\Competitors\Models;

use App\Modules\Products\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KeywordGap extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'product_id', 'competitor_id', 'keyword', 'gap_type',
        'our_frequency', 'their_frequency', 'priority_score',
    ];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function competitor(): BelongsTo
    {
        return $this->belongsTo(Competitor::class);
    }
}
