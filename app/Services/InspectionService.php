<?php

namespace App\Services;

use App\Models\Car;
use App\Models\Inspection;
use App\Models\InspectionDocument;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class InspectionService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function schedule(array $data): Inspection
    {
        Gate::authorize('create', Inspection::class);
        $validated = $this->validateSchedule($data);

        return DB::transaction(function () use ($validated): Inspection {
            $this->lockSchedulingResources($validated);
            $this->ensureNoConflict($validated);

            return Inspection::query()->create($validated);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Inspection $inspection, array $data): Inspection
    {
        Gate::authorize('update', $inspection);
        $data['status'] ??= $inspection->status;
        $validated = $this->validateSchedule($data, $inspection);

        return DB::transaction(function () use ($inspection, $validated): Inspection {
            $lockedInspection = Inspection::query()->lockForUpdate()->findOrFail($inspection->getKey());
            $this->lockSchedulingResources($validated);
            $this->ensureNoConflict($validated, $lockedInspection);

            if ($validated['status'] !== $lockedInspection->status) {
                $this->assertStatusTransition($lockedInspection->status, $validated['status']);
            }

            $lockedInspection->update($validated);

            return $lockedInspection->refresh();
        });
    }

    public function updateStatus(Inspection $inspection, string $status, ?string $notes = null): Inspection
    {
        Gate::authorize('updateStatus', $inspection);

        $validated = Validator::make([
            'status' => $status,
            'notes' => $notes,
        ], [
            'status' => ['required', Rule::in(Inspection::STATUSES)],
            'notes' => ['nullable', 'string', 'max:5000'],
        ])->validate();

        if (! Gate::allows('manage inspections') && ! in_array($status, [
            'confirmed',
            'in_progress',
            'completed',
            'no_show',
        ], true)) {
            throw ValidationException::withMessages([
                'status' => 'Inspection staff cannot set this status.',
            ]);
        }

        return DB::transaction(function () use ($inspection, $validated): Inspection {
            $lockedInspection = Inspection::query()->lockForUpdate()->findOrFail($inspection->getKey());
            $this->assertStatusTransition($lockedInspection->status, $validated['status']);
            $lockedInspection->update([
                'status' => $validated['status'],
                'notes' => $validated['notes'] ?? $lockedInspection->notes,
            ]);

            return $lockedInspection->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function addDocument(Inspection $inspection, array $data): InspectionDocument
    {
        Gate::authorize('uploadDocuments', $inspection);

        $validated = Validator::make($data, [
            'path' => ['required', 'string', 'max:255'],
            'file_name' => ['nullable', 'string', 'max:255'],
            'type' => ['required', Rule::in(['report', 'photo', 'supporting_document'])],
        ])->validate();

        if (! str_starts_with($validated['path'], 'inspections/documents/')
            || ! Storage::disk('local')->exists($validated['path'])) {
            throw ValidationException::withMessages([
                'path' => 'Upload a valid inspection document.',
            ]);
        }

        return DB::transaction(fn (): InspectionDocument => $inspection->documents()->create($validated));
    }

    public function deleteDocument(InspectionDocument $document): void
    {
        Gate::authorize('delete', $document);

        $path = DB::transaction(function () use ($document): string {
            $lockedDocument = InspectionDocument::query()
                ->lockForUpdate()
                ->findOrFail($document->getKey());

            $lockedDocument->delete();

            return $lockedDocument->path;
        });

        Storage::disk('local')->delete($path);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function validateSchedule(array $data, ?Inspection $inspection = null): array
    {
        $validated = Validator::make($data, [
            'car_id' => ['required', 'integer', Rule::exists('cars', 'id')->whereNull('deleted_at')],
            'customer_id' => ['nullable', 'integer', Rule::exists('customers', 'id')->whereNull('deleted_at')],
            'assigned_staff_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('status', 'active')],
            'inspection_date' => ['required', 'date'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'location' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(Inspection::STATUSES)],
            'notes' => ['nullable', 'string', 'max:5000'],
        ])->validate();

        if ($validated['start_time'] >= $validated['end_time']) {
            throw ValidationException::withMessages([
                'end_time' => 'The inspection end time must be after its start time.',
            ]);
        }

        if ($validated['assigned_staff_id'] !== null) {
            $isActiveInspector = User::query()
                ->role('Inspection Staff')
                ->where('status', 'active')
                ->whereKey($validated['assigned_staff_id'])
                ->exists();

            if (! $isActiveInspector) {
                throw ValidationException::withMessages([
                    'assigned_staff_id' => 'Select an active user assigned to the Inspection Staff role.',
                ]);
            }
        }

        if ($inspection) {
            $validated['notes'] ??= $inspection->notes;
        }

        return $validated;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function lockSchedulingResources(array $data): void
    {
        Car::query()->lockForUpdate()->findOrFail($data['car_id']);

        if ($data['assigned_staff_id'] !== null) {
            User::query()
                ->lockForUpdate()
                ->role('Inspection Staff')
                ->where('status', 'active')
                ->findOrFail($data['assigned_staff_id']);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function ensureNoConflict(array $data, ?Inspection $inspection = null): void
    {
        $conflicts = Inspection::query()
            ->whereDate('inspection_date', $data['inspection_date'])
            ->whereNotIn('status', ['completed', 'cancelled', 'no_show'])
            ->where('start_time', '<', $data['end_time'])
            ->where('end_time', '>', $data['start_time'])
            ->where(function ($query) use ($data): void {
                $query->where('car_id', $data['car_id']);

                if ($data['assigned_staff_id'] !== null) {
                    $query->orWhere('assigned_staff_id', $data['assigned_staff_id']);
                }
            });

        if ($inspection) {
            $conflicts->where('id', '<>', $inspection->getKey());
        }

        if ($conflicts->lockForUpdate()->exists()) {
            throw ValidationException::withMessages([
                'inspection_date' => 'The car or assigned staff already has an overlapping inspection.',
            ]);
        }
    }

    private function assertStatusTransition(string $currentStatus, string $newStatus): void
    {
        $transitions = [
            'requested' => ['scheduled', 'cancelled'],
            'scheduled' => ['assigned', 'confirmed', 'cancelled'],
            'assigned' => ['confirmed', 'in_progress', 'cancelled', 'no_show'],
            'confirmed' => ['in_progress', 'cancelled', 'no_show'],
            'in_progress' => ['completed', 'cancelled', 'no_show'],
            'completed' => [],
            'cancelled' => [],
            'no_show' => [],
        ];

        if ($currentStatus !== $newStatus && ! in_array($newStatus, $transitions[$currentStatus] ?? [], true)) {
            throw ValidationException::withMessages([
                'status' => 'This inspection status transition is not allowed.',
            ]);
        }
    }
}
