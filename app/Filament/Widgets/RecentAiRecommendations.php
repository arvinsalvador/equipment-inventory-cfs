<?php

namespace App\Filament\Widgets;

use App\Models\MaintenanceRecommendation;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Collection;

class RecentAiRecommendations extends Widget
{
    protected string $view = 'filament.widgets.recent-ai-recommendations';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->can('viewAny', MaintenanceRecommendation::class) ?? false;
    }

    /**
     * @return Collection<int, MaintenanceRecommendation>
     */
    public function records(): Collection
    {
        return MaintenanceRecommendation::query()
            ->with('equipment')
            ->open()
            ->orderByRaw(MaintenanceRecommendation::riskRankSql())
            ->latest('generated_at')
            ->limit(10)
            ->get();
    }
}
