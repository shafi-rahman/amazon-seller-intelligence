<?php

namespace App\Modules\Workspace\Models;

use App\Models\User;
use Database\Factories\WorkspaceFactory;
use App\Traits\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Workspace extends Model
{
    use HasPublicId;

    /** @use HasFactory<WorkspaceFactory> */
    use HasFactory;

    // Module models live outside the App\Models namespace, so Laravel's default
    // factory-name guess (Database\Factories\Modules\...) misses. Point it explicitly.
    protected static function newFactory(): WorkspaceFactory
    {
        return WorkspaceFactory::new();
    }

    protected $fillable = [
        'name',
        'slug',
        'owner_id',
        'marketplace',
        'currency',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Workspace $workspace) {
            if (empty($workspace->slug)) {
                $workspace->slug = Str::slug($workspace->name);
            }
        });
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'workspace_users')
            ->withPivot('role')
            ->using(WorkspaceUser::class);
    }

    public function hasMember(User $user): bool
    {
        return $this->owner_id === $user->id
            || $this->members()->where('user_id', $user->id)->exists();
    }
}
