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

    public function keywords(): HasMany
    {
        return $this->hasMany(ProductKeyword::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(ProductReview::class);
    }

    public function analyses(): HasMany
    {
        return $this->hasMany(ProductAnalysis::class);
    }

    public function latestAnalysis(string $type): ?ProductAnalysis
    {
        return $this->analyses()->where('analysis_type', $type)->latest('created_at')->first();
    }

    public function bullets(): array
    {
        return array_filter([
            $this->bullet_1, $this->bullet_2, $this->bullet_3,
            $this->bullet_4, $this->bullet_5,
        ]);
    }

    public function combinedText(): string
    {
        return implode(' ', array_filter([
            $this->title,
            implode(' ', $this->bullets()),
            $this->description,
        ]));
    }
}
