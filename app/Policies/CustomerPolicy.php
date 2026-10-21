<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;

class CustomerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view customers') || $user->can('manage customers');
    }

    public function view(User $user, Customer $customer): bool
    {
        return $user->can('view customers') || $user->can('manage customers');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Customer $customer): bool
    {
        return false;
    }

    public function delete(User $user, Customer $customer): bool
    {
        return false;
    }

    public function block(User $user, Customer $customer): bool
    {
        return $user->can('manage customers');
    }

    public function unblock(User $user, Customer $customer): bool
    {
        return $user->can('manage customers');
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
