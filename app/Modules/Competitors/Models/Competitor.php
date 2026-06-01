<?php

namespace App\Modules\Competitors\Models;

use App\Modules\Imports\Models\ImportBatch;
use App\Modules\Products\Models\Product;
use App\Modules\Workspace\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Competitor extends Model
{
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
}
