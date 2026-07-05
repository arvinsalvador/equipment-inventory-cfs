<?php

namespace App\Filament\Concerns;

use Illuminate\Database\Eloquent\Builder;

trait HasSafeRelationshipSearch
{
    /**
     * @param  array<int, string>  $columns
     */
    protected static function searchRelationColumns(Builder $query, string $search, string $relation, array $columns): Builder
    {
        return $query->orWhereHas($relation, function (Builder $relationQuery) use ($columns, $search): void {
            $relationQuery->where(function (Builder $columnQuery) use ($columns, $search): void {
                foreach ($columns as $index => $column) {
                    $method = $index === 0 ? 'where' : 'orWhere';

                    $columnQuery->{$method}($column, 'like', "%{$search}%");
                }
            });
        });
    }

    protected static function searchEquipment(Builder $query, string $search): Builder
    {
        return self::searchRelationColumns($query, $search, 'equipment', [
            'equipment_code',
            'equipment_name',
        ]);
    }

    protected static function searchUserRelation(Builder $query, string $search, string $relation): Builder
    {
        return self::searchRelationColumns($query, $search, $relation, ['name']);
    }
}
