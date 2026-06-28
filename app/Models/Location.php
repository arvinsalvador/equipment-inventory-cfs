<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

#[Fillable(['name', 'type', 'parent_id', 'description', 'is_active'])]
class Location extends Model
{
    use HasFactory;

    public const TYPES = [
        'Campus',
        'Building',
        'Room',
        'Area',
        'Office',
        'Other',
    ];

    protected static function booted(): void
    {
        static::saving(function (Location $location) {
            if ($location->parent_id !== null && $location->getKey() !== null && (int) $location->parent_id === (int) $location->getKey()) {
                throw ValidationException::withMessages([
                    'parent_id' => 'A location cannot be its own parent.',
                ]);
            }
        });
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
