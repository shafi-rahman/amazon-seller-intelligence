<?php

namespace App\Modules\Competitors\Models;

use App\Modules\Imports\Models\ImportBatch;
use App\Modules\Products\Models\Product;
use App\Modules\Workspace\Models\Workspace;
use App\Traits\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Competitor extends Model
{
    use HasPublicId;

    protected $fillable = [
        'workspace_id', 'product_id', 'import_batch_id', 'asin', 'title', 'brand',
        'category', 'bullet_1', 'bullet_2', 'bullet_3', 'bullet_4', 'bullet_5',
        'description', 'price', 'currency', 'rating', 'review_count',
        'source_type', 'raw_html', 'parse_confidence', 'last_analyzed_at',
    ];

    protected function casts(): array
    {
        return [
            'price'            => 'decimal:2',
            'rating'           => 'decimal:2',
            'parse_confidence' => 'array',
            'last_analyzed_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class);
    }

    public function keywords(): HasMany
    {
        return $this->hasMany(CompetitorKeyword::class);
    }

    public function keywordGaps(): HasMany
    {
        return $this->hasMany(KeywordGap::class);
    }

    public function benchmark(): HasOne
    {
        return $this->hasOne(CompetitorBenchmark::class);
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

    public function isLowConfidenceField(string $field): bool
    {
        $confidence = $this->parse_confidence ?? [];
        return isset($confidence[$field]) && $confidence[$field] < 60;
    }
}
