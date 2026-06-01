<?php

namespace App\Modules\AI\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConversationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'title'        => $this->title,
            'context_type' => $this->context_type,
            'context_id'   => $this->context_id,
            'created_at'   => $this->created_at->toISOString(),
            'updated_at'   => $this->updated_at->toISOString(),
            'messages'     => $this->whenLoaded('messages', fn() =>
                $this->messages->map(fn($m) => [
                    'id'               => $m->id,
                    'role'             => $m->role,
                    'content'          => $m->content,
                    'provider'         => $m->provider,
                    'model'            => $m->model,
                    'rag_sources'      => $m->rag_sources ?? [],
                    'prompt_tokens'    => $m->prompt_tokens,
                    'completion_tokens'=> $m->completion_tokens,
                    'created_at'       => $m->created_at?->toISOString(),
                ])
            ),
        ];
    }
}
