<?php

use App\Models\Location;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('panel.layouts.app')] class extends Component
{
    public ?int $locationId = null;

    public string $name = '';
    public string $address = '';
    public string $city = '';
    public string $state = '';
    public string $pincode = '';
    public ?string $latitude = null;
    public ?string $longitude = null;

    public function mount(?int $location = null): void
    {
        if ($location) {
            $record = Location::query()->findOrFail($location);
            Gate::authorize('update', $record);
            $this->locationId = $record->id;
            $this->name = (string) $record->name;
            $this->address = (string) $record->address;
            $this->city = (string) $record->city;
            $this->state = (string) $record->state;
            $this->pincode = (string) $record->pincode;
            $this->latitude = $record->latitude !== null ? (string) $record->latitude : null;
            $this->longitude = $record->longitude !== null ? (string) $record->longitude : null;
        } else {
            Gate::authorize('create', Location::class);
        }
    }

    public function save(): void
    {
        // Authorize again server-side regardless of UI state.
        if ($this->locationId) {
            Gate::authorize('update', Location::query()->findOrFail($this->locationId));
        } else {
            Gate::authorize('create', Location::class);
        }

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
            'pincode' => ['nullable', 'string', 'max:20'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        $location = Location::query()->updateOrCreate(
            ['id' => $this->locationId],
            $validated,
        );

        session()->flash('status', $this->locationId ? 'Location updated.' : 'Location created.');
        $this->redirectRoute('panel.locations.index', navigate: true);
    }
}; ?>

<div>
    @section('title', $locationId ? 'Edit location' : 'Add location')
    @section('breadcrumb', 'Workspace / Locations / ' . ($locationId ? 'Edit' : 'Add'))

    <a href="{{ route('panel.locations.index') }}" wire:navigate class="mb-2 inline-flex items-center gap-1 text-sm text-gray-500 hover:text-carboli-600">← Back to Locations</a>
    <x-panel.page-header :title="$locationId ? 'Edit location' : 'Add location'" description="Yard or pickup location details." />

    @php($label = 'block text-sm font-medium text-gray-700')
    @php($control = 'mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-carboli-500 focus:ring-carboli-500')

    <x-panel.card>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="{{ $label }}">Name <span class="text-red-500">*</span></label>
                <input type="text" wire:model="name" class="{{ $control }}">
                @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="{{ $label }}">Pincode</label>
                <input type="text" wire:model="pincode" class="{{ $control }}">
                @error('pincode') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div class="sm:col-span-2">
                <label class="{{ $label }}">Address</label>
                <textarea wire:model="address" rows="2" class="{{ $control }}"></textarea>
            </div>
            <div>
                <label class="{{ $label }}">City</label>
                <input type="text" wire:model="city" class="{{ $control }}">
            </div>
            <div>
                <label class="{{ $label }}">State</label>
                <input type="text" wire:model="state" class="{{ $control }}">
            </div>
            <div>
                <label class="{{ $label }}">Latitude</label>
                <input type="number" step="any" wire:model="latitude" class="{{ $control }}">
                @error('latitude') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="{{ $label }}">Longitude</label>
                <input type="number" step="any" wire:model="longitude" class="{{ $control }}">
                @error('longitude') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="mt-6 flex items-center justify-end gap-2 border-t border-gray-100 pt-4">
            <a href="{{ route('panel.locations.index') }}" wire:navigate class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</a>
            <button wire:click="save" class="rounded-lg bg-carboli-500 px-4 py-2 text-sm font-semibold text-white hover:bg-carboli-600">{{ $locationId ? 'Save changes' : 'Create location' }}</button>
        </div>
    </x-panel.card>
</div>
