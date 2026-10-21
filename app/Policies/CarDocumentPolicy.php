<?php

namespace App\Policies;

use App\Models\CarDocument;
use App\Models\User;

class CarDocumentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('manage cars');
    }

    public function view(User $user, CarDocument $carDocument): bool
    {
        return $user->can('manage cars');
    }

    public function create(User $user): bool
    {
        return $user->can('manage cars');
    }

    public function update(User $user, CarDocument $carDocument): bool
    {
        return $user->can('manage cars');
    }

    public function delete(User $user, CarDocument $carDocument): bool
    {
        return $user->can('manage cars');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('manage cars');
    }
}
