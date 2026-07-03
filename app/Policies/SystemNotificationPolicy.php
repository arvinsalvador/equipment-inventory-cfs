<?php

namespace App\Policies;

use App\Models\SystemNotification;
use App\Models\User;

class SystemNotificationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('access admin panel');
    }

    public function view(User $user, SystemNotification $systemNotification): bool
    {
        return $this->viewAll($user)
            || $systemNotification->user_id === null
            || $systemNotification->user_id === $user->id;
    }

    public function markRead(User $user, SystemNotification $systemNotification): bool
    {
        return $this->viewAll($user) || $systemNotification->user_id === $user->id;
    }

    public function markUnread(User $user, SystemNotification $systemNotification): bool
    {
        return $this->markRead($user, $systemNotification);
    }

    public function delete(User $user, SystemNotification $systemNotification): bool
    {
        return false;
    }

    public function forceDelete(User $user, SystemNotification $systemNotification): bool
    {
        return false;
    }

    private function viewAll(User $user): bool
    {
        return $user->can('access admin panel') && $user->hasRole('Administrator');
    }
}
