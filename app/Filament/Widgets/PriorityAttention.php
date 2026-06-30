<?php

namespace App\Filament\Widgets;

use App\Models\MaintenanceRecommendation;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Collection;

class PriorityAttention extends Widget
{
    protected string $view = 'filament.widgets.priority-attention';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 3;

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
            ->where('status', 'Open')
            ->where('risk_level', 'Critical')
            ->orderByRaw(MaintenanceRecommendation::riskRankSql())
            ->latest('generated_at')
            ->limit(5)
            ->get();
    }
}
