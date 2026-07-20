<?php

namespace App\Filament\Resources\Equipment\Pages;

use App\Filament\Resources\Equipment\EquipmentResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewEquipment extends ViewRecord
{
    protected static string $resource = EquipmentResource::class;

    public function getTitle(): string
    {
        return 'View Equipment';
    }

    public function getHeading(): string
    {
        return 'View Equipment';
    }

    public function getBreadcrumb(): string
    {
        return 'View';
    }

    protected function getHeaderActions(): array
    {
        return [
            EquipmentResource::openQrLookupAction(),
            EquipmentResource::openQrCodeFileAction(),
            EquipmentResource::generateQrCodeAction(),
            EquipmentResource::recalculateLifecycleAction(),
            EquipmentResource::createAssetActionRequestAction(),
            EditAction::make(),
        ];
    }
}
