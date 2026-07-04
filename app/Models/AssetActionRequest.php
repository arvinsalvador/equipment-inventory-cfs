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
    'request_number',
    'equipment_id',
    'requested_by',
    'reviewed_by',
    'approved_by',
    'completed_by',
    'request_type',
    'reason',
    'justification',
    'recommended_action',
    'estimated_cost',
    'priority',
    'status',
    'reviewed_at',
    'approved_at',
    'rejected_at',
    'completed_at',
    'cancellation_reason',
    'rejection_reason',
    'remarks',
    'metadata',
])]
class AssetActionRequest extends Model
{
    use HasFactory;

    public const REQUEST_TYPES = [
        'Replacement',
        'Procurement',
        'Disposal',
        'Major Repair',
        'Inspection',
    ];

    public const STATUSES = [
        'Draft',
        'Submitted',
        'Under Review',
        'Approved',
        'Rejected',
        'Cancelled',
        'Completed',
    ];

    public const PRIORITIES = [
        'Low',
        'Normal',
        'High',
        'Critical',
    ];

    protected static function booted(): void
    {
        static::creating(function (AssetActionRequest $request): void {
            $request->request_number ??= self::makeRequestNumber();
            $request->status ??= 'Draft';
        });

        static::created(function (AssetActionRequest $request): void {
            app(AuditLogService::class)->logCreate($request, 'Asset Action Request', auth()->user(), $request->getAttributes(), "Asset action request {$request->request_number} created.");
        });
    }

    public static function makeRequestNumber(): string
    {
        $prefix = 'AAR-'.now()->format('Ymd').'-';
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

    public static function requestTypeOptions(): array
    {
        return array_combine(self::REQUEST_TYPES, self::REQUEST_TYPES);
    }

    public static function statusOptions(): array
    {
        return array_combine(self::STATUSES, self::STATUSES);
    }

    public static function priorityOptions(): array
    {
        return array_combine(self::PRIORITIES, self::PRIORITIES);
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function scopeSubmitted(Builder $query): Builder
    {
        return $query->where('status', 'Submitted');
    }

    public function scopeUnderReview(Builder $query): Builder
    {
        return $query->where('status', 'Under Review');
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', 'Approved');
    }

    public function scopeRejected(Builder $query): Builder
    {
        return $query->where('status', 'Rejected');
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', 'Completed');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->whereIn('status', ['Draft', 'Submitted', 'Under Review', 'Approved']);
    }

    public function submit(): self
    {
        if ($this->status !== 'Draft') {
            throw new InvalidArgumentException('Only draft requests can be submitted.');
        }

        return $this->transition('Submitted', 'submitted');
    }

    public function markUnderReview(User $user): self
    {
        if (! in_array($this->status, ['Submitted', 'Draft'], true)) {
            throw new InvalidArgumentException('Only draft or submitted requests can be marked under review.');
        }

        return $this->transition('Under Review', 'reviewed', [
            'reviewed_by' => $user->id,
            'reviewed_at' => now(),
        ]);
    }

    public function approve(User $user): self
    {
        if (! in_array($this->status, ['Submitted', 'Under Review'], true)) {
            throw new InvalidArgumentException('Only submitted or under-review requests can be approved.');
        }

        return $this->transition('Approved', 'approved', [
            'approved_by' => $user->id,
            'approved_at' => now(),
        ]);
    }

    public function reject(User $user, string $reason): self
    {
        $this->requireText($reason, 'Rejection reason is required.');

        if (! in_array($this->status, ['Submitted', 'Under Review'], true)) {
            throw new InvalidArgumentException('Only submitted or under-review requests can be rejected.');
        }

        return $this->transition('Rejected', 'rejected', [
            'reviewed_by' => $this->reviewed_by ?: $user->id,
            'reviewed_at' => $this->reviewed_at ?: now(),
            'rejected_at' => now(),
            'rejection_reason' => $reason,
        ]);
    }

    public function cancel(?string $reason = null): self
    {
        if (! $this->isPending()) {
            throw new InvalidArgumentException('Only pending requests can be cancelled.');
        }

        return $this->transition('Cancelled', 'cancelled', [
            'cancellation_reason' => $reason,
        ]);
    }

    public function complete(User $user, ?string $remarks = null): self
    {
        if (! $this->isApproved()) {
            throw new InvalidArgumentException('Only approved requests can be completed.');
        }

        return $this->transition('Completed', 'completed', [
            'completed_by' => $user->id,
            'completed_at' => now(),
            'remarks' => $remarks ?? $this->remarks,
        ]);
    }

    public function isPending(): bool
    {
        return in_array($this->status, ['Draft', 'Submitted', 'Under Review', 'Approved'], true);
    }

    public function isApproved(): bool
    {
        return $this->status === 'Approved';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'Completed';
    }

    protected function casts(): array
    {
        return [
            'estimated_cost' => 'decimal:2',
            'reviewed_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'completed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function transition(string $status, string $auditAction, array $attributes = []): self
    {
        $oldStatus = $this->status;

        $this->forceFill(array_merge($attributes, [
            'status' => $status,
        ]))->save();

        $this->refresh();

        app(AuditLogService::class)->log(
            $auditAction,
            'Asset Action Request',
            "Asset action request {$this->request_number} {$auditAction}.",
            auth()->user(),
            $this,
            ['status' => $oldStatus],
            ['status' => $this->status],
        );

        return $this;
    }

    private function requireText(?string $value, string $message): void
    {
        if (! filled($value)) {
            throw new InvalidArgumentException($message);
        }
    }
}
