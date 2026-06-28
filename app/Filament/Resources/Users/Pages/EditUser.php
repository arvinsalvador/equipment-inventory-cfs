<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Policies\UserPolicy;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function afterSave(): void
    {
        $role = $this->form->getRawState()['role'] ?? null;

        if ($role === null || ! auth()->user()?->can('assignRoles', $this->record)) {
            return;
        }

        if (! app(UserPolicy::class)->canRemoveAdministratorRole($this->record, $role)) {
            throw ValidationException::withMessages([
                'role' => 'The last Administrator account cannot be demoted.',
            ]);
        }

        $this->record->syncRoles([$role]);
    }
}
