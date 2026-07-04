<?php

namespace App\Models;

use App\Services\AuditLogService;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;

#[Fillable([
    'plan_number',
    'title',
    'fiscal_year',
    'description',
    'total_estimated_budget',
    'status',
    'prepared_by',
    'reviewed_by',
    'approved_by',
    'reviewed_at',
    'approved_at',
    'remarks',
    'metadata',
])]
class BudgetPlan extends Model
{
    use HasFactory;

    public const STATUSES = [
        'Draft',
        'Prepared',
        'Under Review',
        'Approved',
        'Rejected',
        'Cancelled',
    ];

    protected static function booted(): void
    {
        static::creating(function (BudgetPlan $plan): void {
            $plan->plan_number ??= self::makePlanNumber((int) ($plan->fiscal_year ?: now()->year));
            $plan->status ??= 'Draft';
            $plan->total_estimated_budget ??= 0;
        });

        static::created(function (BudgetPlan $plan): void {
            app(AuditLogService::class)->logCreate($plan, 'Budget Plan', auth()->user(), $plan->getAttributes(), "Budget plan {$plan->plan_number} created.");
        });
    }

    public static function makePlanNumber(int $year): string
    {
        $prefix = 'BP-'.$year.'-';
        $latest = self::query()
            ->where('plan_number', 'like', $prefix.'%')
            ->orderByDesc('plan_number')
            ->value('plan_number');

        $nextNumber = $latest ? ((int) substr($latest, -4)) + 1 : 1;

        do {
            $planNumber = $prefix.str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);
            $nextNumber++;
        } while (self::where('plan_number', $planNumber)->exists());

        return $planNumber;
    }

    public static function statusOptions(): array
    {
        return array_combine(self::STATUSES, self::STATUSES);
    }

    public function items(): HasMany
    {
        return $this->hasMany(BudgetPlanItem::class);
    }

    public function preparedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function recalculateTotal(): self
    {
        $oldTotal = $this->total_estimated_budget;
        $total = $this->items()->sum('estimated_cost');

        $this->forceFill(['total_estimated_budget' => $total])->save();

        app(AuditLogService::class)->log('total_recalculated', 'Budget Plan', "Budget plan {$this->plan_number} total recalculated.", auth()->user(), $this, [
            'total_estimated_budget' => $oldTotal,
        ], [
            'total_estimated_budget' => $this->total_estimated_budget,
        ]);

        return $this->refresh();
    }

    public function submitForReview(?User $user = null): self
    {
        if (! in_array($this->status, ['Draft', 'Prepared'], true)) {
            throw new InvalidArgumentException('Only draft or prepared budget plans can be submitted for review.');
        }

        return $this->transition('Under Review', 'submitted_for_review', [
            'reviewed_by' => $user?->id,
            'reviewed_at' => now(),
        ]);
    }

    public function approve(User $user): self
    {
        if (! in_array($this->status, ['Prepared', 'Under Review'], true)) {
            throw new InvalidArgumentException('Only prepared or under-review budget plans can be approved.');
        }

        return $this->transition('Approved', 'approved', [
            'approved_by' => $user->id,
            'approved_at' => now(),
        ]);
    }

    public function reject(User $user, ?string $remarks = null): self
    {
        if (! in_array($this->status, ['Prepared', 'Under Review'], true)) {
            throw new InvalidArgumentException('Only prepared or under-review budget plans can be rejected.');
        }

        return $this->transition('Rejected', 'rejected', [
            'reviewed_by' => $this->reviewed_by ?: $user->id,
            'reviewed_at' => $this->reviewed_at ?: now(),
            'remarks' => $remarks ?? $this->remarks,
        ]);
    }

    public function cancel(?string $remarks = null): self
    {
        if (in_array($this->status, ['Approved', 'Rejected', 'Cancelled'], true)) {
            throw new InvalidArgumentException('Approved, rejected, and cancelled budget plans cannot be cancelled.');
        }

        return $this->transition('Cancelled', 'cancelled', [
            'remarks' => $remarks ?? $this->remarks,
        ]);
    }

    protected function casts(): array
    {
        return [
            'fiscal_year' => 'integer',
            'total_estimated_budget' => 'decimal:2',
            'reviewed_at' => 'datetime',
            'approved_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    private function transition(string $status, string $auditAction, array $attributes = []): self
    {
        $oldStatus = $this->status;

        $this->forceFill(array_merge($attributes, [
            'status' => $status,
        ]))->save();

        $this->refresh();

        app(AuditLogService::class)->log(
            $auditAction,
            'Budget Plan',
            "Budget plan {$this->plan_number} status changed from {$oldStatus} to {$status}.",
            auth()->user(),
            $this,
            ['status' => $oldStatus],
            ['status' => $status],
        );

        return $this;
    }
}
