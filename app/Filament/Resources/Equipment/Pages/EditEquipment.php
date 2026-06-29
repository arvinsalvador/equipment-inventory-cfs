<?php

namespace App\Filament\Resources\Equipment\Pages;

use App\Filament\Resources\Equipment\EquipmentResource;
use Filament\Resources\Pages\EditRecord;

class EditEquipment extends EditRecord
{
    protected static string $resource = EquipmentResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (! auth()->user()?->can('archive', $this->record)) {
            unset($data['is_archived'], $data['archived_at'], $data['archived_by']);

            return $data;
        }

        if (($data['is_archived'] ?? false) && ! $this->record->is_archived) {
            $data['archived_at'] = now();
            $data['archived_by'] = auth()->id();
        }

        return $data;
    }
}
