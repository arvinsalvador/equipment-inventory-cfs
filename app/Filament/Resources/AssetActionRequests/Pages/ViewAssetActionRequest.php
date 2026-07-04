<?php

namespace App\Filament\Resources\AssetActionRequests\Pages;

use App\Filament\Resources\AssetActionRequests\AssetActionRequestResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewAssetActionRequest extends ViewRecord
{
    protected static string $resource = AssetActionRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            AssetActionRequestResource::submitAction(),
            AssetActionRequestResource::markUnderReviewAction(),
            AssetActionRequestResource::approveAction(),
            AssetActionRequestResource::rejectAction(),
            AssetActionRequestResource::cancelAction(),
            AssetActionRequestResource::completeAction(),
            EditAction::make(),
        ];
    }
}
