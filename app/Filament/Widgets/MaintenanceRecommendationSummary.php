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
        return collect(MaintenanceRecommendation::STATUSES)
            ->mapWithKeys(fn (string $status): array => [
                $status => MaintenanceRecommendation::query()->where('status', $status)->count(),
            ])
            ->all();
    }
}
