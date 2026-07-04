<?php

namespace App\Models;

use App\Services\AuditLogService;
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

        static::created(function (Equipment $equipment): void {
            app(AuditLogService::class)->logCreate($equipment, 'Equipment', auth()->user(), $equipment->getAttributes(), "Equipment {$equipment->equipment_code} created.");
        });

        static::updated(function (Equipment $equipment): void {
            $service = app(AuditLogService::class);

            if ($equipment->wasChanged('is_archived') && $equipment->is_archived) {
                $service->log('archived', 'Equipment', "Equipment {$equipment->equipment_code} archived.", auth()->user(), $equipment, [
                    'is_archived' => false,
                ], [
                    'is_archived' => true,
                ]);

                return;
            }

            if ($equipment->wasChanged('current_location_id')) {
                $service->log('location_changed', 'Equipment', "Equipment {$equipment->equipment_code} location changed.", auth()->user(), $equipment, [
                    'current_location_id' => $equipment->getOriginal('current_location_id'),
                ], [
                    'current_location_id' => $equipment->current_location_id,
                ]);

                return;
            }

            $service->logUpdate($equipment, 'Equipment', auth()->user(), $equipment->getOriginal(), $equipment->getChanges(), "Equipment {$equipment->equipment_code} updated.");
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
        return $this->publicMediaUrl($this->qr_code_path);
    }

    public function getPhotoUrl(): ?string
    {
        return $this->publicMediaUrl($this->photo_path);
    }

    public function getEquipmentPhotoUrlAttribute(): ?string
    {
        return $this->getPhotoUrl();
    }

    public function getPhotoUrlAttribute(): ?string
    {
        return $this->getPhotoUrl();
    }

    public function getQrCodeUrlAttribute(): ?string
    {
        return $this->getQrCodeUrl();
    }

    public function getNormalizedPhotoPathAttribute(): ?string
    {
        return $this->normalizePublicMediaPath($this->photo_path);
    }

    public function getNormalizedQrCodePathAttribute(): ?string
    {
        return $this->normalizePublicMediaPath($this->qr_code_path);
    }

    public function normalizePublicMediaPath(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        $path = trim(str_replace('\\', '/', $path));

        if (preg_match('#^https?://#i', $path)) {
            $path = parse_url($path, PHP_URL_PATH) ?: '';
        }

        $path = ltrim($path, '/');

        foreach ([
            'storage/app/public/',
            'app/public/',
            'public/storage/',
            'storage/',
        ] as $prefix) {
            if (str_starts_with($path, $prefix)) {
                $path = substr($path, strlen($prefix));
                break;
            }
        }

        return $path === '' ? null : $path;
    }

    private function publicMediaUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        $path = trim(str_replace('\\', '/', $path));
        $isFullUrl = preg_match('#^https?://#i', $path) === 1;
        $normalizedPath = $this->normalizePublicMediaPath($path);

        if ($normalizedPath && Storage::disk('public')->exists($normalizedPath)) {
            return $this->publicStorageUrl($normalizedPath);
        }

        if ($isFullUrl) {
            return $path;
        }

        return null;
    }

    private function publicStorageUrl(string $path): string
    {
        $url = Storage::disk('public')->url(ltrim($path, '/'));
        $appStoragePrefix = rtrim((string) config('app.url'), '/').'/storage/';

        if (str_starts_with($url, $appStoragePrefix)) {
            return '/storage/'.substr($url, strlen($appStoragePrefix));
        }

        $storagePath = parse_url($url, PHP_URL_PATH);

        if (is_string($storagePath) && str_starts_with($storagePath, '/storage/')) {
            return $storagePath;
        }

        return $url;
    }

    public function filamentPhotoImageState(): ?string
    {
        if ($this->equipment_photo_url && preg_match('#^https?://#i', $this->equipment_photo_url)) {
            return $this->equipment_photo_url;
        }

        $path = $this->normalized_photo_path;

        if (! $path || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        return $path;
    }

    public function filamentQrCodeImageState(): ?string
    {
        if ($this->qr_code_url && preg_match('#^https?://#i', $this->qr_code_url)) {
            return $this->qr_code_url;
        }

        $path = $this->normalized_qr_code_path;

        if (! $path || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        return $path;
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

    public function workOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class)->latest();
    }

    public function assetActionRequests(): HasMany
    {
        return $this->hasMany(AssetActionRequest::class)->latest();
    }

    public function budgetPlanItems(): HasMany
    {
        return $this->hasMany(BudgetPlanItem::class)->latest();
    }

    public function workOrderEvidences(): HasMany
    {
        return $this->hasMany(WorkOrderEvidence::class)->latest('uploaded_at')->latest();
    }

    public function maintenanceRecommendations(): HasMany
    {
        return $this->hasMany(MaintenanceRecommendation::class)->latest('generated_at');
    }

    public function openMaintenanceRecommendations(): HasMany
    {
        return $this->hasMany(MaintenanceRecommendation::class)->open()->latest('generated_at');
    }

    public function highRiskRecommendations(): HasMany
    {
        return $this->hasMany(MaintenanceRecommendation::class)
            ->whereIn('risk_level', ['High', 'Critical'])
            ->unresolved()
            ->latest('generated_at');
    }

    public function openMaintenanceRequests(): HasMany
    {
        return $this->hasMany(MaintenanceRequest::class)->open()->latest();
    }

    public function openWorkOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class)->open()->latest();
    }

    public function activeWorkOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class)->open()->latest();
    }

    public function latestMaintenanceSchedule(): HasOne
    {
        return $this->hasOne(MaintenanceSchedule::class)->latestOfMany('scheduled_date');
    }

    public function lifecycleProfile(): HasOne
    {
        return $this->hasOne(EquipmentLifecycleProfile::class);
    }

    public function completedWorkOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class)->where('status', 'Completed')->latest('completed_at');
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

    public function maintenanceCostTotal(): float
    {
        return (float) $this->workOrders()->sum('total_cost');
    }

    public function repairCount(): int
    {
        return $this->completedWorkOrders()->count();
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
