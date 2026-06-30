<?php

namespace App\Filament\Resources\MaintenanceRequests\Pages;

use App\Filament\Resources\MaintenanceRequests\MaintenanceRequestResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewMaintenanceRequest extends ViewRecord
{
    protected static string $resource = MaintenanceRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            MaintenanceRequestResource::approveAction(),
            MaintenanceRequestResource::rejectAction(),
            MaintenanceRequestResource::convertAction(),
            MaintenanceRequestResource::cancelAction(),
            EditAction::make(),
        ];
    }
}
