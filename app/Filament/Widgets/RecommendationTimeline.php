<?php

namespace App\Filament\Widgets;

use App\Models\MaintenanceRecommendation;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Collection;

class RecommendationTimeline extends Widget
{
    protected string $view = 'filament.widgets.recommendation-timeline';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 12;

    protected static bool $isLazy = false;

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
            ->latest('generated_at')
            ->limit(5)
            ->get();
    }
}
