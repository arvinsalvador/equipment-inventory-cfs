<?php

namespace App\Filament\Resources\Equipment\Pages;

use App\Filament\Resources\Equipment\EquipmentResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListEquipment extends ListRecords
{
    protected static string $resource = EquipmentResource::class;

    public function getTitle(): string
    {
        return 'Master List of Equipments';
    }

    public function getHeading(): string
    {
        return 'Master List of Equipments';
    }

    public function getBreadcrumb(): string
    {
        return 'Equipment';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('scanEquipment')
                ->label('Scan Equipment')
                ->icon('heroicon-o-qr-code')
                ->url(fn (): string => route('equipment.scan'))
                ->visible(fn (): bool => auth()->user()?->can('equipment.view') ?? false),
            CreateAction::make(),
        ];
    }
}
