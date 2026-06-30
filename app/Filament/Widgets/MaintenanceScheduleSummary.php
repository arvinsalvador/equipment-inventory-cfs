<?php

namespace App\Filament\Widgets;

use App\Models\MaintenanceRecommendation;
use App\Models\MaintenanceSchedule;
use Filament\Widgets\Widget;

class MaintenanceScheduleSummary extends Widget
{
    protected string $view = 'filament.widgets.maintenance-schedule-summary';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 8;

    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        return auth()->user()?->can('viewAny', MaintenanceRecommendation::class) ?? false;
    }

    /**
     * @return array<string, int>
     */
    public function counts(): array
    {
        $active = MaintenanceSchedule::query()->whereNotIn('status', ['Completed', 'Cancelled']);

        return [
            'Due Today' => (clone $active)->whereDate('scheduled_date', now()->toDateString())->count(),
            'Due This Week' => (clone $active)
                ->whereBetween('scheduled_date', [now()->startOfDay(), now()->addWeek()->endOfDay()])
                ->count(),
            'Overdue' => (clone $active)->whereDate('scheduled_date', '<', now()->toDateString())->count(),
            'Upcoming' => (clone $active)->whereDate('scheduled_date', '>', now()->addWeek()->toDateString())->count(),
        ];
    }
}
