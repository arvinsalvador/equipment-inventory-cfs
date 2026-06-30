<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Equipment\EquipmentResource;
use App\Filament\Resources\MaintenanceRecommendations\MaintenanceRecommendationResource;
use App\Filament\Resources\MaintenanceRequests\MaintenanceRequestResource;
use App\Filament\Resources\MaintenanceSchedules\MaintenanceScheduleResource;
use App\Filament\Resources\WorkOrders\WorkOrderResource;
use App\Models\MaintenanceRecommendation;
use Filament\Widgets\Widget;

class QuickActions extends Widget
{
    protected string $view = 'filament.widgets.quick-actions';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 13;

    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        return auth()->user()?->can('viewAny', MaintenanceRecommendation::class) ?? false;
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function actions(): array
    {
        return [
            ['label' => 'Run Recommendation Scan', 'icon' => 'heroicon-o-arrow-path', 'url' => MaintenanceRecommendationResource::getUrl('index'), 'color' => 'blue'],
            ['label' => 'Create Work Order', 'icon' => 'heroicon-o-wrench-screwdriver', 'url' => WorkOrderResource::getUrl('index'), 'color' => 'orange'],
            ['label' => 'Create Maintenance Schedule', 'icon' => 'heroicon-o-calendar-days', 'url' => MaintenanceScheduleResource::getUrl('create'), 'color' => 'green'],
            ['label' => 'View Critical Recommendations', 'icon' => 'heroicon-o-exclamation-triangle', 'url' => MaintenanceRecommendationResource::getUrl('index'), 'color' => 'red'],
            ['label' => 'View Equipment', 'icon' => 'heroicon-o-cpu-chip', 'url' => EquipmentResource::getUrl('index'), 'color' => 'gray'],
            ['label' => 'View Requests', 'icon' => 'heroicon-o-inbox-stack', 'url' => MaintenanceRequestResource::getUrl('index'), 'color' => 'purple'],
        ];
    }
}
