<?php

namespace App\Services;

use App\Models\Customer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CustomerAccountService
{
    public function __construct(private AuditLogService $auditLogService) {}

    public function block(Customer $customer): Customer
    {
        Gate::authorize('block', $customer);

        return DB::transaction(function () use ($customer): Customer {
            $lockedCustomer = Customer::query()
                ->lockForUpdate()
                ->findOrFail($customer->getKey());

            if ($lockedCustomer->account_status === 'blocked') {
                return $lockedCustomer;
            }

            if ($lockedCustomer->account_status !== 'active') {
                throw ValidationException::withMessages([
                    'account_status' => 'Only active customer accounts can be blocked.',
                ]);
            }

            $oldValues = [
                'account_status' => $lockedCustomer->account_status,
                'is_blocked' => $lockedCustomer->is_blocked,
            ];

            $lockedCustomer->update([
                'account_status' => 'blocked',
                'is_blocked' => true,
            ]);

            $this->auditLogService->record('customers', 'customer.blocked', $lockedCustomer, $oldValues, [
                'account_status' => 'blocked',
                'is_blocked' => true,
            ]);

            return $lockedCustomer->refresh();
        });
    }

    public function unblock(Customer $customer): Customer
    {
        Gate::authorize('unblock', $customer);

        return DB::transaction(function () use ($customer): Customer {
            $lockedCustomer = Customer::query()
                ->lockForUpdate()
                ->findOrFail($customer->getKey());

            if ($lockedCustomer->account_status !== 'blocked' && ! $lockedCustomer->is_blocked) {
                return $lockedCustomer;
            }

            $oldValues = [
                'account_status' => $lockedCustomer->account_status,
                'is_blocked' => $lockedCustomer->is_blocked,
            ];

            $lockedCustomer->update([
                'account_status' => 'active',
                'is_blocked' => false,
            ]);

            $this->auditLogService->record('customers', 'customer.unblocked', $lockedCustomer, $oldValues, [
                'account_status' => 'active',
                'is_blocked' => false,
            ]);

            return $lockedCustomer->refresh();
        });
    }
}
