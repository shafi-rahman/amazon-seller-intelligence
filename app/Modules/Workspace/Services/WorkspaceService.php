<?php

namespace App\Modules\Workspace\Services;

use App\Models\AuditLog;
use App\Models\User;
use App\Modules\Workspace\Models\Workspace;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class WorkspaceService
{
    public function listForUser(User $user): Collection
    {
        return Workspace::where('owner_id', $user->id)
            ->orWhereHas('members', fn($q) => $q->where('user_id', $user->id))
            ->get();
    }

    public function create(User $user, array $data): Workspace
    {
        $workspace = Workspace::create([
            'name'        => $data['name'],
            'slug'        => $this->uniqueSlug($data['name']),
            'owner_id'    => $user->id,
            'marketplace' => $data['marketplace'] ?? 'IN',
            'currency'    => $data['currency'] ?? 'INR',
            'settings'    => $data['settings'] ?? [],
        ]);

        $workspace->members()->attach($user->id, ['role' => 'owner']);

        AuditLog::create([
            'user_id'     => $user->id,
            'action'      => 'workspace.create',
            'entity_type' => Workspace::class,
            'entity_id'   => $workspace->id,
            'new_values'  => ['name' => $workspace->name],
            'ip_address'  => request()->ip(),
            'user_agent'  => request()->userAgent(),
        ]);

        return $workspace->load('members');
    }

    public function update(Workspace $workspace, array $data): Workspace
    {
        $workspace->update(array_filter([
            'name'        => $data['name'] ?? null,
            'marketplace' => $data['marketplace'] ?? null,
            'currency'    => $data['currency'] ?? null,
            'settings'    => $data['settings'] ?? null,
        ], fn($v) => $v !== null));

        return $workspace->fresh();
    }

    public function inviteMember(Workspace $workspace, string $email, string $role = 'viewer'): void
    {
        $user = User::where('email', $email)->firstOrFail();

        $workspace->members()->syncWithoutDetaching([
            $user->id => ['role' => $role],
        ]);
    }

    public function removeMember(Workspace $workspace, int $userId): void
    {
        abort_if($workspace->owner_id === $userId, 422, 'Cannot remove the workspace owner.');

        $workspace->members()->detach($userId);
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $i    = 1;

        while (Workspace::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }
}
