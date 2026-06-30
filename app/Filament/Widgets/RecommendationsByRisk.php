<?php

namespace App\Filament\Widgets;

use App\Models\MaintenanceRecommendation;
use Filament\Widgets\Widget;

class RecommendationsByRisk extends Widget
{
    protected string $view = 'filament.widgets.recommendations-by-risk';

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
        return collect(['Critical', 'High', 'Moderate', 'Low'])
            ->mapWithKeys(fn (string $risk): array => [
                $risk => MaintenanceRecommendation::query()->where('risk_level', $risk)->count(),
            ])
            ->all();
    }
}
