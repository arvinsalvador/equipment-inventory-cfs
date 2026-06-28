<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('users.viewAny');
    }

    public function view(User $user, User $model): bool
    {
        return $user->can('users.view');
    }

    public function create(User $user): bool
    {
        return $user->can('users.create');
    }

    public function update(User $user, User $model): bool
    {
        return $user->can('users.update');
    }

    public function delete(User $user, User $model): bool
    {
        return $user->can('users.delete')
            && ! $user->is($model)
            && $this->canRemoveAdministratorRole($model, null);
    }

    public function forceDelete(User $user, User $model): bool
    {
        return $this->delete($user, $model);
    }

    public function assignRoles(User $user, User $model): bool
    {
        return $user->can('users.assignRoles');
    }

    public function canRemoveAdministratorRole(User $model, ?string $newRole): bool
    {
        if (! $model->hasRole('Administrator') || $newRole === 'Administrator') {
            return true;
        }

        return User::role('Administrator')->whereKeyNot($model->getKey())->exists();
    }
}
