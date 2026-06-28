<?php

namespace App\Policies;

use App\Models\EquipmentCategory;
use App\Models\User;

class EquipmentCategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('master-data.manage') || $user->can('equipment.view');
    }

    public function view(User $user, EquipmentCategory $equipmentCategory): bool
    {
        return $user->can('master-data.manage') || ($equipmentCategory->is_active && $user->can('equipment.view'));
    }

    public function create(User $user): bool
    {
        return $user->can('master-data.manage');
    }

    public function update(User $user, EquipmentCategory $equipmentCategory): bool
    {
        return $user->can('master-data.manage');
    }

    public function delete(User $user, EquipmentCategory $equipmentCategory): bool
    {
        return $user->can('master-data.manage');
    }

    public function restore(User $user, EquipmentCategory $equipmentCategory): bool
    {
        return $user->can('master-data.manage');
    }

    public function forceDelete(User $user, EquipmentCategory $equipmentCategory): bool
    {
        return $user->can('master-data.manage');
    }
}
