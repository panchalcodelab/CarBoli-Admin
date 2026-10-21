<?php

use App\Models\Auction;
use App\Services\AuctionLifecycleService;
use App\Services\AuctionResultService;
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

    public ?int $confirmingPublishId = null;

    public string $confirmingPublishTitle = '';

    public ?int $confirmingCancelId = null;

    public string $confirmingCancelTitle = '';

    public ?int $confirmingCloseId = null;

    public string $confirmingCloseTitle = '';

    public ?int $confirmingFinalizeId = null;

    public string $confirmingFinalizeTitle = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', Auction::class);
    }

    public function updating($name): void
    {
        if (in_array($name, ['search', 'status'], true)) {
            $this->resetPage();
        }
    }

    public function confirmPublish(int $auctionId): void
    {
        $auction = Auction::query()->findOrFail($auctionId);
        $this->confirmingPublishId = $auctionId;
        $this->confirmingPublishTitle = (string) $auction->title;
    }

    public function cancelPublish(): void
    {
        $this->confirmingPublishId = null;
        $this->confirmingPublishTitle = '';
    }

    public function publish(int $auctionId, AuctionLifecycleService $service): void
    {
        $this->runAction(fn () => $service->publish(Auction::query()->findOrFail($auctionId)), 'Auction published.');
        $this->confirmingPublishId = null;
        $this->confirmingPublishTitle = '';
    }

    public function confirmCancel(int $auctionId): void
    {
        $auction = Auction::query()->findOrFail($auctionId);
        $this->confirmingCancelId = $auctionId;
        $this->confirmingCancelTitle = (string) $auction->title;
    }

    public function cancelCancel(): void
    {
        $this->confirmingCancelId = null;
        $this->confirmingCancelTitle = '';
    }

    public function cancel(int $auctionId, AuctionLifecycleService $service): void
    {
        $this->runAction(fn () => $service->cancel(Auction::query()->findOrFail($auctionId)), 'Auction cancelled.');
        $this->confirmingCancelId = null;
        $this->confirmingCancelTitle = '';
    }

    public function confirmClose(int $auctionId): void
    {
        $auction = Auction::query()->findOrFail($auctionId);
        $this->confirmingCloseId = $auctionId;
        $this->confirmingCloseTitle = (string) $auction->title;
    }

    public function cancelClose(): void
    {
        $this->confirmingCloseId = null;
        $this->confirmingCloseTitle = '';
    }

    public function close(int $auctionId, AuctionLifecycleService $service): void
    {
        $this->runAction(fn () => $service->close(Auction::query()->findOrFail($auctionId)), 'Auction closed.');
        $this->confirmingCloseId = null;
        $this->confirmingCloseTitle = '';
    }

    public function confirmFinalize(int $auctionId): void
    {
        $auction = Auction::query()->findOrFail($auctionId);
        $this->confirmingFinalizeId = $auctionId;
        $this->confirmingFinalizeTitle = (string) $auction->title;
    }

    public function cancelFinalize(): void
    {
        $this->confirmingFinalizeId = null;
        $this->confirmingFinalizeTitle = '';
    }

    public function finalize(int $auctionId, AuctionResultService $service): void
    {
        $this->runAction(fn () => $service->finalize(Auction::query()->findOrFail($auctionId)), 'Auction result finalized.');
        $this->confirmingFinalizeId = null;
        $this->confirmingFinalizeTitle = '';
    }

    private function runAction(callable $action, string $message): void
    {
        try {
            $action();
            session()->flash('status', $message);
        } catch (ValidationException $e) {
            $this->addError('action', collect($e->errors())->flatten()->first());
        }
    }

    public function with(): array
    {
        $auctions = Auction::query()
            ->with(['car', 'result'])
            ->when($this->search !== '', function ($q) {
                $term = '%'.$this->search.'%';
                $q->where(fn ($sub) => $sub
                    ->where('title', 'like', $term)
                    ->orWhereHas('car', fn ($c) => $c->where('make', 'like', $term)->orWhere('model', 'like', $term)->orWhere('stock_inventory_id', 'like', $term)));
            })
            ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
            ->latest()
            ->paginate(10);

        return [
            'auctions' => $auctions,
            'statuses' => Auction::STATUSES,
            'canManage' => Gate::allows('create', Auction::class),
        ];
    }

    public function statusColor(string $status): string
    {
        return match ($status) {
            'live' => 'success',
            'scheduled' => 'info',
            'completed' => 'primary',
            'cancelled' => 'danger',
            'draft' => 'gray',
            default => 'gray',
        };
    }
}; ?>

<div>
    @section('title', 'Live Auctions')
    @section('breadcrumb', 'Workspace / Live Auctions')

    <x-panel.page-header overline="Auctions" title="Live Auctions"
        description="Create, schedule, publish and finalize vehicle auctions.">
        <x-slot:actions>
            @can('create', App\Models\Auction::class)
                <a href="{{ route('panel.auctions.create') }}" wire:navigate
                   class="inline-flex items-center gap-2 rounded-lg bg-carboli-500 px-4 py-2 text-sm font-semibold text-white hover:bg-carboli-600"><x-panel.icon name="plus" class="h-4 w-4" />Create auction</a>
            @endcan
        </x-slot:actions>
    </x-panel.page-header>

    @if (session('status'))
        <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('status') }}</div>
    @endif
    @error('action') <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $message }}</div> @enderror

    <x-panel.card heading="All auctions" description="Search and filter auctions" flush>
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 p-4">
            <div class="relative flex-1 min-w-[240px]">
                <input type="search" wire:model.live.debounce.300ms="search" placeholder="Search title or vehicle..."
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
                        <th class="px-4 py-3">Auction</th>
                        <th class="px-4 py-3">Vehicle</th>
                        <th class="px-4 py-3">Schedule</th>
                        <th class="px-4 py-3">Starting price</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($auctions as $auction)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3"><p class="font-semibold text-gray-900">{{ $auction->title }}</p></td>
                            <td class="px-4 py-3 text-gray-700">{{ $auction->car?->stock_inventory_id ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-700">
                                <span class="block text-xs">{{ $auction->start_at?->format('d M Y H:i') ?? 'Not scheduled' }}</span>
                                <span class="block text-xs text-gray-400">to {{ $auction->end_at?->format('d M Y H:i') ?? '—' }}</span>
                            </td>
                            <td class="px-4 py-3 text-gray-700">₹{{ number_format((float) $auction->starting_price) }}</td>
                            <td class="px-4 py-3"><x-panel.badge :color="$this->statusColor($auction->status)">{{ \Illuminate\Support\Str::of($auction->status)->replace('_',' ')->title() }}</x-panel.badge></td>
                            <td class="px-4 py-3 text-right">
                                <div class="inline-flex flex-wrap items-center justify-end gap-1.5">
                                    <a href="{{ route('panel.auctions.show', $auction) }}" wire:navigate class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50">View</a>
                                    @if ($auction->status === 'draft' && ! $auction->published && Gate::allows('publish', $auction))
                                        <button wire:click="confirmPublish({{ $auction->id }})" class="rounded-lg border border-green-300 px-3 py-1.5 text-xs font-medium text-green-700 hover:bg-green-50">Publish</button>
                                    @endif
                                    @if (in_array($auction->status, ['draft','scheduled','live'], true) && Gate::allows('cancel', $auction))
                                        <button wire:click="confirmCancel({{ $auction->id }})" class="rounded-lg border border-red-300 px-3 py-1.5 text-xs font-medium text-red-700 hover:bg-red-50">Cancel</button>
                                    @endif
                                    @if (in_array($auction->status, ['scheduled','live'], true) && Gate::allows('close', $auction))
                                        <button wire:click="confirmClose({{ $auction->id }})" class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50">Close</button>
                                    @endif
                                    @if ($auction->status === 'completed' && $auction->result?->status !== 'finalized' && Gate::allows('finalize', $auction))
                                        <button wire:click="confirmFinalize({{ $auction->id }})" class="rounded-lg border border-green-300 px-3 py-1.5 text-xs font-medium text-green-700 hover:bg-green-50">Finalize</button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-10 text-center text-sm text-gray-500">No auctions found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-gray-100 p-4">{{ $auctions->links() }}</div>
    </x-panel.card>

    <x-panel.confirm-modal
        :show="$confirmingPublishId !== null"
        title="Publish auction"
        confirm-label="Publish auction"
        busy-label="Publishing…"
        tone="primary"
        :confirm-action="'publish('.(int) $confirmingPublishId.')'"
        cancel-action="cancelPublish"
    >
        Publish the auction
        <span class="font-semibold text-gray-700">{{ $confirmingPublishTitle }}</span>?
        Once published it becomes visible according to its listing settings.
    </x-panel.confirm-modal>

    <x-panel.confirm-modal
        :show="$confirmingCancelId !== null"
        title="Cancel auction"
        confirm-label="Cancel auction"
        busy-label="Cancelling…"
        :confirm-action="'cancel('.(int) $confirmingCancelId.')'"
        cancel-action="cancelCancel"
    >
        Cancel the auction
        <span class="font-semibold text-gray-700">{{ $confirmingCancelTitle }}</span>?
        This stops the auction and cannot be undone.
    </x-panel.confirm-modal>

    <x-panel.confirm-modal
        :show="$confirmingCloseId !== null"
        title="Close auction"
        confirm-label="Close auction"
        busy-label="Closing…"
        :confirm-action="'close('.(int) $confirmingCloseId.')'"
        cancel-action="cancelClose"
    >
        Close the auction
        <span class="font-semibold text-gray-700">{{ $confirmingCloseTitle }}</span>?
        Bidding will end and no further bids will be accepted.
    </x-panel.confirm-modal>

    <x-panel.confirm-modal
        :show="$confirmingFinalizeId !== null"
        title="Finalize auction result"
        confirm-label="Finalize result"
        busy-label="Finalizing…"
        tone="primary"
        :confirm-action="'finalize('.(int) $confirmingFinalizeId.')'"
        cancel-action="cancelFinalize"
    >
        Finalize the result for
        <span class="font-semibold text-gray-700">{{ $confirmingFinalizeTitle }}</span>?
        This locks in the outcome and cannot be undone.
    </x-panel.confirm-modal>
</div>
