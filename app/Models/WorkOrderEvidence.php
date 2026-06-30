<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'work_order_id',
    'equipment_id',
    'evidence_type',
    'image_path',
    'caption',
    'uploaded_by',
    'uploaded_at',
])]
class WorkOrderEvidence extends Model
{
    use HasFactory;

    protected $table = 'work_order_evidences';

    public const EVIDENCE_TYPES = [
        'Before maintenance',
        'During maintenance',
        'After maintenance',
        'Damage evidence',
        'Beyond-repair evidence',
        'Parts evidence',
        'Inspection evidence',
        'Other evidence',
    ];

    public static function evidenceTypeOptions(): array
    {
        return array_combine(self::EVIDENCE_TYPES, self::EVIDENCE_TYPES);
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('evidence_type', $type);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'uploaded_at' => 'datetime',
        ];
    }
}
