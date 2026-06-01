<?php

namespace App\Modules\Workspace\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkspaceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'slug'        => $this->slug,
            'marketplace' => $this->marketplace,
            'currency'    => $this->currency,
            'settings'    => $this->settings ?? [],
            'created_at'  => $this->created_at->toISOString(),
            'members'     => $this->whenLoaded('members', fn() =>
                $this->members->map(fn($user) => [
                    'user_id' => $user->id,
                    'name'    => $user->name,
                    'email'   => $user->email,
                    'role'    => $user->pivot->role,
                ])
            ),
        ];
    }
}
