<?php

namespace App\Models;

use App\Services\AuditLogService;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'equipment_id',
    'expected_useful_life_years',
    'estimated_end_of_life_date',
    'estimated_remaining_life_months',
    'health_score',
    'health_grade',
    'lifecycle_status',
    'replacement_recommendation',
    'replacement_reason',
    'last_calculated_at',
    'metadata',
])]
class EquipmentLifecycleProfile extends Model
{
    use HasFactory;

    public const LIFECYCLE_STATUSES = [
        'New',
        'Active',
        'Aging',
        'High Maintenance',
        'Replacement Candidate',
        'Beyond Repair',
        'Retired',
    ];

    public const HEALTH_GRADES = [
        'Excellent',
        'Good',
        'Fair',
        'Poor',
        'Critical',
    ];

    public const REPLACEMENT_RECOMMENDATIONS = [
        'Continue Maintenance',
        'Continue Monitoring',
        'Schedule Major Inspection',
        'Repair',
        'Replace Equipment',
        'Dispose Equipment',
    ];

    protected static function booted(): void
    {
        static::saved(function (EquipmentLifecycleProfile $profile): void {
            app(AuditLogService::class)->log('recalculated', 'Lifecycle', "Lifecycle recalculated for equipment #{$profile->equipment_id}.", auth()->user(), $profile, null, $profile->getAttributes());
        });
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function isReplacementCandidate(): bool
    {
        return $this->lifecycle_status === 'Replacement Candidate'
            || in_array($this->replacement_recommendation, ['Replace Equipment', 'Dispose Equipment'], true);
    }

    public function isCritical(): bool
    {
        return $this->health_grade === 'Critical' || ($this->health_score !== null && $this->health_score < 40);
    }

    public function getHealthGradeLabel(): string
    {
        return $this->health_grade ?: 'Not calculated';
    }

    public function getReplacementRecommendationLabel(): string
    {
        return $this->replacement_recommendation ?: 'Not calculated';
    }

    protected function casts(): array
    {
        return [
            'expected_useful_life_years' => 'integer',
            'estimated_end_of_life_date' => 'date',
            'estimated_remaining_life_months' => 'integer',
            'health_score' => 'integer',
            'last_calculated_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
