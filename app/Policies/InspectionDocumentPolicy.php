<?php

namespace App\Policies;

use App\Models\InspectionDocument;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class InspectionDocumentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('manage inspections')
            || $user->can('view inspections')
            || $user->can('update assigned inspections');
    }

    public function view(User $user, InspectionDocument $inspectionDocument): bool
    {
        return Gate::forUser($user)->allows('view', $inspectionDocument->inspection);
    }

    public function create(User $user): bool
    {
        return $user->can('manage inspections') || $user->can('update assigned inspections');
    }

    public function update(User $user, InspectionDocument $inspectionDocument): bool
    {
        return Gate::forUser($user)->allows('uploadDocuments', $inspectionDocument->inspection);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, InspectionDocument $inspectionDocument): bool
    {
        return Gate::forUser($user)->allows('update', $inspectionDocument->inspection);
    }
}
