<?php

namespace App\Modules\Identity\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->id,
            'name'               => $this->name,
            'email'              => $this->email,
            'role'               => $this->role,
            'email_verified_at'  => $this->email_verified_at?->toISOString(),
            'created_at'         => $this->created_at->toISOString(),
            'workspaces'         => $this->whenLoaded('workspaces', fn() =>
                $this->workspaces->map(fn($ws) => [
                    'id'          => $ws->id,
                    'name'        => $ws->name,
                    'slug'        => $ws->slug,
                    'marketplace' => $ws->marketplace,
                    'currency'    => $ws->currency,
                    'role'        => $ws->pivot->role,
                ])
            ),
        ];
    }
}
