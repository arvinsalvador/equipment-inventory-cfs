<?php

namespace App\Policies;

use App\Models\Location;
use App\Models\User;

class LocationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('master-data.manage') || $user->can('equipment.view');
    }

    public function view(User $user, Location $location): bool
    {
        return $user->can('master-data.manage') || ($location->is_active && $user->can('equipment.view'));
    }

    public function create(User $user): bool
    {
        return $user->can('master-data.manage');
    }

    public function update(User $user, Location $location): bool
    {
        return $user->can('master-data.manage');
    }

    public function delete(User $user, Location $location): bool
    {
        return $user->can('master-data.manage');
    }

    public function restore(User $user, Location $location): bool
    {
        return $user->can('master-data.manage');
    }

    public function forceDelete(User $user, Location $location): bool
    {
        return $user->can('master-data.manage');
    }
}
