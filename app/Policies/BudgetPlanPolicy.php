<?php

namespace App\Policies;

use App\Models\BudgetPlan;
use App\Models\User;

class BudgetPlanPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('budget-plans.view');
    }

    public function view(User $user, BudgetPlan $budgetPlan): bool
    {
        return $user->can('budget-plans.view');
    }

    public function create(User $user): bool
    {
        return $user->can('budget-plans.create');
    }

    public function update(User $user, BudgetPlan $budgetPlan): bool
    {
        return $user->can('budget-plans.update') && $budgetPlan->status !== 'Approved';
    }

    public function approve(User $user, BudgetPlan $budgetPlan): bool
    {
        return $user->can('budget-plans.approve') && in_array($budgetPlan->status, ['Prepared', 'Under Review'], true);
    }

    public function reject(User $user, BudgetPlan $budgetPlan): bool
    {
        return $user->can('budget-plans.approve') && in_array($budgetPlan->status, ['Prepared', 'Under Review'], true);
    }

    public function submitForReview(User $user, BudgetPlan $budgetPlan): bool
    {
        return $user->can('budget-plans.update') && in_array($budgetPlan->status, ['Draft', 'Prepared'], true);
    }

    public function cancel(User $user, BudgetPlan $budgetPlan): bool
    {
        return $user->can('budget-plans.update') && in_array($budgetPlan->status, ['Draft', 'Prepared', 'Under Review'], true);
    }

    public function manageItems(User $user, BudgetPlan $budgetPlan): bool
    {
        return $user->can('budget-plans.update') && $budgetPlan->status !== 'Approved';
    }

    public function delete(User $user, BudgetPlan $budgetPlan): bool
    {
        return false;
    }

    public function forceDelete(User $user, BudgetPlan $budgetPlan): bool
    {
        return false;
    }
}
