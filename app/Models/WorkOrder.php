<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

#[Fillable([
    'work_order_number',
    'maintenance_request_id',
    'equipment_id',
    'created_by',
    'assigned_to',
    'accepted_by',
    'verified_by',
    'title',
    'problem_description',
    'priority',
    'status',
    'findings',
    'action_performed',
    'completion_remarks',
    'final_equipment_condition',
    'final_operational_status',
    'beyond_repair_reason',
    'recommended_action',
    'on_hold_reason',
    'required_parts',
    'rejection_or_cancellation_reason',
    'available_at',
    'assigned_at',
    'accepted_at',
    'started_at',
    'completed_at',
    'verified_at',
    'reopened_at',
    'cancelled_at',
    'due_date',
    'remarks',
])]
class WorkOrder extends Model
{
    use HasFactory;

    public const STATUSES = [
        'Submitted',
        'For review',
        'Approved',
        'Available',
        'Assigned',
        'Accepted',
        'In progress',
        'On hold',
        'Awaiting parts',
        'For verification',
        'Completed',
        'Beyond repair',
        'Reopened',
        'Rejected',
        'Cancelled',
    ];

    public const CLOSED_STATUSES = [
        'Completed',
        'Beyond repair',
        'Rejected',
        'Cancelled',
    ];

    public const PRIORITIES = [
        'Low',
        'Normal',
        'High',
        'Critical',
    ];

    protected static function booted(): void
    {
        static::creating(function (WorkOrder $workOrder): void {
            $workOrder->work_order_number ??= self::makeWorkOrderNumber();
        });
    }

    public static function makeWorkOrderNumber(): string
    {
        $prefix = 'WO-'.now()->format('Ymd').'-';
        $latest = self::query()
            ->where('work_order_number', 'like', $prefix.'%')
            ->orderByDesc('work_order_number')
            ->value('work_order_number');

        $nextNumber = $latest ? ((int) substr($latest, -4)) + 1 : 1;

        do {
            $workOrderNumber = $prefix.str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);
            $nextNumber++;
        } while (self::where('work_order_number', $workOrderNumber)->exists());

        return $workOrderNumber;
    }

    public static function statusOptions(): array
    {
        return array_combine(self::STATUSES, self::STATUSES);
    }

    public static function priorityOptions(): array
    {
        return array_combine(self::PRIORITIES, self::PRIORITIES);
    }

    public function maintenanceRequest(): BelongsTo
    {
        return $this->belongsTo(MaintenanceRequest::class);
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function acceptedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_by');
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNotIn('status', self::CLOSED_STATUSES);
    }

    public function scopeClosed(Builder $query): Builder
    {
        return $query->whereIn('status', self::CLOSED_STATUSES);
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('status', 'Available');
    }

    public function scopeAssigned(Builder $query): Builder
    {
        return $query->where('status', 'Assigned');
    }

    public function scopeAccepted(Builder $query): Builder
    {
        return $query->where('status', 'Accepted');
    }

    public function scopeInProgress(Builder $query): Builder
    {
        return $query->where('status', 'In progress');
    }

    public function scopeForVerification(Builder $query): Builder
    {
        return $query->where('status', 'For verification');
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', 'Completed');
    }

    public function scopeBeyondRepair(Builder $query): Builder
    {
        return $query->where('status', 'Beyond repair');
    }

    public function scopeCancelled(Builder $query): Builder
    {
        return $query->where('status', 'Cancelled');
    }

    public function isOpen(): bool
    {
        return ! $this->isClosed();
    }

    public function isClosed(): bool
    {
        return in_array($this->status, self::CLOSED_STATUSES, true);
    }

    public function isAvailable(): bool
    {
        return $this->status === 'Available';
    }

    public function isAssigned(): bool
    {
        return $this->status === 'Assigned';
    }

    public function isAccepted(): bool
    {
        return $this->status === 'Accepted';
    }

    public function isInProgress(): bool
    {
        return $this->status === 'In progress';
    }

    public function isForVerification(): bool
    {
        return $this->status === 'For verification';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'Completed';
    }

    public function isBeyondRepair(): bool
    {
        return $this->status === 'Beyond repair';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'Cancelled';
    }

    public function assignTo(User $user): self
    {
        $this->forceFill([
            'assigned_to' => $user->id,
            'assigned_at' => now(),
            'status' => 'Assigned',
        ])->save();

        return $this->refresh();
    }

    public function makeAvailable(): self
    {
        $this->forceFill([
            'status' => 'Available',
            'available_at' => now(),
        ])->save();

        return $this->refresh();
    }

    public function accept(User $user): self
    {
        if (! in_array($this->status, ['Available', 'Assigned'], true)) {
            throw new InvalidArgumentException('Only available or assigned work orders can be accepted.');
        }

        $this->forceFill([
            'accepted_by' => $user->id,
            'accepted_at' => now(),
            'status' => 'Accepted',
        ])->save();

        return $this->refresh();
    }

    public function start(): self
    {
        if (! in_array($this->status, ['Accepted', 'Assigned'], true)) {
            throw new InvalidArgumentException('Only accepted or assigned work orders can be started.');
        }

        $this->forceFill([
            'started_at' => now(),
            'status' => 'In progress',
        ])->save();

        return $this->refresh();
    }

    public function putOnHold(string $reason): self
    {
        $this->requireText($reason, 'On-hold reason is required.');

        $this->forceFill([
            'on_hold_reason' => $reason,
            'status' => 'On hold',
        ])->save();

        return $this->refresh();
    }

    public function awaitParts(string $requiredParts): self
    {
        $this->requireText($requiredParts, 'Required parts are required.');

        $this->forceFill([
            'required_parts' => $requiredParts,
            'status' => 'Awaiting parts',
        ])->save();

        return $this->refresh();
    }

    public function submitForVerification(array $attributes = []): self
    {
        $actionPerformed = $attributes['action_performed'] ?? $this->action_performed;
        $finalCondition = $attributes['final_equipment_condition'] ?? $this->final_equipment_condition;
        $finalStatus = $attributes['final_operational_status'] ?? $this->final_operational_status;

        $this->requireText($actionPerformed, 'Action performed is required.');
        $this->requireText($finalCondition, 'Final equipment condition is required.');
        $this->requireText($finalStatus, 'Final operational status is required.');

        $this->forceFill(array_merge($attributes, [
            'status' => 'For verification',
        ]))->save();

        return $this->refresh();
    }

    public function complete(?string $completionRemarks = null): self
    {
        if (! in_array($this->status, ['For verification', 'In progress'], true)) {
            throw new InvalidArgumentException('Only in-progress or for-verification work orders can be completed.');
        }

        $this->forceFill([
            'status' => 'Completed',
            'completed_at' => now(),
            'completion_remarks' => $completionRemarks,
        ])->save();

        $this->updateEquipmentFinalState();

        return $this->refresh();
    }

    public function markBeyondRepair(array $attributes = []): self
    {
        $findings = $attributes['findings'] ?? $this->findings;
        $reason = $attributes['beyond_repair_reason'] ?? $this->beyond_repair_reason;
        $recommendedAction = $attributes['recommended_action'] ?? $this->recommended_action;

        $this->requireText($findings, 'Findings are required.');
        $this->requireText($reason, 'Beyond-repair reason is required.');
        $this->requireText($recommendedAction, 'Recommended action is required.');

        $this->forceFill(array_merge([
            'final_equipment_condition' => $this->final_equipment_condition ?: 'Beyond repair',
            'final_operational_status' => $this->final_operational_status ?: 'Unavailable',
            'status' => 'Beyond repair',
        ], $attributes))->save();

        $this->updateEquipmentFinalState();

        return $this->refresh();
    }

    public function verify(User $user): self
    {
        if (! in_array($this->status, ['For verification', 'Completed', 'Beyond repair'], true)) {
            throw new InvalidArgumentException('Only for-verification, completed, or beyond-repair work can be verified.');
        }

        if ($this->accepted_by === $user->id) {
            throw new InvalidArgumentException('Users cannot verify their own accepted work.');
        }

        $this->forceFill([
            'verified_by' => $user->id,
            'verified_at' => now(),
            'status' => $this->isForVerification() ? 'Completed' : $this->status,
        ])->save();

        return $this->refresh();
    }

    public function reopen(?string $reason = null): self
    {
        if (! in_array($this->status, ['Completed', 'For verification', 'Beyond repair'], true)) {
            throw new InvalidArgumentException('Only completed, for-verification, or beyond-repair work orders can be reopened.');
        }

        $reason ??= $this->remarks ?: $this->rejection_or_cancellation_reason;
        $this->requireText($reason, 'Reopen reason is required.');

        $this->forceFill([
            'status' => 'Reopened',
            'reopened_at' => now(),
            'remarks' => $reason,
        ])->save();

        return $this->refresh();
    }

    public function cancel(string $reason): self
    {
        $this->requireText($reason, 'Cancellation reason is required.');

        $this->forceFill([
            'status' => 'Cancelled',
            'cancelled_at' => now(),
            'rejection_or_cancellation_reason' => $reason,
        ])->save();

        return $this->refresh();
    }

    private function updateEquipmentFinalState(): void
    {
        $attributes = array_filter([
            'condition' => $this->final_equipment_condition,
            'operational_status' => $this->final_operational_status,
        ], fn (?string $value): bool => filled($value));

        if ($attributes !== []) {
            $this->equipment()->update($attributes);
        }
    }

    private function requireText(?string $value, string $message): void
    {
        if (! filled($value)) {
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
            'available_at' => 'datetime',
            'assigned_at' => 'datetime',
            'accepted_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'verified_at' => 'datetime',
            'reopened_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'due_date' => 'date',
        ];
    }
}
