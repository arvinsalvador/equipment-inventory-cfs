<?php

namespace App\Filament\Widgets;

use App\Models\MaintenanceRecommendation;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class HighestRiskEquipment extends Widget
{
    protected string $view = 'filament.widgets.highest-risk-equipment';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->can('viewAny', MaintenanceRecommendation::class) ?? false;
    }

    /**
     * @return Collection<int, object>
     */
    public function rows(): Collection
    {
        return MaintenanceRecommendation::query()
            ->join('equipment', 'equipment.id', '=', 'maintenance_recommendations.equipment_id')
            ->select([
                'equipment.equipment_code',
                'equipment.equipment_name',
                DB::raw('min('.MaintenanceRecommendation::riskRankSql('maintenance_recommendations.risk_level').') as risk_rank'),
                DB::raw('count(*) as open_recommendation_count'),
            ])
            ->where('maintenance_recommendations.status', 'Open')
            ->groupBy('equipment.id', 'equipment.equipment_code', 'equipment.equipment_name')
            ->orderBy('risk_rank')
            ->orderByDesc('open_recommendation_count')
            ->limit(10)
            ->get()
            ->map(function (object $row): object {
                $row->highest_risk = match ((int) $row->risk_rank) {
                    1 => 'Critical',
                    2 => 'High',
                    3 => 'Moderate',
                    4 => 'Low',
                    default => 'None',
                };

                return $row;
            });
    }
}
