<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'action',
    'module',
    'entity_type',
    'entity_id',
    'description',
    'old_values',
    'new_values',
    'ip_address',
    'user_agent',
    'url',
    'method',
    'request_id',
    'metadata',
    'created_at',
])]
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    public $timestamps = false;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForUser(Builder $query, User|int $user): Builder
    {
        $userId = $user instanceof User ? $user->id : $user;

        return $query->where('user_id', $userId);
    }

    public function scopeModule(Builder $query, string $module): Builder
    {
        return $query->where('module', $module);
    }

    public function scopeAction(Builder $query, string $action): Builder
    {
        return $query->where('action', $action);
    }

    public function scopeEntity(Builder $query, string $entityType, int|string|null $entityId = null): Builder
    {
        return $query
            ->where('entity_type', $entityType)
            ->when($entityId !== null, fn (Builder $query): Builder => $query->where('entity_id', $entityId));
    }

    public function scopeRecent(Builder $query): Builder
    {
        return $query->latest('created_at');
    }

    public function scopeDateRange(Builder $query, mixed $from = null, mixed $to = null): Builder
    {
        return $query
            ->when($from, fn (Builder $query): Builder => $query->whereDate('created_at', '>=', $from))
            ->when($to, fn (Builder $query): Builder => $query->whereDate('created_at', '<=', $to));
    }

    public function isSystemAction(): bool
    {
        return $this->user_id === null || $this->action === 'system';
    }

    public function isUserAction(): bool
    {
        return ! $this->isSystemAction();
    }

    public function summary(): string
    {
        return trim("{$this->module}: {$this->action} - {$this->description}");
    }

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
