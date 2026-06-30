<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'equipment_id',
    'rule_key',
    'title',
    'explanation',
    'risk_level',
    'recommended_action',
    'generated_at',
    'reviewed_at',
    'reviewed_by',
    'resolved_at',
    'resolved_by',
    'status',
    'metadata',
])]
class MaintenanceRecommendation extends Model
{
    use HasFactory;

    public const RISK_LEVELS = [
        'Low',
        'Moderate',
        'High',
        'Critical',
    ];

    public const STATUSES = [
        'Open',
        'Reviewed',
        'Resolved',
        'Dismissed',
    ];

    public static function riskLevelOptions(): array
    {
        return array_combine(self::RISK_LEVELS, self::RISK_LEVELS);
    }

    public static function statusOptions(): array
    {
        return array_combine(self::STATUSES, self::STATUSES);
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', 'Open');
    }

    public function scopeReviewed(Builder $query): Builder
    {
        return $query->where('status', 'Reviewed');
    }

    public function scopeResolved(Builder $query): Builder
    {
        return $query->where('status', 'Resolved');
    }

    public function scopeDismissed(Builder $query): Builder
    {
        return $query->where('status', 'Dismissed');
    }

    public function scopeHighRisk(Builder $query): Builder
    {
        return $query->where('risk_level', 'High');
    }

    public function scopeCriticalRisk(Builder $query): Builder
    {
        return $query->where('risk_level', 'Critical');
    }

    public function scopeUnresolved(Builder $query): Builder
    {
        return $query->whereNotIn('status', ['Resolved', 'Dismissed']);
    }

    public function isOpen(): bool
    {
        return $this->status === 'Open';
    }

    public function isReviewed(): bool
    {
        return $this->status === 'Reviewed';
    }

    public function isResolved(): bool
    {
        return $this->status === 'Resolved';
    }

    public function isDismissed(): bool
    {
        return $this->status === 'Dismissed';
    }

    public function markReviewed(User $user): self
    {
        $this->forceFill([
            'status' => 'Reviewed',
            'reviewed_by' => $user->id,
            'reviewed_at' => now(),
        ])->save();

        return $this->refresh();
    }

    public function markResolved(User $user): self
    {
        $this->forceFill([
            'status' => 'Resolved',
            'resolved_by' => $user->id,
            'resolved_at' => now(),
        ])->save();

        return $this->refresh();
    }

    public function dismiss(User $user): self
    {
        $this->forceFill([
            'status' => 'Dismissed',
            'resolved_by' => $user->id,
            'resolved_at' => now(),
        ])->save();

        return $this->refresh();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'generated_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'resolved_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
