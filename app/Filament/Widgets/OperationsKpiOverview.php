<?php

namespace App\Filament\Widgets;

use App\Models\Equipment;
use App\Models\MaintenanceRecommendation;
use App\Models\MaintenanceRequest;
use App\Models\MaintenanceSchedule;
use App\Models\WorkOrder;
use Filament\Widgets\Widget;

class OperationsKpiOverview extends Widget
{
    protected string $view = 'filament.widgets.operations-kpi-overview';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 2;

    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        return auth()->user()?->can('viewAny', MaintenanceRecommendation::class) ?? false;
    }

    /**
     * @return array<int, array<string, string|int>>
     */
    public function stats(): array
    {
        return [
            [
                'label' => 'Equipment',
                'value' => Equipment::query()->where('is_archived', false)->count(),
                'description' => 'Active inventory assets',
                'color' => 'blue',
                'icon' => 'heroicon-o-cpu-chip',
            ],
            [
                'label' => 'Work Orders',
                'value' => WorkOrder::query()->whereNotIn('status', ['Completed', 'Cancelled'])->count(),
                'description' => 'Active maintenance workload',
                'color' => 'orange',
                'icon' => 'heroicon-o-wrench-screwdriver',
            ],
            [
                'label' => 'Open Maintenance Requests',
                'value' => MaintenanceRequest::query()->whereNotIn('status', ['Rejected', 'Cancelled', 'Converted'])->count(),
                'description' => 'Requests awaiting closure',
                'color' => 'purple',
                'icon' => 'heroicon-o-inbox-stack',
            ],
            [
                'label' => 'Preventive Maintenance Due',
                'value' => MaintenanceSchedule::query()
                    ->whereNotIn('status', ['Completed', 'Cancelled'])
                    ->whereDate('scheduled_date', '<=', now()->addWeek()->toDateString())
                    ->count(),
                'description' => 'Due now or this week',
                'color' => 'green',
                'icon' => 'heroicon-o-calendar-days',
            ],
            [
                'label' => 'Critical Recommendations',
                'value' => MaintenanceRecommendation::query()->where('status', 'Open')->where('risk_level', 'Critical')->count(),
                'description' => 'Immediate attention needed',
                'color' => 'red',
                'icon' => 'heroicon-o-exclamation-triangle',
            ],
            [
                'label' => 'Pending Recommendation Actions',
                'value' => MaintenanceRecommendation::query()
                    ->where('status', 'Open')
                    ->where(fn ($query) => $query->where('action_status', 'Pending')->orWhereNull('action_status'))
                    ->count(),
                'description' => 'Awaiting human decision',
                'color' => 'yellow',
                'icon' => 'heroicon-o-clock',
            ],
        ];
    }
}
