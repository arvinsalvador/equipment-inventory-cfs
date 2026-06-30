<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WorkOrder;

class WorkOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('work-orders.view');
    }

    public function view(User $user, WorkOrder $workOrder): bool
    {
        return $user->can('work-orders.view');
    }

    public function create(User $user): bool
    {
        return $user->can('work-orders.assign');
    }

    public function assign(User $user, WorkOrder $workOrder): bool
    {
        return $user->can('work-orders.assign');
    }

    public function accept(User $user, WorkOrder $workOrder): bool
    {
        return $user->can('work-orders.accept') && $workOrder->isAvailable();
    }

    public function update(User $user, WorkOrder $workOrder): bool
    {
        if ($user->can('work-orders.assign')) {
            return true;
        }

        return $this->updateAssigned($user, $workOrder);
    }

    public function updateAssigned(User $user, WorkOrder $workOrder): bool
    {
        return $user->can('work-orders.update-assigned')
            && ($workOrder->assigned_to === $user->id || $workOrder->accepted_by === $user->id);
    }

    public function uploadEvidence(User $user, WorkOrder $workOrder): bool
    {
        return $this->updateAssigned($user, $workOrder) && $user->can('work-orders.upload-evidence');
    }

    public function verify(User $user, WorkOrder $workOrder): bool
    {
        return $user->can('work-orders.verify') && $workOrder->accepted_by !== $user->id;
    }

    public function recommendBeyondRepair(User $user, WorkOrder $workOrder): bool
    {
        return $user->can('beyond-repair.recommend') && $this->updateAssigned($user, $workOrder);
    }

    public function approveBeyondRepair(User $user, WorkOrder $workOrder): bool
    {
        return $user->can('beyond-repair.approve');
    }

    public function delete(User $user, WorkOrder $workOrder): bool
    {
        return false;
    }

    public function forceDelete(User $user, WorkOrder $workOrder): bool
    {
        return false;
    }
}
