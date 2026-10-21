<?php

namespace App\Policies;

use App\Models\Car;
use App\Models\User;

class CarPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('manage cars');
    }

    public function view(User $user, Car $car): bool
    {
        return $user->can('manage cars');
    }

    public function create(User $user): bool
    {
        return $user->can('manage cars');
    }

    public function update(User $user, Car $car): bool
    {
        return $user->can('manage cars');
    }

    public function submitForReview(User $user, Car $car): bool
    {
        return $user->can('manage cars');
    }

    public function approveDocuments(User $user, Car $car): bool
    {
        return $user->can('manage cars');
    }

    public function approve(User $user, Car $car): bool
    {
        return $user->can('manage cars');
    }

    public function reject(User $user, Car $car): bool
    {
        return $user->can('manage cars');
    }

    public function delete(User $user, Car $car): bool
    {
        return $user->can('manage cars');
    }

    public function restore(User $user, Car $car): bool
    {
        return $user->can('manage cars');
    }

    public function forceDelete(User $user, Car $car): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('manage cars');
    }

    public function restoreAny(User $user): bool
    {
        return $user->can('manage cars');
    }

    public function forceDeleteAny(User $user): bool
    {
        return false;
    }
}
