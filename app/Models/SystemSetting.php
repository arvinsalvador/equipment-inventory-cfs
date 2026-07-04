<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'group',
    'key',
    'value',
    'value_type',
    'label',
    'description',
    'is_public',
    'is_editable',
    'metadata',
])]
class SystemSetting extends Model
{
    public const VALUE_TYPES = [
        'string',
        'text',
        'integer',
        'decimal',
        'boolean',
        'date',
        'time',
        'json',
    ];

    public function scopeGroup(Builder $query, string $group): Builder
    {
        return $query->where('group', $group);
    }

    public function scopePublic(Builder $query): Builder
    {
        return $query->where('is_public', true);
    }

    public function scopeEditable(Builder $query): Builder
    {
        return $query->where('is_editable', true);
    }

    public function getTypedValue(): mixed
    {
        return match ($this->value_type) {
            'integer' => $this->value === null ? null : (int) $this->value,
            'decimal' => $this->value === null ? null : (float) $this->value,
            'boolean' => filter_var($this->value, FILTER_VALIDATE_BOOLEAN),
            'json' => $this->value === null || $this->value === '' ? null : json_decode($this->value, true),
            default => $this->value,
        };
    }

    public function setTypedValue(mixed $value): self
    {
        $this->value = match ($this->value_type) {
            'boolean' => $value ? '1' : '0',
            'json' => $value === null || $value === '' ? null : json_encode($value, JSON_UNESCAPED_SLASHES),
            default => $value === null ? null : (string) $value,
        };

        return $this;
    }

    public function isBoolean(): bool
    {
        return $this->value_type === 'boolean';
    }

    public function isJson(): bool
    {
        return $this->value_type === 'json';
    }

    public function isPublic(): bool
    {
        return (bool) $this->is_public;
    }

    public function isEditable(): bool
    {
        return $this->is_editable !== false;
    }

    protected function casts(): array
    {
        return [
            'is_public' => 'boolean',
            'is_editable' => 'boolean',
            'metadata' => 'array',
        ];
    }
}
