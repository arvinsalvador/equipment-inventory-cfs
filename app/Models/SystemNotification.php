<?php

namespace App\Models;

use App\Services\AuditLogService;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'title',
    'message',
    'notification_type',
    'priority',
    'category',
    'related_type',
    'related_id',
    'action_url',
    'read_at',
    'generated_at',
    'expires_at',
    'email_sent_at',
    'email_failed_at',
    'email_failure_reason',
    'email_delivery_attempts',
    'metadata',
])]
class SystemNotification extends Model
{
    use HasFactory;

    public const TYPES = [
        'Information',
        'Reminder',
        'Warning',
        'Critical',
    ];

    public const PRIORITIES = [
        'Low',
        'Normal',
        'High',
        'Critical',
    ];

    public const CATEGORIES = [
        'Preventive Maintenance',
        'Work Order',
        'Maintenance Request',
        'AI Recommendation',
        'Equipment Lifecycle',
        'Warranty',
        'Evidence',
        'Budget',
        'Asset Action',
        'Executive',
        'System',
    ];

    protected static function booted(): void
    {
        static::created(function (SystemNotification $notification): void {
            app(AuditLogService::class)->log('generated', 'Notification', "Notification {$notification->title} generated.", auth()->user(), $notification, null, $notification->getAttributes());
        });

        static::updated(function (SystemNotification $notification): void {
            if (! $notification->wasChanged('read_at')) {
                return;
            }

            app(AuditLogService::class)->log(
                $notification->read_at ? 'marked_read' : 'marked_unread',
                'Notification',
                "Notification {$notification->title} marked ".($notification->read_at ? 'read' : 'unread').'.',
                auth()->user(),
                $notification,
                ['read_at' => $notification->getOriginal('read_at')],
                ['read_at' => $notification->read_at],
            );
        });
    }

    public static function typeOptions(): array
    {
        return array_combine(self::TYPES, self::TYPES);
    }

    public static function priorityOptions(): array
    {
        return array_combine(self::PRIORITIES, self::PRIORITIES);
    }

    public static function categoryOptions(): array
    {
        return array_combine(self::CATEGORIES, self::CATEGORIES);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }

    public function scopeRead(Builder $query): Builder
    {
        return $query->whereNotNull('read_at');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where(fn (Builder $query): Builder => $query
            ->whereNull('expires_at')
            ->orWhere('expires_at', '>', now()));
    }

    public function scopeExpired(Builder $query): Builder
    {
        return $query->whereNotNull('expires_at')->where('expires_at', '<=', now());
    }

    public function scopeCritical(Builder $query): Builder
    {
        return $query->where(fn (Builder $query): Builder => $query
            ->where('priority', 'Critical')
            ->orWhere('notification_type', 'Critical'));
    }

    public function scopeHighPriority(Builder $query): Builder
    {
        return $query->whereIn('priority', ['High', 'Critical']);
    }

    public function scopeCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }

    public function scopeForUser(Builder $query, User|int $user): Builder
    {
        $userId = $user instanceof User ? $user->id : $user;

        return $query->where('user_id', $userId);
    }

    public function scopeGlobal(Builder $query): Builder
    {
        return $query->whereNull('user_id');
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->can('access admin panel') && $user->hasRole('Administrator')) {
            return $query;
        }

        return $query->where(fn (Builder $query): Builder => $query
            ->where('user_id', $user->id)
            ->orWhereNull('user_id'));
    }

    public function isRead(): bool
    {
        return $this->read_at !== null;
    }

    public function isUnread(): bool
    {
        return ! $this->isRead();
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->lte(now());
    }

    public function wasEmailed(): bool
    {
        return $this->email_sent_at !== null;
    }

    public function emailFailed(): bool
    {
        return $this->email_failed_at !== null && $this->email_sent_at === null;
    }

    public function markAsRead(): self
    {
        $this->forceFill(['read_at' => now()])->save();

        return $this->refresh();
    }

    public function markAsUnread(): self
    {
        $this->forceFill(['read_at' => null])->save();

        return $this->refresh();
    }

    public function getPriorityLabel(): string
    {
        return $this->priority ?: 'Normal';
    }

    public function getTypeLabel(): string
    {
        return $this->notification_type ?: 'Information';
    }

    public function getCategoryLabel(): string
    {
        return $this->category ?: 'System';
    }

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
            'generated_at' => 'datetime',
            'expires_at' => 'datetime',
            'email_sent_at' => 'datetime',
            'email_failed_at' => 'datetime',
            'email_delivery_attempts' => 'integer',
            'metadata' => 'array',
        ];
    }
}
