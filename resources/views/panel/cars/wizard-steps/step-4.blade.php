<div class="rounded-lg bg-carboli-50 px-4 py-3 text-sm text-carboli-800">
    Upload clear, recent images. Keep the exterior, interior and odometer views well lit. Max 2 MB per image.
</div>

<div class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
    @foreach ($imageSlots as $slot => [$label, $hint])
        <div class="rounded-xl border border-gray-200 p-4">
            <div class="flex items-center justify-between">
                <p class="text-sm font-semibold text-gray-900">{{ $label }}</p>
                <span @class([
                    'text-xs',
                    'text-red-500' => $hint === 'Required',
                    'text-gray-400' => $hint !== 'Required',
                ])>{{ $hint }}</span>
            </div>
            <input type="file" wire:model="imageUploads.{{ $slot }}" accept=".jpg,.jpeg,.png,.webp" class="mt-2 block w-full text-sm text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-carboli-50 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-carboli-700">
            <div wire:loading wire:target="imageUploads.{{ $slot }}" class="mt-1 text-xs text-gray-400">Uploading…</div>
            @error('imageUploads.'.$slot) <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            @if (!empty($imageUploads[$slot]) && method_exists($imageUploads[$slot], 'temporaryUrl'))
                @php($isImage = str_starts_with((string) $imageUploads[$slot]->getMimeType(), 'image/'))
                @if ($isImage)
                    <img src="{{ $imageUploads[$slot]->temporaryUrl() }}" class="mt-2 h-24 w-full rounded-lg object-cover" alt="{{ $label }} preview">
                @endif
            @endif
        </div>
    @endforeach
</div>

<div class="mt-4 rounded-xl border border-gray-200 p-4">
    <div class="flex items-center justify-between">
        <p class="text-sm font-semibold text-gray-900">Walkaround video</p>
        <span class="text-xs text-gray-400">Optional · up to 15 MB</span>
    </div>
    <input type="file" wire:model="walkaroundVideo" accept=".mp4,.webm,.mov" class="mt-2 block w-full text-sm text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-carboli-50 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-carboli-700">
    <div wire:loading wire:target="walkaroundVideo" class="mt-1 text-xs text-gray-400">Uploading…</div>
    @error('walkaroundVideo') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
    <p class="mt-1 text-xs text-gray-400">Server currently accepts up to {{ number_format($this->maxVideoKb() / 1024, 0) }} MB (configured PHP limit).</p>
</div>
