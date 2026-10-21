<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('manage staff');
    }

    public function view(User $user, User $staff): bool
    {
        return $user->can('manage staff');
    }

    public function create(User $user): bool
    {
        return $user->can('manage staff');
    }

    public function update(User $user, User $staff): bool
    {
        return $user->can('manage staff') && $user->isNot($staff);
    }

    /**
     * A staff manager may delete another staff account, but never their own.
     * A Super Admin account may be deleted only by another Super Admin, so an
     * ordinary staff manager cannot remove top-level access, while a Super
     * Admin can still remove a different Super Admin.
     */
    public function delete(User $user, User $staff): bool
    {
        if (! $user->can('manage staff') || $user->is($staff)) {
            return false;
        }

        if ($staff->hasRole('Super Admin')) {
            return $user->hasRole('Super Admin');
        }

        return true;
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('manage staff');
    }
}
