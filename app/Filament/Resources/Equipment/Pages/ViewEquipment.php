<?php

namespace App\Filament\Resources\Equipment\Pages;

use App\Filament\Resources\Equipment\EquipmentResource;
use Filament\Actions\ActionGroup;
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
            EditAction::make()
                ->label('Edit Equipment')
                ->icon('heroicon-o-pencil-square')
                ->color('primary'),
            EquipmentResource::viewPropertyCardAction(),
            EquipmentResource::openQrLookupAction(),
            ActionGroup::make([
                EquipmentResource::viewPmsChartAction(),
                EquipmentResource::printPmsChartAction(),
                EquipmentResource::downloadPmsChartAction(),
                EquipmentResource::openPmsChartAction(),
            ])
                ->label('PMS Chart')
                ->icon('heroicon-o-clipboard-document-list')
                ->color('primary')
                ->button(),
            ActionGroup::make([
                EquipmentResource::printPropertyCardAction(),
                EquipmentResource::downloadPropertyCardAction(),
                EquipmentResource::openPropertyCardAction(),
                EquipmentResource::openQrCodeFileAction(),
                EquipmentResource::generateQrCodeAction(),
                EquipmentResource::recalculateLifecycleAction(),
                EquipmentResource::refreshRecommendationsAction(),
                EquipmentResource::createAssetActionRequestAction(),
            ])
                ->label('More Actions')
                ->icon('heroicon-o-ellipsis-horizontal')
                ->color('gray')
                ->button(),
        ];
    }
}
