<?php

namespace App\Policies;

use App\Models\MaintenanceRecommendation;
use App\Models\User;

class MaintenanceRecommendationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('recommendations.view');
    }

    public function view(User $user, MaintenanceRecommendation $maintenanceRecommendation): bool
    {
        return $user->can('recommendations.view');
    }

    public function review(User $user, MaintenanceRecommendation $maintenanceRecommendation): bool
    {
        return $user->can('recommendations.review');
    }

    public function resolve(User $user, MaintenanceRecommendation $maintenanceRecommendation): bool
    {
        return $user->can('recommendations.review');
    }

    public function dismiss(User $user, MaintenanceRecommendation $maintenanceRecommendation): bool
    {
        return $user->can('recommendations.review');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, MaintenanceRecommendation $maintenanceRecommendation): bool
    {
        return $user->can('recommendations.review');
    }

    public function delete(User $user, MaintenanceRecommendation $maintenanceRecommendation): bool
    {
        return false;
    }

    public function forceDelete(User $user, MaintenanceRecommendation $maintenanceRecommendation): bool
    {
        return false;
    }
}
