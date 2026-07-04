<?php

namespace App\Models;

use App\Services\AuditLogService;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'budget_plan_id',
    'equipment_id',
    'asset_action_request_id',
    'item_type',
    'description',
    'priority',
    'estimated_cost',
    'justification',
    'forecast_reason',
    'target_period',
    'status',
    'metadata',
])]
class BudgetPlanItem extends Model
{
    use HasFactory;

    public const ITEM_TYPES = [
        'Replacement',
        'Procurement',
        'Major Repair',
        'Inspection',
        'Disposal Support',
    ];

    public const PRIORITIES = [
        'Low',
        'Normal',
        'High',
        'Critical',
    ];

    public const STATUSES = [
        'Proposed',
        'Approved',
        'Deferred',
        'Rejected',
        'Completed',
    ];

    protected static function booted(): void
    {
        static::creating(function (BudgetPlanItem $item): void {
            $item->status ??= 'Proposed';
        });

        static::created(function (BudgetPlanItem $item): void {
            app(AuditLogService::class)->log('item_added', 'Budget Plan', "Budget item added to plan #{$item->budget_plan_id}.", auth()->user(), $item, null, $item->getAttributes());
        });
    }

    public static function itemTypeOptions(): array
    {
        return array_combine(self::ITEM_TYPES, self::ITEM_TYPES);
    }

    public static function priorityOptions(): array
    {
        return array_combine(self::PRIORITIES, self::PRIORITIES);
    }

    public static function statusOptions(): array
    {
        return array_combine(self::STATUSES, self::STATUSES);
    }

    public function budgetPlan(): BelongsTo
    {
        return $this->belongsTo(BudgetPlan::class);
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function assetActionRequest(): BelongsTo
    {
        return $this->belongsTo(AssetActionRequest::class);
    }

    public function isApproved(): bool
    {
        return $this->status === 'Approved';
    }

    public function isDeferred(): bool
    {
        return $this->status === 'Deferred';
    }

    public function isCritical(): bool
    {
        return $this->priority === 'Critical';
    }

    protected function casts(): array
    {
        return [
            'estimated_cost' => 'decimal:2',
            'metadata' => 'array',
        ];
    }
}
