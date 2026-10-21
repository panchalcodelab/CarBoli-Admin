<?php

use App\Models\Location;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('panel.layouts.app')] class extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    public ?int $confirmingDeleteId = null;

    public string $confirmingDeleteName = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', Location::class);
    }

    public function updating($name): void
    {
        if ($name === 'search') {
            $this->resetPage();
        }
    }

    public function confirmDelete(int $id): void
    {
        $location = Location::query()->findOrFail($id);
        $this->confirmingDeleteId = $id;
        $this->confirmingDeleteName = (string) $location->name;
    }

    public function cancelDelete(): void
    {
        $this->confirmingDeleteId = null;
        $this->confirmingDeleteName = '';
    }

    public function delete(int $id): void
    {
        $location = Location::query()->withCount('cars')->findOrFail($id);
        Gate::authorize('delete', $location);

        try {
            if ($location->cars_count > 0) {
                $this->addError('location', "This location has {$location->cars_count} ".($location->cars_count === 1 ? 'car' : 'cars').' assigned and cannot be deleted. Reassign them first.');

                return;
            }

            $snapshot = [
                'id' => $location->getKey(),
                'name' => $location->name,
                'city' => $location->city,
                'state' => $location->state,
                'pincode' => $location->pincode,
            ];

            DB::transaction(function () use ($location, $snapshot): void {
                $location->delete();

                app(AuditLogService::class)->record('cars', 'location.deleted', null, $snapshot, null);
            });

            session()->flash('status', "Location \"{$location->name}\" deleted.");
        } finally {
            $this->confirmingDeleteId = null;
            $this->confirmingDeleteName = '';
        }
    }

    public function with(): array
    {
        $locations = Location::query()
            ->withCount('cars')
            ->when($this->search !== '', function ($q) {
                $term = '%'.$this->search.'%';
                $q->where('name', 'like', $term)->orWhere('city', 'like', $term)->orWhere('state', 'like', $term)->orWhere('pincode', 'like', $term);
            })
            ->orderBy('name')
            ->paginate(10);

        return [
            'locations' => $locations,
            'canManage' => Gate::allows('create', Location::class),
        ];
    }
}; ?>

<div>
    @section('title', 'Locations')
    @section('breadcrumb', 'Workspace / Locations')

    <x-panel.page-header overline="Inventory" title="Locations" description="Yard and pickup locations used across inventory.">
        <x-slot:actions>
            @can('create', App\Models\Location::class)
                <a href="{{ route('panel.locations.create') }}" wire:navigate class="inline-flex items-center gap-2 rounded-lg bg-carboli-500 px-4 py-2 text-sm font-semibold text-white hover:bg-carboli-600"><x-panel.icon name="plus" class="h-4 w-4" />Add location</a>
            @endcan
        </x-slot:actions>
    </x-panel.page-header>

    @if (session('status'))
        <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('status') }}</div>
    @endif
    @error('location')
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $message }}</div>
    @enderror

    <x-panel.card heading="All locations" description="Search yard and pickup locations" flush>
        <div class="border-b border-gray-100 p-4">
            <div class="relative max-w-sm">
                <input type="search" wire:model.live.debounce.300ms="search" placeholder="Search name, city, state, pincode..."
                       class="w-full rounded-lg border-gray-300 pl-9 text-sm focus:border-carboli-500 focus:ring-carboli-500">
                <svg class="absolute left-3 top-2.5 h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/></svg>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-100 text-sm">
                <thead>
                    <tr class="text-left text-xs font-semibold uppercase tracking-wider text-gray-400">
                        <th class="px-4 py-3">Name</th>
                        <th class="px-4 py-3">City</th>
                        <th class="px-4 py-3">State</th>
                        <th class="px-4 py-3">Pincode</th>
                        <th class="px-4 py-3">Cars</th>
                        <th class="px-4 py-3 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($locations as $location)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 font-semibold text-gray-900">{{ $location->name }}</td>
                            <td class="px-4 py-3 text-gray-700">{{ $location->city ?: '—' }}</td>
                            <td class="px-4 py-3 text-gray-700">{{ $location->state ?: '—' }}</td>
                            <td class="px-4 py-3 text-gray-700">{{ $location->pincode ?: '—' }}</td>
                            <td class="px-4 py-3 text-gray-700">{{ $location->cars_count }}</td>
                            <td class="px-4 py-3 text-right">
                                <div class="inline-flex items-center gap-2">
                                    @if ($canManage)
                                        <a href="{{ route('panel.locations.edit', $location) }}" wire:navigate class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50">Edit</a>
                                    @endif
                                    @can('delete', $location)
                                        <button wire:click="confirmDelete({{ $location->id }})" class="rounded-lg border border-red-300 px-3 py-1.5 text-xs font-medium text-red-700 hover:bg-red-50">Delete</button>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-10 text-center text-sm text-gray-500">No locations found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-gray-100 p-4">{{ $locations->links() }}</div>
    </x-panel.card>

    <x-panel.confirm-modal
        :show="$confirmingDeleteId !== null"
        title="Delete location"
        confirm-label="Delete location"
        busy-label="Deleting…"
        :confirm-action="'delete('.(int) $confirmingDeleteId.')'"
        cancel-action="cancelDelete"
    >
        Are you sure you want to delete the location
        <span class="font-semibold text-gray-700">{{ $confirmingDeleteName }}</span>?
        This cannot be undone.
    </x-panel.confirm-modal>
</div>
