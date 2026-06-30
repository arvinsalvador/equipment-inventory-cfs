<?php

namespace App\Filament\Widgets;

use App\Models\MaintenanceRecommendation;
use Filament\Widgets\Widget;

class MaintenanceRecommendationSummary extends Widget
{
    protected string $view = 'filament.widgets.maintenance-recommendation-summary';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->can('viewAny', MaintenanceRecommendation::class) ?? false;
    }

    /**
     * @return array<string, int>
     */
    public function counts(): array
    {
        return [
            'Open Recommendations' => MaintenanceRecommendation::query()->where('status', 'Open')->count(),
            'Reviewed Recommendations' => MaintenanceRecommendation::query()->where('status', 'Reviewed')->count(),
            'Resolved Recommendations' => MaintenanceRecommendation::query()->where('status', 'Resolved')->count(),
            'Dismissed Recommendations' => MaintenanceRecommendation::query()->where('status', 'Dismissed')->count(),
            'Critical Recommendations' => MaintenanceRecommendation::query()->where('risk_level', 'Critical')->count(),
            'High Recommendations' => MaintenanceRecommendation::query()->where('risk_level', 'High')->count(),
        ];
    }
}
