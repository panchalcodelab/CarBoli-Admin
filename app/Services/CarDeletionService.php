<?php

namespace App\Services;

use App\Models\Car;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Permanently deletes a car together with every related record and stored
 * file. Database children (images, documents, seller, inspections and their
 * documents, auctions and their participants/bids/results) are removed by the
 * schema's cascade-on-delete rules when the car is force-deleted; this service
 * additionally removes the physical files those records point to, which the
 * database cascade does not touch.
 *
 * Because the operation is irreversible and high-impact, it is blocked when the
 * car is tied to an active auction or a captured payment.
 */
class CarDeletionService
{
    private const PRIVATE_DISK = 'local';

    public function delete(Car $car): void
    {
        Gate::authorize('delete', $car);

        $this->guardAgainstActiveAuction($car);
        $this->guardAgainstCapturedPayments($car);

        $paths = $this->collectFilePaths($car);
        $snapshot = $this->snapshot($car);

        DB::transaction(function () use ($car): void {
            // forceDelete is required: the model uses SoftDeletes, and only a
            // hard delete triggers the database's cascade-on-delete rules.
            $car->forceDelete();
        });

        // Remove files only after the database transaction has committed, so a
        // rollback never leaves the record pointing at a missing file.
        $this->deleteFiles($paths);

        app(AuditLogService::class)->record(
            'cars',
            'car.deleted',
            null,
            $snapshot,
            null,
        );
    }

    private function guardAgainstActiveAuction(Car $car): void
    {
        $hasActiveAuction = $car->auctions()
            ->whereIn('status', ['scheduled', 'live'])
            ->exists();

        if ($hasActiveAuction) {
            throw ValidationException::withMessages([
                'car' => 'This car is linked to a scheduled or live auction and cannot be deleted.',
            ]);
        }
    }

    private function guardAgainstCapturedPayments(Car $car): void
    {
        $hasCapturedPayment = Payment::query()
            ->whereIn('auction_id', $car->auctions()->pluck('id'))
            ->where('status', 'captured')
            ->exists();

        if ($hasCapturedPayment) {
            throw ValidationException::withMessages([
                'car' => 'This car has captured payments against its auctions and cannot be deleted.',
            ]);
        }
    }

    /**
     * Gather every stored file path owned by the car and its inspections.
     *
     * @return array<int, string>
     */
    private function collectFilePaths(Car $car): array
    {
        $paths = [];

        foreach ($car->images as $image) {
            $paths[] = $image->path;
        }

        foreach ($car->documents as $document) {
            $paths[] = $document->path;
        }

        if ($car->walkaround_video_path) {
            $paths[] = $car->walkaround_video_path;
        }

        if ($car->primary_image_path) {
            $paths[] = $car->primary_image_path;
        }

        foreach ($car->inspections()->with('documents')->get() as $inspection) {
            if ($inspection->inspection_report_path) {
                $paths[] = $inspection->inspection_report_path;
            }

            foreach ($inspection->documents as $document) {
                $paths[] = $document->path;
            }
        }

        return array_values(array_unique(array_filter($paths)));
    }

    /**
     * @param  array<int, string>  $paths
     */
    private function deleteFiles(array $paths): void
    {
        if ($paths === []) {
            return;
        }

        Storage::disk(self::PRIVATE_DISK)->delete($paths);
    }

    /**
     * Build an audit snapshot of the car and the counts of what was removed.
     *
     * @return array<string, mixed>
     */
    private function snapshot(Car $car): array
    {
        return [
            'id' => $car->getKey(),
            'stock_inventory_id' => $car->stock_inventory_id,
            'make' => $car->make,
            'model' => $car->model,
            'registration_number' => $car->registration_number,
            'status' => $car->status,
            'images_count' => $car->images()->count(),
            'documents_count' => $car->documents()->count(),
            'inspections_count' => $car->inspections()->count(),
            'auctions_count' => $car->auctions()->count(),
        ];
    }
}
