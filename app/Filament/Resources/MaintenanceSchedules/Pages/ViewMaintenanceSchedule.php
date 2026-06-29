<?php

namespace App\Filament\Resources\MaintenanceSchedules\Pages;

use App\Filament\Resources\MaintenanceSchedules\MaintenanceScheduleResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewMaintenanceSchedule extends ViewRecord
{
    protected static string $resource = MaintenanceScheduleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            MaintenanceScheduleResource::completeAction(),
            MaintenanceScheduleResource::rescheduleAction(),
            MaintenanceScheduleResource::cancelAction(),
            EditAction::make(),
        ];
    }
}
