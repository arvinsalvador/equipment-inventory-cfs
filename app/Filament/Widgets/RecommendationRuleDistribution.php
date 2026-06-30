<?php

namespace App\Filament\Widgets;

use App\Models\MaintenanceRecommendation;
use Filament\Widgets\Widget;

class RecommendationRuleDistribution extends Widget
{
    protected string $view = 'filament.widgets.recommendation-rule-distribution';

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
        return collect(MaintenanceRecommendation::RULE_KEYS)
            ->mapWithKeys(fn (string $rule): array => [
                $rule => MaintenanceRecommendation::query()->where('rule_key', $rule)->count(),
            ])
            ->all();
    }
}
