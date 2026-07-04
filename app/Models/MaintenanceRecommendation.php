<?php

namespace App\Models;

use App\Services\AuditLogService;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

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
    'suggested_action_type',
    'action_status',
    'actioned_at',
    'actioned_by',
    'action_notes',
    'linked_work_order_id',
    'linked_maintenance_schedule_id',
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

    public const RULE_KEYS = [
        'overdue_maintenance',
        'due_soon',
        'defective_without_work_order',
        'repeated_repairs',
        'no_maintenance_history',
        'expiring_warranty',
        'beyond_repair_evidence_incomplete',
        'completed_without_after_evidence',
    ];

    public const SUGGESTED_ACTIONS = [
        'overdue_maintenance' => 'Create Preventive Maintenance Schedule',
        'due_soon' => 'Schedule Preventive Maintenance',
        'defective_without_work_order' => 'Generate Corrective Work Order',
        'repeated_repairs' => 'Generate Inspection Work Order',
        'no_maintenance_history' => 'Create Initial Maintenance Schedule',
        'expiring_warranty' => 'Schedule Warranty Inspection',
        'beyond_repair_evidence_incomplete' => 'Upload Required Evidence',
        'completed_without_after_evidence' => 'Upload After-Maintenance Evidence',
    ];

    public const ACTION_STATUSES = [
        'Pending',
        'Approved',
        'Executed',
        'Rejected',
        'Cancelled',
    ];

    public const SUGGESTED_ACTION_TYPES = [
        'create_preventive_maintenance_schedule',
        'schedule_preventive_maintenance',
        'generate_corrective_work_order',
        'generate_inspection_work_order',
        'create_initial_maintenance_schedule',
        'schedule_warranty_inspection',
        'upload_required_evidence',
        'upload_after_maintenance_evidence',
        'monitor_only',
    ];

    public const ACTION_TYPE_LABELS = [
        'create_preventive_maintenance_schedule' => 'Create Preventive Maintenance Schedule',
        'schedule_preventive_maintenance' => 'Schedule Preventive Maintenance',
        'generate_corrective_work_order' => 'Generate Corrective Work Order',
        'generate_inspection_work_order' => 'Generate Inspection Work Order',
        'create_initial_maintenance_schedule' => 'Create Initial Maintenance Schedule',
        'schedule_warranty_inspection' => 'Schedule Warranty Inspection',
        'upload_required_evidence' => 'Upload Required Evidence',
        'upload_after_maintenance_evidence' => 'Upload After-Maintenance Evidence',
        'monitor_only' => 'Monitor Only',
    ];

    public const RULE_ACTION_TYPES = [
        'overdue_maintenance' => 'create_preventive_maintenance_schedule',
        'due_soon' => 'schedule_preventive_maintenance',
        'defective_without_work_order' => 'generate_corrective_work_order',
        'repeated_repairs' => 'generate_inspection_work_order',
        'no_maintenance_history' => 'create_initial_maintenance_schedule',
        'expiring_warranty' => 'schedule_warranty_inspection',
        'beyond_repair_evidence_incomplete' => 'upload_required_evidence',
        'completed_without_after_evidence' => 'upload_after_maintenance_evidence',
    ];

    protected static function booted(): void
    {
        static::creating(function (MaintenanceRecommendation $recommendation): void {
            $recommendation->action_status ??= 'Pending';
            $recommendation->suggested_action_type ??= $recommendation->getSuggestedActionType();
        });

        static::created(function (MaintenanceRecommendation $recommendation): void {
            app(AuditLogService::class)->log('generated', 'AI Recommendation', "Recommendation {$recommendation->title} generated.", auth()->user(), $recommendation, null, $recommendation->getAttributes());
        });

        static::updated(function (MaintenanceRecommendation $recommendation): void {
            app(AuditLogService::class)->logUpdate($recommendation, 'AI Recommendation', auth()->user(), $recommendation->getOriginal(), $recommendation->getChanges(), "Recommendation {$recommendation->title} updated.");
        });
    }

    public static function riskLevelOptions(): array
    {
        return array_combine(self::RISK_LEVELS, self::RISK_LEVELS);
    }

    public static function statusOptions(): array
    {
        return array_combine(self::STATUSES, self::STATUSES);
    }

    public static function ruleKeyOptions(): array
    {
        return array_combine(self::RULE_KEYS, self::RULE_KEYS);
    }

    public static function actionStatusOptions(): array
    {
        return array_combine(self::ACTION_STATUSES, self::ACTION_STATUSES);
    }

    public static function suggestedActionTypeOptions(): array
    {
        return self::ACTION_TYPE_LABELS;
    }

    public static function riskRankSql(string $column = 'risk_level'): string
    {
        return "case {$column} when 'Critical' then 1 when 'High' then 2 when 'Moderate' then 3 when 'Low' then 4 else 5 end";
    }

    public function suggestedAction(): string
    {
        return $this->getSuggestedActionLabel();
    }

    public function getSuggestedActionType(): string
    {
        return $this->suggested_action_type ?: (self::RULE_ACTION_TYPES[$this->rule_key] ?? 'monitor_only');
    }

    public function getSuggestedActionLabel(): string
    {
        return self::ACTION_TYPE_LABELS[$this->getSuggestedActionType()]
            ?? self::SUGGESTED_ACTIONS[$this->rule_key]
            ?? 'Monitor Only';
    }

    public function priorityFromRisk(): string
    {
        return match ($this->risk_level) {
            'Critical' => 'Critical',
            'High' => 'High',
            'Low' => 'Low',
            default => 'Normal',
        };
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

    public function actionedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actioned_by');
    }

    public function linkedWorkOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class, 'linked_work_order_id');
    }

    public function linkedMaintenanceSchedule(): BelongsTo
    {
        return $this->belongsTo(MaintenanceSchedule::class, 'linked_maintenance_schedule_id');
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

    public function scopeOrderByRisk(Builder $query): Builder
    {
        return $query->orderByRaw(self::riskRankSql());
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

    public function isActionPending(): bool
    {
        return ($this->action_status ?: 'Pending') === 'Pending';
    }

    public function isActionApproved(): bool
    {
        return $this->action_status === 'Approved';
    }

    public function isActionExecuted(): bool
    {
        return $this->action_status === 'Executed';
    }

    public function isActionRejected(): bool
    {
        return $this->action_status === 'Rejected';
    }

    public function isActionCancelled(): bool
    {
        return $this->action_status === 'Cancelled';
    }

    public function markReviewed(User $user): self
    {
        $this->forceFill([
            'status' => 'Reviewed',
            'reviewed_by' => $user->id,
            'reviewed_at' => now(),
        ])->save();

        $this->refresh();
        app(AuditLogService::class)->log('reviewed', 'AI Recommendation', "Recommendation {$this->title} reviewed.", $user, $this);

        return $this;
    }

    public function markResolved(User $user): self
    {
        $this->forceFill([
            'status' => 'Resolved',
            'resolved_by' => $user->id,
            'resolved_at' => now(),
        ])->save();

        $this->refresh();
        app(AuditLogService::class)->log('resolved', 'AI Recommendation', "Recommendation {$this->title} resolved.", $user, $this);

        return $this;
    }

    public function dismiss(User $user): self
    {
        $this->forceFill([
            'status' => 'Dismissed',
            'resolved_by' => $user->id,
            'resolved_at' => now(),
        ])->save();

        $this->refresh();
        app(AuditLogService::class)->log('dismissed', 'AI Recommendation', "Recommendation {$this->title} dismissed.", $user, $this);

        return $this;
    }

    public function approveAction(User $user, ?string $notes = null): self
    {
        if (! $this->isActionPending()) {
            throw new InvalidArgumentException('Only pending recommendation actions can be approved.');
        }

        $this->forceFill([
            'suggested_action_type' => $this->getSuggestedActionType(),
            'action_status' => 'Approved',
            'actioned_by' => $user->id,
            'actioned_at' => now(),
            'action_notes' => $notes,
        ])->save();

        $this->refresh();
        app(AuditLogService::class)->log('action_approved', 'AI Recommendation', "Recommendation action approved for {$this->title}.", $user, $this);

        return $this;
    }

    public function rejectAction(User $user, string $notes): self
    {
        $this->requireActionNotes($notes, 'Rejection notes are required.');

        if (! $this->isActionPending()) {
            throw new InvalidArgumentException('Only pending recommendation actions can be rejected.');
        }

        $this->forceFill([
            'suggested_action_type' => $this->getSuggestedActionType(),
            'action_status' => 'Rejected',
            'actioned_by' => $user->id,
            'actioned_at' => now(),
            'action_notes' => $notes,
        ])->save();

        $this->refresh();
        app(AuditLogService::class)->log('action_rejected', 'AI Recommendation', "Recommendation action rejected for {$this->title}.", $user, $this);

        return $this;
    }

    public function cancelAction(User $user, string $notes): self
    {
        $this->requireActionNotes($notes, 'Cancellation notes are required.');

        if (! in_array($this->action_status ?: 'Pending', ['Pending', 'Approved'], true)) {
            throw new InvalidArgumentException('Only pending or approved recommendation actions can be cancelled.');
        }

        $this->forceFill([
            'suggested_action_type' => $this->getSuggestedActionType(),
            'action_status' => 'Cancelled',
            'actioned_by' => $user->id,
            'actioned_at' => now(),
            'action_notes' => $notes,
        ])->save();

        $this->refresh();
        app(AuditLogService::class)->log('action_cancelled', 'AI Recommendation', "Recommendation action cancelled for {$this->title}.", $user, $this);

        return $this;
    }

    public function executeAction(User $user, ?string $notes = null): self
    {
        if (! $this->isActionApproved()) {
            throw new InvalidArgumentException('Recommendation action must be approved before execution.');
        }

        if ($this->isResolved() || $this->isDismissed()) {
            throw new InvalidArgumentException('Resolved or dismissed recommendations cannot execute actions.');
        }

        if ($this->linked_work_order_id || $this->linked_maintenance_schedule_id || $this->isActionExecuted()) {
            throw new InvalidArgumentException('Recommendation action has already been executed.');
        }

        return match ($this->getSuggestedActionType()) {
            'create_preventive_maintenance_schedule',
            'schedule_preventive_maintenance' => $this->executePreventiveSchedule($user, $notes),
            'create_initial_maintenance_schedule' => $this->executeSchedule($user, 'Initial Inspection', 'As needed', today(), $notes),
            'schedule_warranty_inspection' => $this->executeSchedule($user, 'Warranty Inspection', 'As needed', today(), $notes),
            'generate_corrective_work_order' => $this->executeWorkOrder($user, $this->title, $this->explanation, $notes),
            'generate_inspection_work_order' => $this->executeWorkOrder($user, 'Inspection Required - '.$this->equipment->equipment_name, $this->explanation, $notes),
            'upload_required_evidence',
            'upload_after_maintenance_evidence' => $this->executeEvidenceGuidance($user, $notes),
            default => $this->executeMonitorOnly($user, $notes),
        };
    }

    private function executePreventiveSchedule(User $user, ?string $notes = null): self
    {
        $frequency = filled($this->equipment->maintenance_frequency) ? $this->equipment->maintenance_frequency : 'Monthly';
        $scheduledDate = $this->equipment->next_maintenance_date?->toDateString() ?: today()->toDateString();

        return $this->executeSchedule($user, 'Preventive Maintenance', $frequency, $scheduledDate, $notes);
    }

    private function executeSchedule(User $user, string $type, string $frequency, mixed $scheduledDate, ?string $notes = null): self
    {
        $schedule = MaintenanceSchedule::create([
            'equipment_id' => $this->equipment_id,
            'maintenance_type' => $type,
            'maintenance_frequency' => $frequency,
            'scheduled_date' => $scheduledDate,
            'priority' => $this->priorityFromRisk(),
            'checklist_instructions' => $this->recommended_action,
            'status' => 'Upcoming',
            'remarks' => 'Generated from recommendation: '.$this->title,
        ]);

        return $this->markActionExecuted($user, $notes, [
            'linked_maintenance_schedule_id' => $schedule->id,
        ]);
    }

    private function executeWorkOrder(User $user, string $title, string $problemDescription, ?string $notes = null): self
    {
        $workOrder = WorkOrder::create([
            'equipment_id' => $this->equipment_id,
            'created_by' => $user->id,
            'title' => $title,
            'problem_description' => $problemDescription,
            'priority' => $this->priorityFromRisk(),
            'status' => 'Available',
            'available_at' => now(),
            'remarks' => 'Generated from recommendation: '.$this->title,
        ]);

        return $this->markActionExecuted($user, $notes, [
            'linked_work_order_id' => $workOrder->id,
        ]);
    }

    private function executeEvidenceGuidance(User $user, ?string $notes = null): self
    {
        $workOrder = $this->findRelatedWorkOrder();

        $this->forceFill([
            'action_status' => 'Approved',
            'actioned_by' => $user->id,
            'actioned_at' => now(),
            'action_notes' => $notes ?: 'Evidence must be uploaded from the related work order. No evidence was uploaded automatically.',
            'linked_work_order_id' => $workOrder?->id,
        ])->save();

        $this->refresh();
        app(AuditLogService::class)->log('action_executed', 'AI Recommendation', "Recommendation action executed for {$this->title}.", $user, $this);

        return $this;
    }

    private function executeMonitorOnly(User $user, ?string $notes = null): self
    {
        return $this->markActionExecuted($user, $notes ?: 'Monitor only. No operational record was created.');
    }

    /**
     * @param  array<string, mixed>  $links
     */
    private function markActionExecuted(User $user, ?string $notes = null, array $links = []): self
    {
        $this->forceFill(array_merge([
            'action_status' => 'Executed',
            'actioned_by' => $user->id,
            'actioned_at' => now(),
            'action_notes' => $notes,
        ], $links))->save();

        $this->refresh();
        app(AuditLogService::class)->log('action_executed', 'AI Recommendation', "Recommendation action executed for {$this->title}.", $user, $this);

        return $this;
    }

    private function findRelatedWorkOrder(): ?WorkOrder
    {
        $metadataWorkOrderIds = collect($this->metadata['work_order_ids'] ?? [])
            ->filter()
            ->values();

        if ($metadataWorkOrderIds->isNotEmpty()) {
            return WorkOrder::query()
                ->where('equipment_id', $this->equipment_id)
                ->whereIn('id', $metadataWorkOrderIds)
                ->first();
        }

        return $this->equipment->openWorkOrders()->first();
    }

    private function requireActionNotes(?string $notes, string $message): void
    {
        if (! filled($notes)) {
            throw new InvalidArgumentException($message);
        }
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
            'actioned_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
