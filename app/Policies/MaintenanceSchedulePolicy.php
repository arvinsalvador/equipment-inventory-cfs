<?php

namespace App\Policies;

use App\Models\MaintenanceSchedule;
use App\Models\User;

class MaintenanceSchedulePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('maintenance-schedules.manage') || $user->can('equipment.view');
    }

    public function view(User $user, MaintenanceSchedule $maintenanceSchedule): bool
    {
        return $user->can('maintenance-schedules.manage') || $user->can('view', $maintenanceSchedule->equipment);
    }

    public function create(User $user): bool
    {
        return $user->can('maintenance-schedules.manage');
    }

    public function update(User $user, MaintenanceSchedule $maintenanceSchedule): bool
    {
        return $user->can('maintenance-schedules.manage');
    }

    public function complete(User $user, MaintenanceSchedule $maintenanceSchedule): bool
    {
        return $user->can('maintenance-schedules.manage');
    }

    public function reschedule(User $user, MaintenanceSchedule $maintenanceSchedule): bool
    {
        return $user->can('maintenance-schedules.manage');
    }

    public function cancel(User $user, MaintenanceSchedule $maintenanceSchedule): bool
    {
        return $user->can('maintenance-schedules.manage');
    }

    public function delete(User $user, MaintenanceSchedule $maintenanceSchedule): bool
    {
        return $user->can('maintenance-schedules.manage');
    }

    public function forceDelete(User $user, MaintenanceSchedule $maintenanceSchedule): bool
    {
        return false;
    }
}
