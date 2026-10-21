<?php

namespace App\Policies;

use App\Models\NotificationLog;
use App\Models\User;

class NotificationLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('manage notifications');
    }

    public function view(User $user, NotificationLog $notificationLog): bool
    {
        return $user->can('manage notifications');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, NotificationLog $notificationLog): bool
    {
        return false;
    }

    public function delete(User $user, NotificationLog $notificationLog): bool
    {
        return false;
    }

    public function restore(User $user, NotificationLog $notificationLog): bool
    {
        return false;
    }

    public function forceDelete(User $user, NotificationLog $notificationLog): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
