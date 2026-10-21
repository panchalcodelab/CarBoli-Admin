<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CustomerKyc;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CustomerKycDocumentController extends Controller
{
    public function __invoke(CustomerKyc $customerKyc, string $document): BinaryFileResponse
    {
        Gate::authorize('view', $customerKyc);

        $path = match ($document) {
            'identity' => $customerKyc->document_path,
            'selfie' => $customerKyc->selfie_path,
            'address-proof' => $customerKyc->address_proof,
            default => null,
        };

        $normalizedPath = str_replace('\\', '/', $path ?? '');

        abort_if(
            str_starts_with($normalizedPath, '/') || in_array('..', explode('/', $normalizedPath), true),
            404,
        );

        $disk = Storage::disk('local');
        abort_unless(filled($path) && $disk->exists($path), 404);

        $privateFilePath = realpath($disk->path($path));
        $privateRootPath = realpath($disk->path(''));

        abort_unless(
            $privateFilePath && $privateRootPath && str_starts_with($privateFilePath, $privateRootPath.DIRECTORY_SEPARATOR),
            404,
        );

        return response()->download($privateFilePath);
    }
}
