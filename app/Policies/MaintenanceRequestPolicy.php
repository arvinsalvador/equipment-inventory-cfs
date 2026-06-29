<?php

namespace App\Policies;

use App\Models\MaintenanceRequest;
use App\Models\User;

class MaintenanceRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('maintenance-requests.review')
            || $user->can('maintenance-requests.submit')
            || $user->can('equipment.view');
    }

    public function view(User $user, MaintenanceRequest $maintenanceRequest): bool
    {
        return $user->can('maintenance-requests.review')
            || $user->can('view', $maintenanceRequest->equipment)
            || $maintenanceRequest->submitted_by === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->can('maintenance-requests.submit');
    }

    public function update(User $user, MaintenanceRequest $maintenanceRequest): bool
    {
        return $user->can('maintenance-requests.review');
    }

    public function review(User $user, MaintenanceRequest $maintenanceRequest): bool
    {
        return $user->can('maintenance-requests.review');
    }

    public function approve(User $user, MaintenanceRequest $maintenanceRequest): bool
    {
        return $this->review($user, $maintenanceRequest);
    }

    public function reject(User $user, MaintenanceRequest $maintenanceRequest): bool
    {
        return $this->review($user, $maintenanceRequest);
    }

    public function convert(User $user, MaintenanceRequest $maintenanceRequest): bool
    {
        return $this->review($user, $maintenanceRequest);
    }

    public function cancel(User $user, MaintenanceRequest $maintenanceRequest): bool
    {
        return $maintenanceRequest->submitted_by === $user->id
            && ! $maintenanceRequest->isApproved()
            && ! $maintenanceRequest->isClosed();
    }

    public function delete(User $user, MaintenanceRequest $maintenanceRequest): bool
    {
        return false;
    }

    public function forceDelete(User $user, MaintenanceRequest $maintenanceRequest): bool
    {
        return false;
    }
}
