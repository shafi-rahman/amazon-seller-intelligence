<?php

namespace App\Modules\AI\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiMessage extends Model
{
    protected $table = 'ai_messages';

    public $timestamps = false;

    protected $fillable = [
        'conversation_id', 'role', 'content', 'provider', 'model',
        'prompt_tokens', 'completion_tokens', 'rag_sources',
    ];

    protected function casts(): array
    {
        return [
            'rag_sources' => 'array',
            'created_at'  => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiConversation::class, 'conversation_id');
    }
}
