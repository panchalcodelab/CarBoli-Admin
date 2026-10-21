<?php

use App\Models\CustomerKyc;
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

    public function mount(): void
    {
        Gate::authorize('viewAny', CustomerKyc::class);
    }

    public function updating($name): void
    {
        if (in_array($name, ['search', 'status'], true)) {
            $this->resetPage();
        }
    }

    public function with(): array
    {
        $records = CustomerKyc::query()
            ->with(['customer', 'reviewer'])
            ->when($this->search !== '', function ($q) {
                $term = '%'.$this->search.'%';
                $q->whereHas('customer', fn ($sub) => $sub
                    ->where('full_name', 'like', $term)
                    ->orWhere('email', 'like', $term));
            })
            ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
            ->latest()
            ->paginate(10);

        return [
            'records' => $records,
            'statuses' => ['pending', 'under_review', 'approved', 'rejected'],
            'pendingCount' => CustomerKyc::query()->whereIn('status', ['pending', 'under_review'])->count(),
        ];
    }

    public function statusColor(string $status): string
    {
        return match ($status) {
            'approved' => 'success',
            'rejected' => 'danger',
            'under_review' => 'warning',
            'pending' => 'gray',
            default => 'gray',
        };
    }
}; ?>

<div>
    @section('title', 'KYC Verification')
    @section('breadcrumb', 'Workspace / KYC Verification')

    <x-panel.page-header overline="Compliance" title="KYC Verification"
        description="Review customer identity documents and verification status.">
        <x-slot:actions>
            @if ($pendingCount > 0)
                <x-panel.badge color="warning">{{ $pendingCount }} pending review</x-panel.badge>
            @endif
        </x-slot:actions>
    </x-panel.page-header>

    <x-panel.card heading="KYC records" description="Search and filter identity submissions" flush>
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 p-4">
            <div class="relative flex-1 min-w-[240px]">
                <input type="search" wire:model.live.debounce.300ms="search"
                       placeholder="Search customer name or email..."
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
                        <th class="px-4 py-3">Customer</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Reviewer</th>
                        <th class="px-4 py-3">Reviewed</th>
                        <th class="px-4 py-3">Submitted</th>
                        <th class="px-4 py-3 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($records as $kyc)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3">
                                <p class="font-semibold text-gray-900">{{ $kyc->customer?->full_name ?? '—' }}</p>
                                <p class="text-xs text-gray-500">{{ $kyc->customer?->email }}</p>
                            </td>
                            <td class="px-4 py-3"><x-panel.badge :color="$this->statusColor($kyc->status)">{{ \Illuminate\Support\Str::of($kyc->status)->replace('_',' ')->title() }}</x-panel.badge></td>
                            <td class="px-4 py-3 text-gray-700">{{ $kyc->reviewer?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-700">{{ $kyc->reviewed_at?->format('d M Y') ?? 'Not reviewed' }}</td>
                            <td class="px-4 py-3 text-gray-700">{{ $kyc->created_at?->format('d M Y') }}</td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('panel.kyc.show', $kyc) }}" wire:navigate class="inline-flex items-center gap-1 rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50">Review</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-10 text-center text-sm text-gray-500">No KYC records found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-gray-100 p-4">{{ $records->links() }}</div>
    </x-panel.card>
</div>
