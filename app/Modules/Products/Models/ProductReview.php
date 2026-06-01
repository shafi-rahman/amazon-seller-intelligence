<?php

namespace App\Modules\Products\Models;

use App\Modules\Imports\Models\ImportBatch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductReview extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'product_id', 'import_batch_id', 'external_id', 'reviewer_name',
        'rating', 'title', 'body', 'verified_purchase', 'review_date', 'helpful_votes',
    ];

    protected function casts(): array
    {
        return [
            'review_date'       => 'date',
            'verified_purchase' => 'boolean',
            'created_at'        => 'datetime',
        ];
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
