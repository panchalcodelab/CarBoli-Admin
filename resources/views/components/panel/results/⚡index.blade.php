<?php

use App\Models\Auction;
use App\Models\AuctionResult;
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

    #[Url]
    public string $settlement_status = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', AuctionResult::class);
    }

    public function updating($name): void
    {
        if (in_array($name, ['auction_id', 'status', 'settlement_status'], true)) {
            $this->resetPage();
        }
    }

    public function with(): array
    {
        $results = AuctionResult::query()
            ->with(['auction', 'winner', 'winningBid', 'finalizedBy'])
            ->when($this->auction_id !== '', fn ($q) => $q->where('auction_id', $this->auction_id))
            ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
            ->when($this->settlement_status !== '', fn ($q) => $q->where('settlement_status', $this->settlement_status))
            ->latest()
            ->paginate(15);

        return [
            'results' => $results,
            'auctions' => Auction::query()->orderBy('title')->get(['id', 'title']),
        ];
    }
}; ?>

<div>
    @section('title', 'Results')
    @section('breadcrumb', 'Workspace / Results')

    <x-panel.page-header overline="Auctions" title="Results" description="Finalized auction outcomes, winners and settlement status." />

    <x-panel.card heading="Auction results" description="Filter by auction, status and settlement" flush>
        <div class="flex flex-wrap items-center gap-3 border-b border-gray-100 p-4">
            <select wire:model.live="auction_id" class="rounded-lg border-gray-300 text-sm">
                <option value="">All auctions</option>
                @foreach ($auctions as $a)
                    <option value="{{ $a->id }}">{{ $a->title }}</option>
                @endforeach
            </select>
            <select wire:model.live="status" class="rounded-lg border-gray-300 text-sm">
                <option value="">All statuses</option>
                <option value="pending">Pending</option>
                <option value="finalized">Finalized</option>
            </select>
            <select wire:model.live="settlement_status" class="rounded-lg border-gray-300 text-sm">
                <option value="">All settlement statuses</option>
                <option value="pending">Pending</option>
                <option value="not_required">Not required</option>
                <option value="settled">Settled</option>
            </select>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-100 text-sm">
                <thead>
                    <tr class="text-left text-xs font-semibold uppercase tracking-wider text-gray-400">
                        <th class="px-4 py-3">Auction</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Winner</th>
                        <th class="px-4 py-3">Winning bid</th>
                        <th class="px-4 py-3">Finalized by</th>
                        <th class="px-4 py-3">Settlement</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($results as $r)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-gray-900">{{ $r->auction?->title ?? '—' }}</td>
                            <td class="px-4 py-3"><x-panel.badge :color="$r->status === 'finalized' ? 'success' : 'gray'">{{ ucfirst($r->status) }}</x-panel.badge></td>
                            <td class="px-4 py-3 text-gray-900">{{ $r->winner?->full_name ?? 'No winner' }}</td>
                            <td class="px-4 py-3 text-gray-700">{{ $r->winningBid ? '₹'.number_format((float) $r->winningBid->bid_amount) : '—' }}</td>
                            <td class="px-4 py-3 text-gray-700">{{ $r->finalizedBy?->name ?? '—' }}</td>
                            <td class="px-4 py-3"><x-panel.badge :color="$r->settlement_status === 'settled' ? 'success' : 'gray'">{{ \Illuminate\Support\Str::of($r->settlement_status)->replace('_',' ')->title() }}</x-panel.badge></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-10 text-center text-sm text-gray-500">No results found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-gray-100 p-4">{{ $results->links() }}</div>
    </x-panel.card>
</div>
