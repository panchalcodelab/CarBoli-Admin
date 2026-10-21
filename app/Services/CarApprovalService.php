<?php

namespace App\Services;

use App\Models\Car;
use App\Models\Inspection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Owns the car review/approval lifecycle (Q6):
 *   draft -> under_review -> available (approved) | draft (rejected)
 *
 * Document approval and a completed inspection are prerequisites for final
 * approval. Every transition is authorized server-side and audit-logged.
 */
class CarApprovalService
{
    public function __construct(private AuditLogService $auditLogService) {}

    public function submitForReview(Car $car): Car
    {
        Gate::authorize('submitForReview', $car);

        return DB::transaction(function () use ($car): Car {
            $lockedCar = Car::query()->lockForUpdate()->findOrFail($car->getKey());

            if (! in_array($lockedCar->status, ['draft', 'under_review'], true)) {
                throw ValidationException::withMessages([
                    'status' => 'Only draft cars can be submitted for review.',
                ]);
            }

            $lockedCar->update([
                'status' => 'under_review',
                'submitted_for_review_at' => now(),
                'rejection_reason' => null,
            ]);

            $this->auditLogService->record('cars', 'car.submitted_for_review', $lockedCar, null, [
                'status' => 'under_review',
            ]);

            return $lockedCar->refresh();
        });
    }

    public function approveDocuments(Car $car): Car
    {
        Gate::authorize('approveDocuments', $car);

        return DB::transaction(function () use ($car): Car {
            $lockedCar = Car::query()->lockForUpdate()->findOrFail($car->getKey());

            if ($lockedCar->status !== 'under_review') {
                throw ValidationException::withMessages([
                    'status' => 'Documents can only be approved while the car is under review.',
                ]);
            }

            $lockedCar->update([
                'documents_approved_at' => now(),
                'documents_approved_by' => Auth::id(),
            ]);

            $this->auditLogService->record('cars', 'car.documents_approved', $lockedCar, null, [
                'documents_approved_by' => Auth::id(),
            ]);

            return $lockedCar->refresh();
        });
    }

    public function approve(Car $car): Car
    {
        Gate::authorize('approve', $car);

        return DB::transaction(function () use ($car): Car {
            $lockedCar = Car::query()->lockForUpdate()->findOrFail($car->getKey());

            if ($lockedCar->status !== 'under_review') {
                throw ValidationException::withMessages([
                    'status' => 'Only cars under review can be approved.',
                ]);
            }

            if ($lockedCar->documents_approved_at === null) {
                throw ValidationException::withMessages([
                    'documents' => 'Documents must be approved before the car can be approved.',
                ]);
            }

            if (! $this->hasCompletedInspection($lockedCar)) {
                throw ValidationException::withMessages([
                    'inspection' => 'A completed inspection is required before the car can be approved.',
                ]);
            }

            $oldStatus = $lockedCar->status;
            $lockedCar->update([
                'status' => 'available',
                'approved_at' => now(),
                'approved_by' => Auth::id(),
                'rejection_reason' => null,
            ]);

            $this->auditLogService->record('cars', 'car.approved', $lockedCar, [
                'status' => $oldStatus,
            ], [
                'status' => 'available',
                'approved_by' => Auth::id(),
            ]);

            return $lockedCar->refresh();
        });
    }

    public function reject(Car $car, string $reason): Car
    {
        Gate::authorize('reject', $car);

        $validated = validator(['reason' => $reason], [
            'reason' => ['required', 'string', 'max:2000'],
        ])->validate();

        return DB::transaction(function () use ($car, $validated): Car {
            $lockedCar = Car::query()->lockForUpdate()->findOrFail($car->getKey());

            if ($lockedCar->status !== 'under_review') {
                throw ValidationException::withMessages([
                    'status' => 'Only cars under review can be rejected.',
                ]);
            }

            $lockedCar->update([
                'status' => 'draft',
                'rejection_reason' => $validated['reason'],
                'approved_at' => null,
                'approved_by' => null,
            ]);

            $this->auditLogService->record('cars', 'car.rejected', $lockedCar, [
                'status' => 'under_review',
            ], [
                'status' => 'draft',
                'rejection_reason' => $validated['reason'],
            ]);

            return $lockedCar->refresh();
        });
    }

    private function hasCompletedInspection(Car $car): bool
    {
        return Inspection::query()
            ->where('car_id', $car->getKey())
            ->where('status', 'completed')
            ->exists();
    }
}
