<?php

namespace App\Filament\Widgets;

use App\Models\MaintenanceRecommendation;
use App\Models\WorkOrder;
use Filament\Widgets\Widget;

class WorkOrderSummary extends Widget
{
    protected string $view = 'filament.widgets.work-order-summary';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 9;

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
        return [
            'Available' => WorkOrder::query()->where('status', 'Available')->count(),
            'Assigned' => WorkOrder::query()->where('status', 'Assigned')->count(),
            'Accepted' => WorkOrder::query()->where('status', 'Accepted')->count(),
            'In Progress' => WorkOrder::query()->where('status', 'In Progress')->count(),
            'On Hold' => WorkOrder::query()->where('status', 'On Hold')->count(),
            'Completed Today' => WorkOrder::query()->where('status', 'Completed')->whereDate('completed_at', now()->toDateString())->count(),
            'Cancelled' => WorkOrder::query()->where('status', 'Cancelled')->count(),
        ];
    }
}
