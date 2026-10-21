<?php

use App\Models\Auction;
use App\Models\Bid;
use App\Models\Customer;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('panel.layouts.app')] class extends Component
{
    use WithPagination;

    #[Url]
    public string $auction_id = '';

    #[Url]
    public string $status = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', Bid::class);
    }

    public function updating($name): void
    {
        if (in_array($name, ['auction_id', 'status'], true)) {
            $this->resetPage();
        }
    }

    public function with(): array
    {
        $bids = Bid::query()
            ->with(['auction', 'customer'])
            ->when($this->auction_id !== '', fn ($q) => $q->where('auction_id', $this->auction_id))
            ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
            ->latest()
            ->paginate(15);

        return [
            'bids' => $bids,
            'auctions' => Auction::query()->orderBy('title')->get(['id', 'title']),
        ];
    }
}; ?>

<div>
    @section('title', 'Bids')
    @section('breadcrumb', 'Workspace / Bids')

    <x-panel.page-header overline="Auctions" title="Bids" description="Read-only record of all bids placed across auctions." />

    <x-panel.card heading="All bids" description="Filter by auction and status" flush>
        <div class="flex flex-wrap items-center gap-3 border-b border-gray-100 p-4">
            <select wire:model.live="auction_id" class="rounded-lg border-gray-300 text-sm">
                <option value="">All auctions</option>
                @foreach ($auctions as $a)
                    <option value="{{ $a->id }}">{{ $a->title }}</option>
                @endforeach
            </select>
            <select wire:model.live="status" class="rounded-lg border-gray-300 text-sm">
                <option value="">All statuses</option>
                <option value="valid">Valid</option>
                <option value="rejected">Rejected</option>
            </select>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-100 text-sm">
                <thead>
                    <tr class="text-left text-xs font-semibold uppercase tracking-wider text-gray-400">
                        <th class="px-4 py-3">Auction</th>
                        <th class="px-4 py-3">Customer</th>
                        <th class="px-4 py-3">Sequence</th>
                        <th class="px-4 py-3">Amount</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Placed</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($bids as $bid)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-gray-900">{{ $bid->auction?->title ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-900">{{ $bid->customer?->full_name ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-700">#{{ $bid->bid_sequence }}</td>
                            <td class="px-4 py-3 font-semibold text-gray-900">₹{{ number_format((float) $bid->bid_amount) }}</td>
                            <td class="px-4 py-3"><x-panel.badge :color="$bid->status === 'valid' ? 'success' : 'gray'">{{ ucfirst($bid->status) }}</x-panel.badge></td>
                            <td class="px-4 py-3 text-gray-700">{{ $bid->created_at?->format('d M Y H:i') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-10 text-center text-sm text-gray-500">No bids found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-gray-100 p-4">{{ $bids->links() }}</div>
    </x-panel.card>
</div>
