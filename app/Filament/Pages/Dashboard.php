<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\EquipmentHealth;
use App\Filament\Widgets\HighestRiskEquipment;
use App\Filament\Widgets\MaintenanceRecommendationSummary;
use App\Filament\Widgets\MaintenanceScheduleSummary;
use App\Filament\Widgets\NotificationOverview;
use App\Filament\Widgets\OperationsCommandHeader;
use App\Filament\Widgets\OperationsKpiOverview;
use App\Filament\Widgets\PriorityAttention;
use App\Filament\Widgets\QuickActions;
use App\Filament\Widgets\RecentAiRecommendations;
use App\Filament\Widgets\RecommendationActionStatus;
use App\Filament\Widgets\RecommendationRuleDistribution;
use App\Filament\Widgets\RecommendationsByRisk;
use App\Filament\Widgets\RecommendationTimeline;
use App\Filament\Widgets\WorkOrderSummary;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-command-line';

    protected static ?string $navigationLabel = 'Command Center';

    public function getTitle(): string
    {
        return 'Smart Maintenance Command Center';
    }

    public function getHeading(): string
    {
        return '';
    }

    /**
     * @return array<class-string>
     */
    public function getWidgets(): array
    {
        return [
            OperationsCommandHeader::class,
            NotificationOverview::class,
            OperationsKpiOverview::class,
            PriorityAttention::class,
            HighestRiskEquipment::class,
            RecommendationActionStatus::class,
            MaintenanceRecommendationSummary::class,
            RecommendationRuleDistribution::class,
            RecommendationsByRisk::class,
            MaintenanceScheduleSummary::class,
            WorkOrderSummary::class,
            EquipmentHealth::class,
            RecentAiRecommendations::class,
            RecommendationTimeline::class,
            QuickActions::class,
        ];
    }

    public function getColumns(): int|array
    {
        return [
            'default' => 1,
            'xl' => 2,
        ];
    }
}
