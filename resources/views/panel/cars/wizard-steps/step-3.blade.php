@php
    $docTypes = [
        'rc' => 'Registration certificate (RC)',
        'insurance' => 'Insurance',
        'service_record' => 'Service records',
        'other' => 'Other supporting document',
    ];
@endphp

<div class="rounded-lg bg-carboli-50 px-4 py-3 text-sm text-carboli-800">
    Upload RC, insurance and supporting files. Accepted: PDF, JPG, PNG. Files are stored privately.
</div>

<div class="mt-4 grid gap-4 sm:grid-cols-2">
    @foreach ($docTypes as $type => $label)
        <div class="rounded-xl border border-gray-200 p-4">
            <p class="text-sm font-semibold text-gray-900">{{ $label }}</p>
            <input type="file" wire:model="documentUploads.{{ $type }}" accept=".pdf,.jpg,.jpeg,.png" class="mt-2 block w-full text-sm text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-carboli-50 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-carboli-700">
            <div wire:loading wire:target="documentUploads.{{ $type }}" class="mt-1 text-xs text-gray-400">Uploading…</div>
            @error('documentUploads.'.$type) <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            @if (!empty($documentUploads[$type]))
                <p class="mt-1 text-xs text-green-600">Selected: {{ $documentUploads[$type]->getClientOriginalName() }}</p>
            @endif
        </div>
    @endforeach
</div>
