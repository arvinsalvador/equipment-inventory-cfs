<?php

namespace App\Filament\Resources\AssetActionRequests\Pages;

use App\Filament\Resources\AssetActionRequests\AssetActionRequestResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAssetActionRequests extends ListRecords
{
    protected static string $resource = AssetActionRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
