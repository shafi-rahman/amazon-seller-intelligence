<?php

namespace App\Modules\Products\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductAnalysis extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'product_id', 'analysis_type', 'ai_provider', 'ai_model',
        'prompt_tokens', 'completion_tokens', 'analysis_data',
    ];

    protected function casts(): array
    {
        return [
            'analysis_data' => 'array',
            'created_at'    => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
