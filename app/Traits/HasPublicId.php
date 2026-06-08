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
     * Resolve route binding — finds model by UUID, returns 404 for non-UUID strings.
     * Prevents PostgreSQL from throwing a cast error when old integer IDs are used.
     */
    public static function findByPublicId(string $publicId, ?int $workspaceId = null): static
    {
        // Reject non-UUID format immediately with 404 (not 500)
        if (!Str::isUuid($publicId)) {
            abort(404, "Resource not found. URL format has changed — UUIDs are now required.");
        }

        $query = static::where('public_id', $publicId);
        if ($workspaceId && in_array('workspace_id', (new static)->getFillable())) {
            $query->where('workspace_id', $workspaceId);
        }

        return $query->firstOrFail();
    }

    /**
     * When a model is serialized to array (e.g. via $paginator->items()), replace
     * the integer 'id' with public_id (UUID) so that paginated() responses are
     * consistent with JsonResource responses — both return UUID as 'id'.
     */
    public function toArray(): array
    {
        $data = parent::toArray();
        if (!empty($this->public_id)) {
            $data['id'] = $this->public_id;
        }
        return $data;
    }

    /**
     * Override resolveRouteBinding to guard against invalid UUID format.
     * Called by Laravel's route model binding — protects all implicit bindings.
     */
    public function resolveRouteBinding(mixed $value, $field = null): ?static
    {
        $field = $field ?? $this->getRouteKeyName();

        if ($field === 'public_id' && !Str::isUuid((string) $value)) {
            abort(404, "Resource not found. UUIDs are required in URLs.");
        }

        return $this->where($field, $value)->first();
    }
}
