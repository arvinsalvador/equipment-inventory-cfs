<?php

namespace App\Filament\Resources\Equipment\Pages;

use App\Filament\Resources\Equipment\EquipmentResource;
use App\Models\EquipmentLocationHistory;
use Filament\Resources\Pages\EditRecord;

class EditEquipment extends EditRecord
{
    protected static string $resource = EquipmentResource::class;

    private ?int $previousLocationId = null;

    private ?string $locationTransferRemarks = null;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->previousLocationId = $this->record->current_location_id;
        $this->locationTransferRemarks = $data['location_transfer_remarks'] ?? null;
        unset($data['location_transfer_remarks']);

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

    protected function afterSave(): void
    {
        $newLocationId = $this->record->current_location_id;

        if ($this->previousLocationId === null || (int) $this->previousLocationId === (int) $newLocationId) {
            return;
        }

        EquipmentLocationHistory::create([
            'equipment_id' => $this->record->id,
            'from_location_id' => $this->previousLocationId,
            'to_location_id' => $newLocationId,
            'transferred_by' => auth()->id(),
            'transferred_at' => now(),
            'remarks' => $this->locationTransferRemarks,
        ]);
    }
}
