<?php

namespace App\Filament\Resources\AssetActionRequests\Pages;

use App\Filament\Resources\AssetActionRequests\AssetActionRequestResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAssetActionRequest extends CreateRecord
{
    protected static string $resource = AssetActionRequestResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['requested_by'] = auth()->id();
        $data['status'] = 'Draft';

        return $data;
    }
}
