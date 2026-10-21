<?php

namespace App\Policies;

use App\Models\CarImage;
use App\Models\User;

class CarImagePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('manage cars');
    }

    public function view(User $user, CarImage $carImage): bool
    {
        return $user->can('manage cars');
    }

    public function create(User $user): bool
    {
        return $user->can('manage cars');
    }

    public function update(User $user, CarImage $carImage): bool
    {
        return $user->can('manage cars');
    }

    public function delete(User $user, CarImage $carImage): bool
    {
        return $user->can('manage cars');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('manage cars');
    }
}
