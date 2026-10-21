<?php

namespace App\Services;

use App\Models\Car;
use App\Models\CarDocument;
use App\Models\CarImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class CarMediaService
{
    /** Allowed image mime extensions. */
    public const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

    /** Allowed document extensions. */
    public const DOCUMENT_EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png'];

    /** Allowed video extensions. */
    public const VIDEO_EXTENSIONS = ['mp4', 'webm', 'mov'];

    /**
     * Store an image for a car in a named slot on the private disk.
     * Uploads are size-validated against the server's configured PHP limit
     * (Livewire already enforces temporary-upload limits); the controlled
     * product target for images is 2 MB each.
     */
    public function storeImage(Car $car, UploadedFile $file, ?string $slot = null): CarImage
    {
        Gate::authorize('update', $car);

        $this->assertExtension($file, self::IMAGE_EXTENSIONS, 'image');

        return DB::transaction(function () use ($car, $file, $slot): CarImage {
            $lockedCar = Car::query()->lockForUpdate()->findOrFail($car->getKey());

            $path = $file->store('cars/images', 'local');

            // Replace an existing image occupying the same named slot.
            if ($slot !== null && $slot !== 'additional') {
                $existing = $lockedCar->images()->where('slot', $slot)->get();
                foreach ($existing as $old) {
                    $oldPath = $old->path;
                    $old->delete();
                    Storage::disk('local')->delete($oldPath);
                }
            }

            $isPrimary = $slot === 'front' || $lockedCar->images()->count() === 0;

            $image = $lockedCar->images()->create([
                'slot' => $slot,
                'path' => $path,
                'is_primary' => $isPrimary,
                'sort_order' => (int) ($lockedCar->images()->max('sort_order') ?? 0) + 1,
            ]);

            if ($isPrimary) {
                $lockedCar->images()->where('id', '<>', $image->getKey())->update(['is_primary' => false]);
                $lockedCar->update(['primary_image_path' => $path]);
            }

            return $image->refresh();
        });
    }

    /**
     * Store a typed document for a car on the private disk.
     */
    public function storeDocument(Car $car, UploadedFile $file, string $type): CarDocument
    {
        Gate::authorize('update', $car);

        $this->assertExtension($file, self::DOCUMENT_EXTENSIONS, 'document');

        return DB::transaction(function () use ($car, $file, $type): CarDocument {
            $lockedCar = Car::query()->lockForUpdate()->findOrFail($car->getKey());
            $path = $file->store('cars/documents', 'local');

            return $lockedCar->documents()->create([
                'type' => $type,
                'path' => $path,
                'file_name' => $file->getClientOriginalName(),
            ]);
        });
    }

    /**
     * Store (or replace) the walkaround video for a car on the private disk.
     * The product target is 15 MB; actual acceptance is bounded by the server's
     * configured upload limits and validated by the caller/Livewire rules.
     */
    public function storeWalkaroundVideo(Car $car, UploadedFile $file): Car
    {
        Gate::authorize('update', $car);

        $this->assertExtension($file, self::VIDEO_EXTENSIONS, 'video');

        return DB::transaction(function () use ($car, $file): Car {
            $lockedCar = Car::query()->lockForUpdate()->findOrFail($car->getKey());
            $previous = $lockedCar->walkaround_video_path;

            $path = $file->store('cars/videos', 'local');
            $lockedCar->update(['walkaround_video_path' => $path]);

            if ($previous && $previous !== $path) {
                Storage::disk('local')->delete($previous);
            }

            return $lockedCar->refresh();
        });
    }

    private function assertExtension(UploadedFile $file, array $allowed, string $label): void
    {
        $ext = strtolower($file->getClientOriginalExtension());
        if (! in_array($ext, $allowed, true)) {
            throw ValidationException::withMessages([
                'file' => "The {$label} must be one of: ".implode(', ', $allowed).'.',
            ]);
        }
    }

    public function setPrimaryImage(CarImage $image): CarImage
    {
        $car = $image->car;
        Gate::authorize('update', $car);

        if (! Storage::disk('local')->exists($image->path)) {
            throw ValidationException::withMessages([
                'path' => 'The uploaded image could not be found in private storage.',
            ]);
        }

        return DB::transaction(function () use ($image, $car): CarImage {
            $lockedCar = Car::query()->lockForUpdate()->findOrFail($car->getKey());
            $lockedImage = $lockedCar->images()
                ->lockForUpdate()
                ->findOrFail($image->getKey());

            $lockedCar->images()
                ->where('id', '<>', $lockedImage->getKey())
                ->update(['is_primary' => false]);

            $lockedImage->update(['is_primary' => true]);
            $lockedCar->update(['primary_image_path' => $lockedImage->path]);

            return $lockedImage->refresh();
        });
    }

    public function deleteImage(CarImage $image): void
    {
        $car = $image->car;
        Gate::authorize('update', $car);

        $path = DB::transaction(function () use ($image, $car): string {
            $lockedCar = Car::query()->lockForUpdate()->findOrFail($car->getKey());
            $lockedImage = $lockedCar->images()
                ->lockForUpdate()
                ->findOrFail($image->getKey());
            $wasPrimary = $lockedImage->is_primary || $lockedCar->primary_image_path === $lockedImage->path;

            $lockedImage->delete();

            if ($wasPrimary) {
                $nextPrimaryImage = $lockedCar->images()
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->first();

                if ($nextPrimaryImage) {
                    $nextPrimaryImage->update(['is_primary' => true]);
                }

                $lockedCar->update(['primary_image_path' => $nextPrimaryImage?->path]);
            }

            return $lockedImage->path;
        });

        Storage::disk('local')->delete($path);
    }

    public function deleteDocument(CarDocument $document): void
    {
        Gate::authorize('update', $document->car);

        $path = DB::transaction(function () use ($document): string {
            $lockedDocument = CarDocument::query()
                ->lockForUpdate()
                ->findOrFail($document->getKey());

            $lockedDocument->delete();

            return $lockedDocument->path;
        });

        Storage::disk('local')->delete($path);
    }
}
