<?php

namespace App\Filament\Widgets;

use App\Models\MaintenanceRecommendation;
use Filament\Widgets\Widget;

class OperationsCommandHeader extends Widget
{
    protected string $view = 'filament.widgets.operations-command-header';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 1;

    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        return auth()->user()?->can('viewAny', MaintenanceRecommendation::class) ?? false;
    }

    public function lastRecommendationScan(): ?string
    {
        return MaintenanceRecommendation::query()
            ->latest('generated_at')
            ->value('generated_at')
            ?->toDayDateTimeString();
    }

    public function lastUpdated(): ?string
    {
        return MaintenanceRecommendation::query()
            ->latest('updated_at')
            ->value('updated_at')
            ?->toDayDateTimeString();
    }
}
