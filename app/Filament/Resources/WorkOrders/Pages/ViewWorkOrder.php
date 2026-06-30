<?php

namespace App\Filament\Resources\WorkOrders\Pages;

use App\Filament\Resources\WorkOrders\WorkOrderResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewWorkOrder extends ViewRecord
{
    protected static string $resource = WorkOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            WorkOrderResource::assignAction(),
            WorkOrderResource::makeAvailableAction(),
            WorkOrderResource::acceptAction(),
            WorkOrderResource::startAction(),
            WorkOrderResource::putOnHoldAction(),
            WorkOrderResource::awaitPartsAction(),
            WorkOrderResource::submitForVerificationAction(),
            WorkOrderResource::completeAction(),
            WorkOrderResource::beyondRepairAction(),
            WorkOrderResource::verifyAction(),
            WorkOrderResource::reopenAction(),
            WorkOrderResource::cancelAction(),
            EditAction::make(),
        ];
    }
}
