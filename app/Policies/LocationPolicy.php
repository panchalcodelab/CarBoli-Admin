<?php

namespace App\Policies;

use App\Models\Location;
use App\Models\User;

class LocationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('manage cars');
    }

    public function view(User $user, Location $location): bool
    {
        return $user->can('manage cars');
    }

    public function create(User $user): bool
    {
        return $user->can('manage cars');
    }

    public function update(User $user, Location $location): bool
    {
        return $user->can('manage cars');
    }

    public function delete(User $user, Location $location): bool
    {
        return $user->can('manage cars');
    }

    public function restore(User $user, Location $location): bool
    {
        return false;
    }

    public function forceDelete(User $user, Location $location): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('manage cars');
    }
}
