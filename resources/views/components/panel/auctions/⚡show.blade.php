<?php

use App\Models\Auction;
use App\Services\AuctionLifecycleService;
use App\Services\AuctionResultService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('panel.layouts.app')] class extends Component
{
    public int $auctionId;

    /** Which action is awaiting confirmation: publish|close|cancel|finalize|null. */
    public ?string $confirming = null;

    public function mount(int $auction): void
    {
        $record = Auction::query()->findOrFail($auction);
        Gate::authorize('view', $record);
        $this->auctionId = $record->id;
    }

    public function confirmAction(string $action): void
    {
        $this->confirming = in_array($action, ['publish', 'close', 'cancel', 'finalize'], true)
            ? $action
            : null;
    }

    public function cancelConfirm(): void
    {
        $this->confirming = null;
    }

    public function auction(): Auction
    {
        return Auction::query()
            ->with(['car', 'result.winner', 'result.winningBid', 'participants.customer', 'bids.customer'])
            ->findOrFail($this->auctionId);
    }

    public function publish(AuctionLifecycleService $service): void
    {
        $this->run(fn () => $service->publish($this->auction()), 'Auction published.');
    }

    public function cancel(AuctionLifecycleService $service): void
    {
        $this->run(fn () => $service->cancel($this->auction()), 'Auction cancelled.');
    }

    public function close(AuctionLifecycleService $service): void
    {
        $this->run(fn () => $service->close($this->auction()), 'Auction closed.');
    }

    public function finalize(AuctionResultService $service): void
    {
        $this->run(fn () => $service->finalize($this->auction()), 'Auction result finalized.');
    }

    private function run(callable $action, string $message): void
    {
        try {
            $action();
            session()->flash('status', $message);
        } catch (ValidationException $e) {
            $this->addError('action', collect($e->errors())->flatten()->first());
        } finally {
            $this->confirming = null;
        }
    }
}; ?>

<div>
    @php($auction = $this->auction())
    @section('title', $auction->title)
    @section('breadcrumb', 'Workspace / Live Auctions / ' . $auction->title)

    <a href="{{ route('panel.auctions.index') }}" wire:navigate class="mb-2 inline-flex items-center gap-1 text-sm text-gray-500 hover:text-carboli-600">← Back to Live Auctions</a>

    <x-panel.page-header :title="$auction->title" :description="$auction->car?->stock_inventory_id">
        <x-slot:actions>
            @if ($auction->status === 'draft' && ! $auction->published && Gate::allows('update', $auction))
                <a href="{{ route('panel.auctions.edit', $auction) }}" wire:navigate class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Edit</a>
            @endif
            @if ($auction->status === 'draft' && ! $auction->published && Gate::allows('publish', $auction))
                <button wire:click="confirmAction('publish')" class="rounded-lg bg-carboli-500 px-4 py-2 text-sm font-semibold text-white hover:bg-carboli-600">Publish</button>
            @endif
            @if (in_array($auction->status, ['scheduled','live'], true) && Gate::allows('close', $auction))
                <button wire:click="confirmAction('close')" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Close</button>
            @endif
            @if (in_array($auction->status, ['draft','scheduled','live'], true) && Gate::allows('cancel', $auction))
                <button wire:click="confirmAction('cancel')" class="rounded-lg border border-red-300 px-4 py-2 text-sm font-medium text-red-700 hover:bg-red-50">Cancel</button>
            @endif
            @if ($auction->status === 'completed' && $auction->result?->status !== 'finalized' && Gate::allows('finalize', $auction))
                <button wire:click="confirmAction('finalize')" class="rounded-lg bg-carboli-500 px-4 py-2 text-sm font-semibold text-white hover:bg-carboli-600">Finalize result</button>
            @endif
            <x-panel.badge :color="$auction->status === 'live' ? 'success' : ($auction->status === 'cancelled' ? 'danger' : 'gray')">{{ \Illuminate\Support\Str::of($auction->status)->replace('_',' ')->title() }}</x-panel.badge>
        </x-slot:actions>
    </x-panel.page-header>

    @if (session('status'))
        <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('status') }}</div>
    @endif
    @error('action') <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $message }}</div> @enderror

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-panel.card heading="Auction details">
                <dl class="grid grid-cols-2 gap-4 text-sm">
                    @foreach ([
                        'Vehicle' => $auction->car ? trim($auction->car->make.' '.$auction->car->model) : '—',
                        'Inventory ID' => $auction->car?->stock_inventory_id,
                        'Starts' => $auction->start_at?->format('d M Y H:i') ?? 'Not scheduled',
                        'Ends' => $auction->end_at?->format('d M Y H:i') ?? '—',
                        'Starting price' => '₹'.number_format((float) $auction->starting_price),
                        'Min increment' => '₹'.number_format((float) $auction->minimum_bid_increment),
                        'Reserve price' => $auction->reserve_price !== null ? '₹'.number_format((float) $auction->reserve_price) : '—',
                        'Participation fee' => '₹'.number_format((float) $auction->participation_fee),
                        'Published' => $auction->published ? 'Yes' : 'No',
                    ] as $k => $v)
                        <div><dt class="text-xs uppercase tracking-wide text-gray-400">{{ $k }}</dt><dd class="mt-0.5 text-gray-900">{{ $v !== null && $v !== '' ? $v : '—' }}</dd></div>
                    @endforeach
                </dl>
            </x-panel.card>

            <x-panel.card heading="Bids" description="{{ $auction->bids->count() }} total">
                @forelse ($auction->bids->sortByDesc('bid_sequence')->take(10) as $bid)
                    <div class="flex items-center justify-between border-b border-gray-100 py-2 text-sm last:border-0">
                        <span class="text-gray-900">#{{ $bid->bid_sequence }} · {{ $bid->customer?->full_name ?? '—' }}</span>
                        <span class="flex items-center gap-2"><span class="font-semibold">₹{{ number_format((float) $bid->bid_amount) }}</span><x-panel.badge :color="$bid->status === 'valid' ? 'success' : 'gray'">{{ ucfirst($bid->status) }}</x-panel.badge></span>
                    </div>
                @empty
                    <p class="text-sm text-gray-500">No bids yet.</p>
                @endforelse
            </x-panel.card>
        </div>

        <div class="space-y-6">
            <x-panel.card heading="Result">
                @if ($auction->result)
                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between"><dt class="text-gray-500">Status</dt><dd><x-panel.badge :color="$auction->result->status === 'finalized' ? 'success' : 'gray'">{{ ucfirst($auction->result->status) }}</x-panel.badge></dd></div>
                        <div><dt class="text-gray-500">Winner</dt><dd class="text-gray-900">{{ $auction->result->winner?->full_name ?? 'No winner' }}</dd></div>
                        <div><dt class="text-gray-500">Winning bid</dt><dd class="text-gray-900">{{ $auction->result->winningBid ? '₹'.number_format((float) $auction->result->winningBid->bid_amount) : '—' }}</dd></div>
                        <div class="flex justify-between"><dt class="text-gray-500">Settlement</dt><dd><x-panel.badge :color="$auction->result->settlement_status === 'settled' ? 'success' : 'gray'">{{ \Illuminate\Support\Str::of($auction->result->settlement_status)->replace('_',' ')->title() }}</x-panel.badge></dd></div>
                    </dl>
                @else
                    <p class="text-sm text-gray-500">Not finalized yet.</p>
                @endif
            </x-panel.card>

            <x-panel.card heading="Participants" description="{{ $auction->participants->count() }} registered">
                @forelse ($auction->participants->take(10) as $p)
                    <div class="flex items-center justify-between border-b border-gray-100 py-2 text-sm last:border-0">
                        <span class="text-gray-900">{{ $p->customer?->full_name ?? '—' }}</span>
                        <x-panel.badge :color="$p->eligibility_status === 'eligible' ? 'success' : ($p->eligibility_status === 'ineligible' ? 'danger' : 'gray')">{{ ucfirst($p->eligibility_status) }}</x-panel.badge>
                    </div>
                @empty
                    <p class="text-sm text-gray-500">No participants registered.</p>
                @endforelse
            </x-panel.card>
        </div>
    </div>

    <x-panel.confirm-modal
        :show="$confirming === 'publish'"
        title="Publish auction"
        confirm-label="Publish auction"
        busy-label="Publishing…"
        tone="primary"
        confirm-action="publish"
        cancel-action="cancelConfirm"
    >
        Publish <span class="font-semibold text-gray-700">{{ $auction->title }}</span>?
        Once published it becomes visible according to its listing settings.
    </x-panel.confirm-modal>

    <x-panel.confirm-modal
        :show="$confirming === 'close'"
        title="Close auction"
        confirm-label="Close auction"
        busy-label="Closing…"
        confirm-action="close"
        cancel-action="cancelConfirm"
    >
        Close <span class="font-semibold text-gray-700">{{ $auction->title }}</span>?
        Bidding will end and no further bids will be accepted.
    </x-panel.confirm-modal>

    <x-panel.confirm-modal
        :show="$confirming === 'cancel'"
        title="Cancel auction"
        confirm-label="Cancel auction"
        busy-label="Cancelling…"
        confirm-action="cancel"
        cancel-action="cancelConfirm"
    >
        Cancel <span class="font-semibold text-gray-700">{{ $auction->title }}</span>?
        This stops the auction and cannot be undone.
    </x-panel.confirm-modal>

    <x-panel.confirm-modal
        :show="$confirming === 'finalize'"
        title="Finalize auction result"
        confirm-label="Finalize result"
        busy-label="Finalizing…"
        tone="primary"
        confirm-action="finalize"
        cancel-action="cancelConfirm"
    >
        Finalize the result for <span class="font-semibold text-gray-700">{{ $auction->title }}</span>?
        This locks in the outcome and cannot be undone.
    </x-panel.confirm-modal>
</div>
