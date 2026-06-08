<?php

namespace App\Modules\Settings\Models;

use App\Modules\Workspace\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class SocialAccount extends Model
{
    protected $fillable = [
        'workspace_id', 'platform', 'account_name', 'account_id',
        'access_token', 'access_token_secondary',
        'token_expires_at', 'is_active', 'is_connected', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'token_expires_at' => 'datetime',
            'is_active'        => 'boolean',
            'is_connected'     => 'boolean',
            'meta'             => 'array',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    // ─── Encrypted token accessors ─────────────────────────────────────────

    public function setAccessTokenAttribute(?string $value): void
    {
        $this->attributes['access_token'] = $value ? Crypt::encryptString($value) : null;
    }

    public function getAccessTokenAttribute(?string $value): ?string
    {
        if (!$value) return null;
        try {
            return Crypt::decryptString($value);
        } catch (\Throwable) {
            return null;
        }
    }

    public function setAccessTokenSecondaryAttribute(?string $value): void
    {
        $this->attributes['access_token_secondary'] = $value ? Crypt::encryptString($value) : null;
    }

    public function getAccessTokenSecondaryAttribute(?string $value): ?string
    {
        if (!$value) return null;
        try {
            return Crypt::decryptString($value);
        } catch (\Throwable) {
            return null;
        }
    }

    // ─── Platform-specific meta getters ───────────────────────────────────

    public function pageId(): ?string      { return $this->meta['page_id'] ?? $this->account_id; }
    public function igUserId(): ?string    { return $this->meta['ig_user_id'] ?? $this->account_id; }
    public function linkedInAuthor(): ?string { return $this->meta['linkedin_author_urn'] ?? null; }
    public function locationName(): ?string   { return $this->meta['location_name'] ?? null; }
}
