<?php

namespace App\Modules\Products\Models;

use App\Modules\Imports\Models\ImportBatch;
use App\Modules\Workspace\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $fillable = [
        'workspace_id', 'import_batch_id', 'asin', 'sku', 'title', 'brand',
        'category', 'sub_category', 'bullet_1', 'bullet_2', 'bullet_3',
        'bullet_4', 'bullet_5', 'description', 'price', 'currency',
        'rating', 'review_count', 'listing_score', 'source_type',
        'last_analyzed_at', 'raw_data',
    ];

    protected function casts(): array
    {
        return [
            'price'           => 'decimal:2',
            'rating'          => 'decimal:2',
            'last_analyzed_at'=> 'datetime',
            'raw_data'        => 'array',
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

    public function competitors(): HasMany
    {
        return $this->hasMany(\App\Modules\Competitors\Models\Competitor::class);
    }
}
