<?php

use App\Models\Car;
use App\Services\CarDeletionService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('panel.layouts.app')] class extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public string $status = '';

    public ?int $confirmingDeleteId = null;

    public string $confirmingDeleteName = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', Car::class);
    }

    public function updating($name): void
    {
        if (in_array($name, ['search', 'status'], true)) {
            $this->resetPage();
        }
    }

    public function confirmDelete(int $id): void
    {
        $car = Car::query()->findOrFail($id);
        $this->confirmingDeleteId = $id;
        $this->confirmingDeleteName = trim($car->make.' '.$car->model) ?: ('#'.$car->id);
    }

    public function cancelDelete(): void
    {
        $this->confirmingDeleteId = null;
        $this->confirmingDeleteName = '';
    }

    public function delete(int $id, CarDeletionService $service): void
    {
        $car = Car::query()->findOrFail($id);

        try {
            $service->delete($car);
            session()->flash('status', 'Car and all related records deleted.');
        } catch (ValidationException $e) {
            $this->addError('car', collect($e->errors())->flatten()->first());
        } finally {
            $this->confirmingDeleteId = null;
            $this->confirmingDeleteName = '';
        }
    }

    public function with(): array
    {
        $cars = Car::query()
            ->with(['location', 'seller', 'inspections' => fn ($q) => $q->latest('id')])
            ->withCount('documents')
            ->when($this->search !== '', function ($q) {
                $term = '%'.$this->search.'%';
                $q->where(fn ($sub) => $sub
                    ->where('make', 'like', $term)
                    ->orWhere('model', 'like', $term)
                    ->orWhere('registration_number', 'like', $term)
                    ->orWhere('stock_inventory_id', 'like', $term));
            })
            ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
            ->latest()
            ->paginate(10);

        return [
            'cars' => $cars,
            'statuses' => Car::STATUSES,
        ];
    }

    public function inspectionState(Car $car): string
    {
        $latest = $car->inspections->first();
        if ($latest === null) {
            return 'Not started';
        }

        return match ($latest->status) {
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
            'no_show' => 'No show',
            'requested' => 'Requested',
            default => 'In progress',
        };
    }

    public function statusColor(string $status): string
    {
        return match ($status) {
            'available', 'live_auction' => 'success',
            'under_review', 'auction_scheduled', 'inspection_scheduled' => 'warning',
            'sold' => 'info',
            'draft' => 'gray',
            default => 'gray',
        };
    }
}; ?>

<div>
    @section('title', 'Car Management')
    @section('breadcrumb', 'Workspace / Car Management')

    <x-panel.page-header overline="Inventory" title="Car Management"
        description="Manage vehicle records, verification and auction readiness.">
        <x-slot:actions>
            @can('create', App\Models\Car::class)
                <a href="{{ route('panel.cars.create') }}" wire:navigate
                   class="inline-flex items-center gap-2 rounded-lg bg-carboli-500 px-4 py-2 text-sm font-semibold text-white hover:bg-carboli-600">
                    <x-panel.icon name="plus" class="h-4 w-4" />
                    Add new car
                </a>
            @endcan
        </x-slot:actions>
    </x-panel.page-header>

    @error('car')
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $message }}</div>
    @enderror

    <x-panel.card heading="All vehicles" description="Search and filter your inventory" flush>
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 p-4">
            <div class="relative flex-1 min-w-[240px]">
                <input type="search" wire:model.live.debounce.300ms="search"
                       placeholder="Search make, model, registration..."
                       class="w-full rounded-lg border-gray-300 pl-9 text-sm focus:border-carboli-500 focus:ring-carboli-500">
                <svg class="absolute left-3 top-2.5 h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/></svg>
            </div>
            <select wire:model.live="status" class="rounded-lg border-gray-300 text-sm focus:border-carboli-500 focus:ring-carboli-500">
                <option value="">All statuses</option>
                @foreach ($statuses as $s)
                    <option value="{{ $s }}">{{ \Illuminate\Support\Str::of($s)->replace('_',' ')->title() }}</option>
                @endforeach
            </select>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-100 text-sm">
                <thead>
                    <tr class="text-left text-xs font-semibold uppercase tracking-wider text-gray-400">
                        <th class="px-4 py-3">Vehicle</th>
                        <th class="px-4 py-3">Registration</th>
                        <th class="px-4 py-3">Seller</th>
                        <th class="px-4 py-3">Documents</th>
                        <th class="px-4 py-3">Inspection</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($cars as $car)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3">
                                <p class="font-semibold text-gray-900">{{ trim($car->make.' '.$car->model) }}</p>
                                <p class="text-xs text-gray-500">{{ trim(collect([$car->manufacturing_year, $car->fuel_type ? \Illuminate\Support\Str::title($car->fuel_type) : null])->filter()->implode(' · ')) }}</p>
                            </td>
                            <td class="px-4 py-3 text-gray-700">{{ $car->registration_number ?: '—' }}</td>
                            <td class="px-4 py-3 text-gray-700">{{ $car->seller?->name ?? ($car->location?->name ?? 'Unassigned') }}</td>
                            <td class="px-4 py-3">
                                <x-panel.badge :color="($car->documents_count ?? 0) > 0 ? 'success' : 'gray'">
                                    {{ ($car->documents_count ?? 0) > 0 ? 'Uploaded' : 'Missing' }}
                                </x-panel.badge>
                            </td>
                            <td class="px-4 py-3 text-gray-700">{{ $this->inspectionState($car) }}</td>
                            <td class="px-4 py-3">
                                <x-panel.badge :color="$this->statusColor($car->status)">
                                    {{ \Illuminate\Support\Str::of($car->status)->replace('_',' ')->title() }}
                                </x-panel.badge>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="inline-flex items-center gap-2">
                                    <a href="{{ route('panel.cars.show', $car) }}" wire:navigate
                                       class="inline-flex items-center gap-1 rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50">
                                        View
                                    </a>
                                    @can('delete', $car)
                                        <button wire:click="confirmDelete({{ $car->id }})"
                                                class="rounded-lg border border-red-300 px-3 py-1.5 text-xs font-medium text-red-700 hover:bg-red-50">Delete</button>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-10 text-center text-sm text-gray-500">
                                No vehicles found. Try adjusting your search or filters.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-gray-100 p-4">
            {{ $cars->links() }}
        </div>
    </x-panel.card>

    <x-panel.confirm-modal
        :show="$confirmingDeleteId !== null"
        title="Delete car"
        confirm-label="Delete car"
        :confirm-action="'delete('.(int) $confirmingDeleteId.')'"
        cancel-action="cancelDelete"
    >
        Are you sure you want to permanently delete
        <span class="font-semibold text-gray-700">{{ $confirmingDeleteName }}</span>?
        This also removes all related records, documents, images and files, and cannot be undone.
    </x-panel.confirm-modal>
</div>
