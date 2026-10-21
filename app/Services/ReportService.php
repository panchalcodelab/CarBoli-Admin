<?php

namespace App\Services;

use App\Models\Auction;
use App\Models\Customer;
use App\Models\Inspection;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Validator;

class ReportService
{
    /**
     * @return array{auction_count: ?int, payment_count: ?int, captured_payment_total: ?string, customer_count: ?int, inspection_count: ?int}
     */
    public function summary(User $user, ?string $from = null, ?string $to = null): array
    {
        $validated = Validator::make(compact('from', 'to'), [
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ])->validate();

        $canViewFinancials = $user->can('view financial reports');
        $canViewCustomers = $user->can('view customers');
        $auctionQuery = $this->applyDateRange(Auction::query(), $validated['from'] ?? null, $validated['to'] ?? null);
        $paymentQuery = $this->applyDateRange(Payment::query(), $validated['from'] ?? null, $validated['to'] ?? null);
        $customerQuery = $this->applyDateRange(Customer::query(), $validated['from'] ?? null, $validated['to'] ?? null);
        $inspectionQuery = $this->applyDateRange(Inspection::query(), $validated['from'] ?? null, $validated['to'] ?? null);

        return [
            'auction_count' => $canViewFinancials ? $auctionQuery->count() : null,
            'payment_count' => $canViewFinancials ? $paymentQuery->count() : null,
            'captured_payment_total' => $canViewFinancials
                ? (clone $paymentQuery)->where('status', 'captured')->sum('amount')
                : null,
            'customer_count' => $canViewCustomers ? $customerQuery->count() : null,
            'inspection_count' => $canViewFinancials ? $inspectionQuery->count() : null,
        ];
    }

    private function applyDateRange(Builder $query, ?string $from, ?string $to): Builder
    {
        return $query
            ->when($from, fn (Builder $query): Builder => $query->whereDate('created_at', '>=', $from))
            ->when($to, fn (Builder $query): Builder => $query->whereDate('created_at', '<=', $to));
    }
}
