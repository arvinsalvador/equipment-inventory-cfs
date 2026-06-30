<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WorkOrderEvidence;

class WorkOrderEvidencePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('work-orders.view');
    }

    public function view(User $user, WorkOrderEvidence $workOrderEvidence): bool
    {
        return $user->can('work-orders.view');
    }

    public function create(User $user): bool
    {
        return $user->can('work-orders.upload-evidence') || $user->can('work-orders.assign');
    }

    public function upload(User $user, WorkOrderEvidence $workOrderEvidence): bool
    {
        $workOrder = $workOrderEvidence->workOrder;

        if ($user->can('work-orders.assign')) {
            return ! $workOrder->isCancelled();
        }

        return $user->can('work-orders.upload-evidence')
            && ! $workOrder->isCompleted()
            && ! $workOrder->isCancelled()
            && ($workOrder->assigned_to === $user->id || $workOrder->accepted_by === $user->id);
    }

    public function delete(User $user, WorkOrderEvidence $workOrderEvidence): bool
    {
        return $user->can('work-orders.assign');
    }

    public function forceDelete(User $user, WorkOrderEvidence $workOrderEvidence): bool
    {
        return false;
    }
}
