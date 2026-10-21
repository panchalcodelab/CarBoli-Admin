<?php

use App\Models\Car;
use App\Models\Customer;
use App\Models\Inspection;
use App\Models\User;
use App\Services\InspectionService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('panel.layouts.app')] class extends Component
{
    public ?int $inspectionId = null;

    public ?int $car_id = null;
    public ?int $customer_id = null;
    public ?int $assigned_staff_id = null;
    public string $location = '';
    public ?string $inspection_date = null;
    public ?string $start_time = null;
    public ?string $end_time = null;
    public string $status = 'scheduled';
    public string $notes = '';

    public function mount(?int $inspection = null): void
    {
        if ($inspection) {
            $record = Inspection::query()->findOrFail($inspection);
            Gate::authorize('update', $record);
            $this->inspectionId = $record->id;
            $this->car_id = $record->car_id;
            $this->customer_id = $record->customer_id;
            $this->assigned_staff_id = $record->assigned_staff_id;
            $this->location = (string) $record->location;
            $this->inspection_date = $record->inspection_date?->format('Y-m-d');
            $this->start_time = $record->start_time ? substr((string) $record->start_time, 0, 5) : null;
            $this->end_time = $record->end_time ? substr((string) $record->end_time, 0, 5) : null;
            $this->status = $record->status;
            $this->notes = (string) $record->notes;
        } else {
            Gate::authorize('create', Inspection::class);
        }
    }

    public function save(InspectionService $service): void
    {
        $data = [
            'car_id' => $this->car_id,
            'customer_id' => $this->customer_id,
            'assigned_staff_id' => $this->assigned_staff_id,
            'location' => $this->location ?: null,
            'inspection_date' => $this->inspection_date,
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'status' => $this->status,
            'notes' => $this->notes ?: null,
        ];

        try {
            $inspection = $this->inspectionId
                ? $service->update(Inspection::query()->findOrFail($this->inspectionId), $data)
                : $service->schedule($data);

            session()->flash('status', $this->inspectionId ? 'Inspection updated.' : 'Inspection scheduled.');
            $this->redirectRoute('panel.inspections.show', ['inspection' => $inspection->id], navigate: true);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $key => $messages) {
                $this->addError(str_replace('_', '_', $key), $messages[0]);
            }
        }
    }

    public function with(): array
    {
        return [
            'cars' => Car::query()->orderBy('stock_inventory_id')->get(['id', 'stock_inventory_id', 'make', 'model']),
            'customers' => Customer::query()->orderBy('full_name')->get(['id', 'full_name']),
            'staff' => User::query()->role('Inspection Staff')->where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'statuses' => Inspection::STATUSES,
        ];
    }
}; ?>

<div>
    @section('title', $inspectionId ? 'Edit inspection' : 'Schedule inspection')
    @section('breadcrumb', 'Workspace / Inspections / ' . ($inspectionId ? 'Edit' : 'Schedule'))

    <a href="{{ route('panel.inspections.index') }}" wire:navigate class="mb-2 inline-flex items-center gap-1 text-sm text-gray-500 hover:text-carboli-600">← Back to Inspections</a>
    <x-panel.page-header :title="$inspectionId ? 'Edit inspection' : 'Schedule inspection'" description="Assign a car, staff and time. Conflicts are checked automatically." />

    @php($label = 'block text-sm font-medium text-gray-700')
    @php($control = 'mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-carboli-500 focus:ring-carboli-500')

    <x-panel.card>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="{{ $label }}">Vehicle <span class="text-red-500">*</span></label>
                <select wire:model="car_id" class="{{ $control }}">
                    <option value="">Select vehicle</option>
                    @foreach ($cars as $c)<option value="{{ $c->id }}">{{ $c->stock_inventory_id }} — {{ trim($c->make.' '.$c->model) }}</option>@endforeach
                </select>
                @error('car_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="{{ $label }}">Customer</label>
                <select wire:model="customer_id" class="{{ $control }}">
                    <option value="">Not specified</option>
                    @foreach ($customers as $c)<option value="{{ $c->id }}">{{ $c->full_name }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="{{ $label }}">Assigned inspection staff</label>
                <select wire:model="assigned_staff_id" class="{{ $control }}">
                    <option value="">Unassigned</option>
                    @foreach ($staff as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach
                </select>
                @error('assigned_staff_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="{{ $label }}">Location</label>
                <input type="text" wire:model="location" class="{{ $control }}">
            </div>
            <div>
                <label class="{{ $label }}">Inspection date <span class="text-red-500">*</span></label>
                <input type="date" wire:model="inspection_date" class="{{ $control }}">
                @error('inspection_date') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="{{ $label }}">Status <span class="text-red-500">*</span></label>
                <select wire:model="status" class="{{ $control }}">
                    @foreach ($statuses as $s)<option value="{{ $s }}">{{ \Illuminate\Support\Str::of($s)->replace('_',' ')->title() }}</option>@endforeach
                </select>
                @error('status') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="{{ $label }}">Start time <span class="text-red-500">*</span></label>
                <input type="time" wire:model="start_time" class="{{ $control }}">
                @error('start_time') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="{{ $label }}">End time <span class="text-red-500">*</span></label>
                <input type="time" wire:model="end_time" class="{{ $control }}">
                @error('end_time') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div class="sm:col-span-2">
                <label class="{{ $label }}">Notes</label>
                <textarea wire:model="notes" rows="3" class="{{ $control }}"></textarea>
            </div>
        </div>

        <div class="mt-6 flex items-center justify-end gap-2 border-t border-gray-100 pt-4">
            <a href="{{ route('panel.inspections.index') }}" wire:navigate class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</a>
            <button wire:click="save" class="rounded-lg bg-carboli-500 px-4 py-2 text-sm font-semibold text-white hover:bg-carboli-600">{{ $inspectionId ? 'Save changes' : 'Schedule inspection' }}</button>
        </div>
    </x-panel.card>
</div>
