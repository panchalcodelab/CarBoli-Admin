<?php

use App\Models\Inspection;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
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

    #[Url]
    public string $status = '';

    #[Url]
    public string $assigned_staff_id = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', Inspection::class);
    }

    public function updating($name): void
    {
        if (in_array($name, ['search', 'status', 'assigned_staff_id'], true)) {
            $this->resetPage();
        }
    }

    public function with(): array
    {
        $user = Auth::user();

        $query = Inspection::query()->with(['car', 'customer', 'assignedStaff']);

        // Mirror the Filament scope: assigned-only staff see only their inspections.
        if ($user && $user->can('update assigned inspections') && ! $user->can('manage inspections')) {
            $query->where('assigned_staff_id', $user->getKey());
        }

        $records = $query
            ->when($this->search !== '', function ($q) {
                $term = '%'.$this->search.'%';
                $q->whereHas('car', fn ($c) => $c->where('stock_inventory_id', 'like', $term)->orWhere('make', 'like', $term)->orWhere('model', 'like', $term))
                    ->orWhereHas('customer', fn ($c) => $c->where('full_name', 'like', $term));
            })
            ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
            ->when($this->assigned_staff_id !== '', fn ($q) => $q->where('assigned_staff_id', $this->assigned_staff_id))
            ->latest('inspection_date')
            ->paginate(10);

        return [
            'records' => $records,
            'statuses' => Inspection::STATUSES,
            'staff' => User::query()->role('Inspection Staff')->orderBy('name')->get(['id', 'name']),
            'canManage' => Gate::allows('create', Inspection::class),
        ];
    }

    public function statusColor(string $status): string
    {
        return match ($status) {
            'completed' => 'success',
            'cancelled', 'no_show' => 'danger',
            'in_progress', 'confirmed', 'assigned' => 'warning',
            'scheduled' => 'info',
            default => 'gray',
        };
    }
}; ?>

<div>
    @section('title', 'Inspections')
    @section('breadcrumb', 'Workspace / Inspections')

    <x-panel.page-header overline="Operations" title="Inspections" description="Schedule, assign and track vehicle inspections.">
        <x-slot:actions>
            @can('create', App\Models\Inspection::class)
                <a href="{{ route('panel.inspections.create') }}" wire:navigate class="inline-flex items-center gap-2 rounded-lg bg-carboli-500 px-4 py-2 text-sm font-semibold text-white hover:bg-carboli-600"><x-panel.icon name="plus" class="h-4 w-4" />Schedule inspection</a>
            @endcan
        </x-slot:actions>
    </x-panel.page-header>

    <x-panel.card heading="All inspections" description="Search and filter inspections" flush>
        <div class="flex flex-wrap items-center gap-3 border-b border-gray-100 p-4">
            <div class="relative flex-1 min-w-[220px]">
                <input type="search" wire:model.live.debounce.300ms="search" placeholder="Search vehicle or customer..."
                       class="w-full rounded-lg border-gray-300 pl-9 text-sm focus:border-carboli-500 focus:ring-carboli-500">
                <svg class="absolute left-3 top-2.5 h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/></svg>
            </div>
            <select wire:model.live="status" class="rounded-lg border-gray-300 text-sm">
                <option value="">All statuses</option>
                @foreach ($statuses as $s)<option value="{{ $s }}">{{ \Illuminate\Support\Str::of($s)->replace('_',' ')->title() }}</option>@endforeach
            </select>
            @if ($canManage)
                <select wire:model.live="assigned_staff_id" class="rounded-lg border-gray-300 text-sm">
                    <option value="">All staff</option>
                    @foreach ($staff as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach
                </select>
            @endif
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-100 text-sm">
                <thead>
                    <tr class="text-left text-xs font-semibold uppercase tracking-wider text-gray-400">
                        <th class="px-4 py-3">Vehicle</th>
                        <th class="px-4 py-3">Customer</th>
                        <th class="px-4 py-3">Assigned staff</th>
                        <th class="px-4 py-3">Date</th>
                        <th class="px-4 py-3">Time</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($records as $inspection)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-gray-900">{{ $inspection->car?->stock_inventory_id ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-700">{{ $inspection->customer?->full_name ?? 'Not specified' }}</td>
                            <td class="px-4 py-3 text-gray-700">{{ $inspection->assignedStaff?->name ?? 'Unassigned' }}</td>
                            <td class="px-4 py-3 text-gray-700">{{ $inspection->inspection_date?->format('d M Y') ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-700">{{ $inspection->start_time }}</td>
                            <td class="px-4 py-3"><x-panel.badge :color="$this->statusColor($inspection->status)">{{ \Illuminate\Support\Str::of($inspection->status)->replace('_',' ')->title() }}</x-panel.badge></td>
                            <td class="px-4 py-3 text-right"><a href="{{ route('panel.inspections.show', $inspection) }}" wire:navigate class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50">View</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-10 text-center text-sm text-gray-500">No inspections found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-gray-100 p-4">{{ $records->links() }}</div>
    </x-panel.card>
</div>
