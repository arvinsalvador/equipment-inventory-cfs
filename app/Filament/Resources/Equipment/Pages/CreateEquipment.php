<?php

namespace App\Filament\Resources\Equipment\Pages;

use App\Filament\Resources\Equipment\EquipmentResource;
use Filament\Resources\Pages\CreateRecord;

class CreateEquipment extends CreateRecord
{
    protected static string $resource = EquipmentResource::class;

    public function getTitle(): string
    {
        return 'Create New Equipment';
    }

    public function getHeading(): string
    {
        return 'Create New Equipment';
    }

    public function getBreadcrumb(): string
    {
        return 'Create';
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (! auth()->user()?->can('equipment.archive')) {
            $data['is_archived'] = false;
            $data['archived_at'] = null;
            $data['archived_by'] = null;

            return $data;
        }

        if ($data['is_archived'] ?? false) {
            $data['archived_at'] = now();
            $data['archived_by'] = auth()->id();
        }

        return $data;
    }
}
