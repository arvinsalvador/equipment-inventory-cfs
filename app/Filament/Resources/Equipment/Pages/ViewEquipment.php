<?php

namespace App\Filament\Resources\Equipment\Pages;

use App\Filament\Resources\Equipment\EquipmentResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewEquipment extends ViewRecord
{
    protected static string $resource = EquipmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EquipmentResource::openQrLookupAction(),
            EquipmentResource::openQrCodeFileAction(),
            EquipmentResource::generateQrCodeAction(),
            EquipmentResource::recalculateLifecycleAction(),
            EditAction::make(),
        ];
    }
}
