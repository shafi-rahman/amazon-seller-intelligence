<?php

namespace App\Modules\SEO\Models;

use App\Models\User;
use App\Modules\Products\Models\Product;
use App\Modules\Workspace\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SeoCampaign extends Model
{
    protected $fillable = [
        'product_id', 'workspace_id', 'user_id',
        'status', 'trend_data', 'ai_provider', 'ai_model', 'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'trend_data'   => 'array',
            'generated_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function posts(): HasMany
    {
        return $this->hasMany(SeoPost::class, 'campaign_id');
    }

    public function approvedPosts(): HasMany
    {
        return $this->hasMany(SeoPost::class, 'campaign_id')->where('status', 'approved');
    }
}
