<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use InvalidArgumentException;

#[Fillable([
    'request_number',
    'equipment_id',
    'submitted_by',
    'problem_description',
    'severity',
    'status',
    'initial_photo_path',
    'reviewed_by',
    'reviewed_at',
    'review_remarks',
    'rejected_at',
    'rejected_by',
    'rejection_reason',
    'converted_at',
    'converted_by',
    'remarks',
])]
class MaintenanceRequest extends Model
{
    use HasFactory;

    public const SEVERITIES = [
        'Low',
        'Moderate',
        'High',
        'Critical',
    ];

    public const STATUSES = [
        'Submitted',
        'For review',
        'Approved',
        'Converted',
        'Rejected',
        'Cancelled',
    ];

    protected static function booted(): void
    {
        static::creating(function (MaintenanceRequest $maintenanceRequest): void {
            $maintenanceRequest->request_number ??= self::makeRequestNumber();
        });
    }

    public static function makeRequestNumber(): string
    {
        $prefix = 'MR-'.now()->format('Ymd').'-';
        $latest = self::query()
            ->where('request_number', 'like', $prefix.'%')
            ->orderByDesc('request_number')
            ->value('request_number');

        $nextNumber = $latest ? ((int) substr($latest, -4)) + 1 : 1;

        do {
            $requestNumber = $prefix.str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);
            $nextNumber++;
        } while (self::where('request_number', $requestNumber)->exists());

        return $requestNumber;
    }

    public static function severityOptions(): array
    {
        return array_combine(self::SEVERITIES, self::SEVERITIES);
    }

    public static function statusOptions(): array
    {
        return array_combine(self::STATUSES, self::STATUSES);
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function convertedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'converted_by');
    }

    public function workOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class)->latest();
    }

    public function latestWorkOrder(): HasOne
    {
        return $this->hasOne(WorkOrder::class)->latestOfMany();
    }

    public function scopeSubmitted(Builder $query): Builder
    {
        return $query->where('status', 'Submitted');
    }

    public function scopeForReview(Builder $query): Builder
    {
        return $query->where('status', 'For review');
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', 'Approved');
    }

    public function scopeConverted(Builder $query): Builder
    {
        return $query->where('status', 'Converted');
    }

    public function scopeRejected(Builder $query): Builder
    {
        return $query->where('status', 'Rejected');
    }

    public function scopeCancelled(Builder $query): Builder
    {
        return $query->where('status', 'Cancelled');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNotIn('status', ['Converted', 'Rejected', 'Cancelled']);
    }

    public function scopeClosed(Builder $query): Builder
    {
        return $query->whereIn('status', ['Converted', 'Rejected', 'Cancelled']);
    }

    public function isSubmitted(): bool
    {
        return $this->status === 'Submitted';
    }

    public function isApproved(): bool
    {
        return $this->status === 'Approved';
    }

    public function isConverted(): bool
    {
        return $this->status === 'Converted';
    }

    public function isRejected(): bool
    {
        return $this->status === 'Rejected';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'Cancelled';
    }

    public function isClosed(): bool
    {
        return $this->isConverted() || $this->isRejected() || $this->isCancelled();
    }

    public function approve(?User $user = null, ?string $remarks = null): self
    {
        if (! in_array($this->status, ['Submitted', 'For review'], true)) {
            throw new InvalidArgumentException('Only submitted or for-review requests can be approved.');
        }

        $this->forceFill([
            'status' => 'Approved',
            'reviewed_by' => $user?->id,
            'reviewed_at' => now(),
            'review_remarks' => $remarks,
        ])->save();

        return $this->refresh();
    }

    public function reject(string $reason, ?User $user = null): self
    {
        if (! in_array($this->status, ['Submitted', 'For review'], true)) {
            throw new InvalidArgumentException('Only submitted or for-review requests can be rejected.');
        }

        if (trim($reason) === '') {
            throw new InvalidArgumentException('Rejection reason is required.');
        }

        $this->forceFill([
            'status' => 'Rejected',
            'rejected_by' => $user?->id,
            'rejected_at' => now(),
            'rejection_reason' => $reason,
        ])->save();

        return $this->refresh();
    }

    public function markAsConverted(?User $user = null): self
    {
        if (! $this->isApproved()) {
            throw new InvalidArgumentException('Only approved requests can be converted.');
        }

        $this->forceFill([
            'status' => 'Converted',
            'converted_by' => $user?->id,
            'converted_at' => now(),
        ])->save();

        return $this->refresh();
    }

    public function createWorkOrder(User $user): WorkOrder
    {
        if (! $this->isApproved()) {
            throw new InvalidArgumentException('Only approved requests can be converted to work orders.');
        }

        if ($this->workOrders()->exists()) {
            return $this->latestWorkOrder()->first();
        }

        $workOrder = WorkOrder::create([
            'maintenance_request_id' => $this->id,
            'equipment_id' => $this->equipment_id,
            'created_by' => $user->id,
            'title' => 'Work order for '.$this->request_number,
            'problem_description' => $this->problem_description,
            'priority' => $this->workOrderPriority(),
            'status' => 'Available',
            'available_at' => now(),
        ]);

        $this->markAsConverted($user);

        return $workOrder->refresh();
    }

    public function cancel(?string $remarks = null): self
    {
        if ($this->isClosed() || $this->isApproved()) {
            throw new InvalidArgumentException('This request cannot be cancelled.');
        }

        $this->forceFill([
            'status' => 'Cancelled',
            'remarks' => $remarks,
        ])->save();

        return $this->refresh();
    }

    private function workOrderPriority(): string
    {
        return match ($this->severity) {
            'Low' => 'Low',
            'High' => 'High',
            'Critical' => 'Critical',
            default => 'Normal',
        };
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
            'rejected_at' => 'datetime',
            'converted_at' => 'datetime',
        ];
    }
}
