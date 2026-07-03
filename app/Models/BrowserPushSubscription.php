<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'endpoint',
    'public_key',
    'auth_token',
    'content_encoding',
    'user_agent',
    'device_name',
    'is_active',
    'last_seen_at',
    'revoked_at',
    'metadata',
])]
class BrowserPushSubscription extends Model
{
    use HasFactory;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->whereNull('revoked_at');
    }

    public function scopeRevoked(Builder $query): Builder
    {
        return $query->where(fn (Builder $query): Builder => $query
            ->where('is_active', false)
            ->orWhereNotNull('revoked_at'));
    }

    public function scopeForUser(Builder $query, User|int $user): Builder
    {
        $userId = $user instanceof User ? $user->id : $user;

        return $query->where('user_id', $userId);
    }

    public function isActive(): bool
    {
        return $this->is_active && $this->revoked_at === null;
    }

    public function isRevoked(): bool
    {
        return ! $this->isActive();
    }

    public function revoke(): self
    {
        $this->forceFill([
            'is_active' => false,
            'revoked_at' => now(),
        ])->save();

        return $this->refresh();
    }

    public function markSeen(): self
    {
        $this->forceFill(['last_seen_at' => now()])->save();

        return $this->refresh();
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_seen_at' => 'datetime',
            'revoked_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
