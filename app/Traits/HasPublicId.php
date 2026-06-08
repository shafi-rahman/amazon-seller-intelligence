<?php

namespace App\Traits;

use Illuminate\Support\Str;

/**
 * Adds a `public_id` (UUID) column used in all public-facing URLs.
 * Integer primary keys are kept for database performance and FK relations.
 * Route model binding uses public_id so URLs never expose sequential integers.
 *
 * Usage:
 *   - Add `public_id` column via migration (see add_public_id_to_all_tables migration)
 *   - Use the trait in the model
 *   - Laravel's route model binding will automatically use public_id
 */
trait HasPublicId
{
    protected static function bootHasPublicId(): void
    {
        static::creating(function ($model) {
            if (empty($model->public_id)) {
                $model->public_id = (string) Str::uuid();
            }
        });
    }

    /**
     * Route model binding uses public_id (UUID) instead of integer id.
     * This means /products/01234567-89ab-... instead of /products/42
     */
    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    /**
     * Resolve route binding — finds model by UUID, scoped to workspace if needed.
     */
    public static function findByPublicId(string $publicId, ?int $workspaceId = null): static
    {
        $query = static::where('public_id', $publicId);
        if ($workspaceId && in_array('workspace_id', (new static)->getFillable())) {
            $query->where('workspace_id', $workspaceId);
        }
        return $query->firstOrFail();
    }
}
