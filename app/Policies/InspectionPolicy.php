<?php

namespace App\Policies;

use App\Models\Inspection;
use App\Models\User;

class InspectionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('manage inspections') || $user->can('view inspections');
    }

    public function view(User $user, Inspection $inspection): bool
    {
        if ($user->can('manage inspections')) {
            return true;
        }

        if ($user->can('update assigned inspections')) {
            return $inspection->assigned_staff_id === $user->id;
        }

        return $user->can('view inspections');
    }

    public function create(User $user): bool
    {
        return $user->can('manage inspections');
    }

    public function update(User $user, Inspection $inspection): bool
    {
        return $user->can('manage inspections');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Inspection $inspection): bool
    {
        return false;
    }

    public function updateStatus(User $user, Inspection $inspection): bool
    {
        return $user->can('manage inspections')
            || ($user->can('update assigned inspections') && $inspection->assigned_staff_id === $user->id);
    }

    public function uploadDocuments(User $user, Inspection $inspection): bool
    {
        return $this->updateStatus($user, $inspection);
    }
}
