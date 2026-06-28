<?php

namespace App\Policies;

use App\Models\Equipment;
use App\Models\User;

class EquipmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('equipment.view');
    }

    public function view(User $user, Equipment $equipment): bool
    {
        return $user->can('equipment.view');
    }

    public function create(User $user): bool
    {
        return $user->can('equipment.create');
    }

    public function update(User $user, Equipment $equipment): bool
    {
        return $user->can('equipment.update');
    }

    public function archive(User $user, Equipment $equipment): bool
    {
        return $user->can('equipment.archive');
    }

    public function delete(User $user, Equipment $equipment): bool
    {
        return false;
    }

    public function forceDelete(User $user, Equipment $equipment): bool
    {
        return false;
    }
}
