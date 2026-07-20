<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['account_code', 'name', 'description', 'is_active'])]
class EquipmentCategory extends Model
{
    use HasFactory;

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function displayName(): string
    {
        return trim(collect([$this->account_code, $this->name])->filter()->implode(' - '));
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->displayName();
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
