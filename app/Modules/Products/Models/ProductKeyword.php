<?php

namespace App\Modules\Products\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductKeyword extends Model
{
    public $timestamps = false;

    protected $fillable = ['product_id', 'keyword', 'source', 'frequency'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
