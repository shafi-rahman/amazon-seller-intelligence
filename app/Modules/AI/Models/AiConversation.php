<?php

namespace App\Modules\AI\Models;

use App\Models\User;
use App\Modules\Workspace\Models\Workspace;
use App\Traits\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiConversation extends Model
{
    use HasPublicId;

    protected $table = 'ai_conversations';

    protected $fillable = [
        'workspace_id', 'user_id', 'title', 'context_type', 'context_id',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(AiMessage::class, 'conversation_id')->orderBy('created_at');
    }

    public function lastMessage(): HasMany
    {
        return $this->hasMany(AiMessage::class, 'conversation_id')->orderByDesc('created_at')->limit(1);
    }
}
