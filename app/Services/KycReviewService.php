<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerKyc;
use App\Models\NotificationLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class KycReviewService
{
    public function markUnderReview(CustomerKyc $customerKyc): CustomerKyc
    {
        Gate::authorize('review', $customerKyc);

        return $this->transition($customerKyc, 'under_review');
    }

    public function approve(CustomerKyc $customerKyc): CustomerKyc
    {
        Gate::authorize('review', $customerKyc);

        return $this->transition($customerKyc, 'approved');
    }

    public function reject(CustomerKyc $customerKyc, string $reason): CustomerKyc
    {
        Gate::authorize('review', $customerKyc);

        $validated = Validator::make(['reason' => $reason], [
            'reason' => ['required', 'string', 'max:2000'],
        ])->validate();

        return $this->transition($customerKyc, 'rejected', $validated['reason']);
    }

    private function transition(CustomerKyc $customerKyc, string $status, ?string $reason = null): CustomerKyc
    {
        return DB::transaction(function () use ($customerKyc, $status, $reason): CustomerKyc {
            $lockedKyc = CustomerKyc::query()
                ->lockForUpdate()
                ->findOrFail($customerKyc->getKey());

            if (! in_array($lockedKyc->status, ['pending', 'under_review'], true)) {
                throw ValidationException::withMessages([
                    'status' => 'This KYC record has already been finalized.',
                ]);
            }

            $customer = Customer::query()
                ->lockForUpdate()
                ->findOrFail($lockedKyc->customer_id);

            $reviewerId = Auth::id();
            $reviewedAt = $status === 'under_review' ? null : now();
            $oldValues = [
                'status' => $lockedKyc->status,
                'rejection_reason' => $lockedKyc->rejection_reason,
                'customer_kyc_status' => $customer->kyc_status,
            ];

            $lockedKyc->update([
                'status' => $status,
                'rejection_reason' => $reason,
                'reviewer_id' => $reviewerId,
                'reviewed_at' => $reviewedAt,
            ]);

            $customer->update([
                'kyc_status' => $status,
                'kyc_submitted_at' => $customer->kyc_submitted_at ?? now(),
                'kyc_approved_at' => $status === 'approved' ? $reviewedAt : null,
                'kyc_rejection_reason' => $status === 'rejected' ? $reason : null,
            ]);

            app(AuditLogService::class)->record(
                'customer_kyc',
                "kyc.{$status}",
                $lockedKyc,
                $oldValues,
                [
                    'status' => $status,
                    'rejection_reason' => $reason,
                    'customer_kyc_status' => $customer->kyc_status,
                ],
            );

            if ($status !== 'under_review') {
                NotificationLog::query()->create([
                    'customer_id' => $customer->getKey(),
                    'type' => 'kyc',
                    'channel' => 'email',
                    'event' => "kyc.{$status}",
                    'template_identifier' => "kyc.{$status}",
                    'status' => 'queued',
                ]);
            }

            return $lockedKyc->refresh()->load(['customer', 'reviewer']);
        });
    }
}
