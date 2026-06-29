<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

#[Fillable([
    'equipment_code',
    'qr_identifier',
    'qr_code_path',
    'qr_code_generated_at',
    'property_number',
    'equipment_name',
    'equipment_category_id',
    'description',
    'brand',
    'model',
    'serial_number',
    'acquisition_date',
    'acquisition_cost',
    'current_location_id',
    'custodian',
    'condition',
    'operational_status',
    'maintenance_frequency',
    'last_maintenance_date',
    'next_maintenance_date',
    'warranty_expiration_date',
    'photo_path',
    'remarks',
    'is_archived',
    'archived_at',
    'archived_by',
])]
class Equipment extends Model
{
    use HasFactory;

    protected $table = 'equipment';

    public const CONDITIONS = [
        'New',
        'Good',
        'Fair',
        'Needs inspection',
        'Needs maintenance',
        'Defective',
        'Beyond repair',
    ];

    public const OPERATIONAL_STATUSES = [
        'Available',
        'In use',
        'Under inspection',
        'Under maintenance',
        'Unavailable',
        'Retired',
        'Disposed',
        'Transferred',
    ];

    protected static function booted(): void
    {
        static::creating(function (Equipment $equipment): void {
            $equipment->qr_identifier ??= self::makeQrIdentifier();
        });
    }

    public static function makeQrIdentifier(): string
    {
        do {
            $identifier = (string) Str::uuid();
        } while (self::where('qr_identifier', $identifier)->exists());

        return $identifier;
    }

    public static function conditionOptions(): array
    {
        return array_combine(self::CONDITIONS, self::CONDITIONS);
    }

    public static function operationalStatusOptions(): array
    {
        return array_combine(self::OPERATIONAL_STATUSES, self::OPERATIONAL_STATUSES);
    }

    public function getQrLookupUrl(): string
    {
        return url("/equipment/lookup/{$this->qr_identifier}");
    }

    public function getQrCodeUrl(): ?string
    {
        if (! $this->qr_code_path || ! Storage::disk('public')->exists($this->qr_code_path)) {
            return null;
        }

        return Storage::disk('public')->url($this->qr_code_path);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(EquipmentCategory::class, 'equipment_category_id');
    }

    public function currentLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'current_location_id');
    }

    public function archivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'archived_by');
    }

    public function locationHistories(): HasMany
    {
        return $this->hasMany(EquipmentLocationHistory::class)->latest('transferred_at');
    }

    public function maintenanceSchedules(): HasMany
    {
        return $this->hasMany(MaintenanceSchedule::class)->latest('scheduled_date');
    }

    public function maintenanceRequests(): HasMany
    {
        return $this->hasMany(MaintenanceRequest::class)->latest();
    }

    public function openMaintenanceRequests(): HasMany
    {
        return $this->hasMany(MaintenanceRequest::class)->open()->latest();
    }

    public function latestMaintenanceSchedule(): HasOne
    {
        return $this->hasOne(MaintenanceSchedule::class)->latestOfMany('scheduled_date');
    }

    public function dueMaintenanceSchedules(): HasMany
    {
        return $this->hasMany(MaintenanceSchedule::class)
            ->incomplete()
            ->whereDate('scheduled_date', '<=', today()->addDays(7))
            ->orderBy('scheduled_date');
    }

    public function latestLocationHistory(): HasOne
    {
        return $this->hasOne(EquipmentLocationHistory::class)->latestOfMany('transferred_at');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_archived', false);
    }

    public function scopeArchived(Builder $query): Builder
    {
        return $query->where('is_archived', true);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'acquisition_date' => 'date',
            'acquisition_cost' => 'decimal:2',
            'last_maintenance_date' => 'date',
            'next_maintenance_date' => 'date',
            'warranty_expiration_date' => 'date',
            'is_archived' => 'boolean',
            'archived_at' => 'datetime',
            'qr_code_generated_at' => 'datetime',
        ];
    }
}
