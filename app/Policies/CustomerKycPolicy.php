<?php

namespace App\Policies;

use App\Models\CustomerKyc;
use App\Models\User;

class CustomerKycPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view customer kyc') || $user->can('approve kyc');
    }

    public function view(User $user, CustomerKyc $customerKyc): bool
    {
        return $user->can('view customer kyc') || $user->can('approve kyc');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, CustomerKyc $customerKyc): bool
    {
        return false;
    }

    public function delete(User $user, CustomerKyc $customerKyc): bool
    {
        return false;
    }

    public function review(User $user, CustomerKyc $customerKyc): bool
    {
        return $user->can('approve kyc');
    }
}
