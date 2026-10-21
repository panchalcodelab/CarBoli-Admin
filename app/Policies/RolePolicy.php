<?php

namespace App\Policies;

use App\Models\User;
use Spatie\Permission\Models\Role;

class RolePolicy
{
    /**
     * Roles that must never be edited or deleted through the ordinary
     * Role Management UI. "Super Admin" is the protected system role whose
     * permission set and existence underpin the application's top-level access.
     *
     * @var list<string>
     */
    public const PROTECTED_ROLES = ['Super Admin'];

    public function viewAny(User $user): bool
    {
        return $user->can('manage roles');
    }

    public function view(User $user, Role $role): bool
    {
        return $user->can('manage roles');
    }

    public function create(User $user): bool
    {
        return $user->can('manage roles');
    }

    /**
     * A role may be edited by a roles manager, except protected system roles.
     * Only a Super Admin may edit a protected system role (kept for exceptional,
     * explicitly authorized changes — the UI does not expose this path).
     */
    public function update(User $user, Role $role): bool
    {
        if (! $user->can('manage roles')) {
            return false;
        }

        if ($this->isProtected($role)) {
            return $user->hasRole('Super Admin');
        }

        return true;
    }

    /**
     * A role may be deleted only by a roles manager, only when it is not a
     * protected system role. Assignment checks (role still in use) are enforced
     * in the service with a user-facing validation message.
     */
    public function delete(User $user, Role $role): bool
    {
        if (! $user->can('manage roles')) {
            return false;
        }

        return ! $this->isProtected($role);
    }

    public function isProtected(Role $role): bool
    {
        return in_array($role->name, self::PROTECTED_ROLES, true);
    }
}
