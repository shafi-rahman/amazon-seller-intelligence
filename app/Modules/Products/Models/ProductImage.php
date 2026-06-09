<?php

namespace App\Modules\Products\Models;

use App\Modules\Workspace\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductImage extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'public_id', 'product_id', 'workspace_id',
        'storage_path', 'file_name', 'display_order', 'is_primary',
    ];

    protected function casts(): array
    {
        return [
            'is_primary'  => 'boolean',
            'created_at'  => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (ProductImage $image) {
            if (empty($image->public_id)) {
                $image->public_id = (string) Str::uuid();
            }
        });
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function url(int $ttlHours = 24): ?string
    {
        if (!$this->storage_path) return null;
        try {
            return Storage::disk('s3')->temporaryUrl(
                $this->storage_path,
                now()->addHours($ttlHours)
            );
        } catch (\Throwable) {
            return null;
        }
    }

    public function deleteFromStorage(): void
    {
        if ($this->storage_path) {
            Storage::disk('s3')->delete($this->storage_path);
        }
    }
}
