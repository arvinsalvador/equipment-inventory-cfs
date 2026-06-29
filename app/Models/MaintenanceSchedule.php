<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

#[Fillable([
    'equipment_id',
    'maintenance_type',
    'maintenance_frequency',
    'scheduled_date',
    'assigned_user_id',
    'priority',
    'checklist_instructions',
    'status',
    'completed_at',
    'completed_by',
    'completion_remarks',
    'rescheduled_from',
    'cancelled_at',
    'cancelled_by',
    'cancellation_reason',
    'remarks',
])]
class MaintenanceSchedule extends Model
{
    use HasFactory;

    public const FREQUENCIES = [
        'Daily',
        'Weekly',
        'Monthly',
        'Quarterly',
        'Semi-annually',
        'Annually',
        'As needed',
    ];

    public const STATUSES = [
        'Upcoming',
        'Due soon',
        'Due today',
        'Overdue',
        'In progress',
        'Completed',
        'Rescheduled',
        'Cancelled',
    ];

    public const PRIORITIES = [
        'Low',
        'Normal',
        'High',
        'Critical',
    ];

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->incomplete()->whereDate('scheduled_date', '>', today());
    }

    public function scopeDueSoon(Builder $query): Builder
    {
        return $query->incomplete()
            ->whereDate('scheduled_date', '>', today())
            ->whereDate('scheduled_date', '<=', today()->addDays(7));
    }

    public function scopeDueToday(Builder $query): Builder
    {
        return $query->incomplete()->whereDate('scheduled_date', today());
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->incomplete()->whereDate('scheduled_date', '<', today());
    }

    public function scopeIncomplete(Builder $query): Builder
    {
        return $query->whereNotIn('status', ['Completed', 'Cancelled']);
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', 'Completed');
    }

    public function scopeCancelled(Builder $query): Builder
    {
        return $query->where('status', 'Cancelled');
    }

    public function isCompleted(): bool
    {
        return $this->status === 'Completed';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'Cancelled';
    }

    public function isOverdue(): bool
    {
        return ! $this->isCompleted()
            && ! $this->isCancelled()
            && $this->scheduled_date->isBefore(today());
    }

    public function calculateNextScheduledDate(): ?CarbonInterface
    {
        return match ($this->maintenance_frequency) {
            'Daily' => $this->scheduled_date->copy()->addDay(),
            'Weekly' => $this->scheduled_date->copy()->addWeek(),
            'Monthly' => $this->scheduled_date->copy()->addMonth(),
            'Quarterly' => $this->scheduled_date->copy()->addMonths(3),
            'Semi-annually' => $this->scheduled_date->copy()->addMonths(6),
            'Annually' => $this->scheduled_date->copy()->addYear(),
            default => null,
        };
    }

    public function complete(?User $user = null, ?string $remarks = null): self
    {
        $completedAt = now();
        $nextScheduledDate = $this->calculateNextScheduledDate();

        $this->forceFill([
            'status' => 'Completed',
            'completed_at' => $completedAt,
            'completed_by' => $user?->id,
            'completion_remarks' => $remarks,
        ])->save();

        $this->equipment->update([
            'last_maintenance_date' => $completedAt->toDateString(),
            'next_maintenance_date' => $nextScheduledDate?->toDateString(),
        ]);

        return $this->refresh();
    }

    public function reschedule(CarbonInterface|string $newDate, ?string $remarks = null): self
    {
        $this->forceFill([
            'rescheduled_from' => $this->scheduled_date,
            'scheduled_date' => $newDate,
            'status' => 'Rescheduled',
            'remarks' => $remarks,
        ])->save();

        return $this->refresh();
    }

    public function cancel(string $reason, ?User $user = null): self
    {
        if (trim($reason) === '') {
            throw new InvalidArgumentException('Cancellation reason is required.');
        }

        $this->forceFill([
            'status' => 'Cancelled',
            'cancelled_at' => now(),
            'cancelled_by' => $user?->id,
            'cancellation_reason' => $reason,
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
            'scheduled_date' => 'date',
            'completed_at' => 'datetime',
            'rescheduled_from' => 'date',
            'cancelled_at' => 'datetime',
        ];
    }
}
