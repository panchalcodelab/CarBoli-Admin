<?php

use App\Models\Auction;
use App\Models\Car;
use App\Services\AuctionLifecycleService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('panel.layouts.app')] class extends Component
{
    public ?int $auctionId = null;

    public ?int $car_id = null;
    public string $title = '';
    public string $description = '';
    public ?string $start_at = null;
    public ?string $end_at = null;
    public ?string $starting_price = null;
    public ?string $minimum_bid_increment = null;
    public ?string $reserve_price = null;
    public ?string $participation_fee = '0';

    public function mount(?int $auction = null): void
    {
        if ($auction) {
            $record = Auction::query()->findOrFail($auction);
            Gate::authorize('update', $record);
            $this->auctionId = $record->id;
            $this->car_id = $record->car_id;
            $this->title = (string) $record->title;
            $this->description = (string) $record->description;
            $this->start_at = $record->start_at?->format('Y-m-d\TH:i');
            $this->end_at = $record->end_at?->format('Y-m-d\TH:i');
            $this->starting_price = $record->starting_price !== null ? (string) $record->starting_price : null;
            $this->minimum_bid_increment = $record->minimum_bid_increment !== null ? (string) $record->minimum_bid_increment : null;
            $this->reserve_price = $record->reserve_price !== null ? (string) $record->reserve_price : null;
            $this->participation_fee = $record->participation_fee !== null ? (string) $record->participation_fee : '0';
        } else {
            Gate::authorize('create', Auction::class);
        }
    }

    public function save(AuctionLifecycleService $service): void
    {
        $data = [
            'car_id' => $this->car_id,
            'title' => $this->title,
            'description' => $this->description ?: null,
            'start_at' => $this->start_at ?: null,
            'end_at' => $this->end_at ?: null,
            'starting_price' => $this->starting_price,
            'minimum_bid_increment' => $this->minimum_bid_increment,
            'reserve_price' => $this->reserve_price ?: null,
            'participation_fee' => $this->participation_fee ?: 0,
        ];

        try {
            $auction = $this->auctionId
                ? $service->update(Auction::query()->findOrFail($this->auctionId), $data)
                : $service->create($data);

            session()->flash('status', $this->auctionId ? 'Auction updated.' : 'Auction created as draft.');
            $this->redirectRoute('panel.auctions.show', ['auction' => $auction->id], navigate: true);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $key => $messages) {
                $this->addError($key, $messages[0]);
            }
        }
    }

    public function with(): array
    {
        return [
            'cars' => Car::query()->whereIn('status', ['available', 'draft', 'inspection_scheduled', 'auction_scheduled'])->orderBy('stock_inventory_id')->get(),
        ];
    }
}; ?>

<div>
    @section('title', $auctionId ? 'Edit auction' : 'Create auction')
    @section('breadcrumb', 'Workspace / Live Auctions / ' . ($auctionId ? 'Edit' : 'Create'))

    <a href="{{ route('panel.auctions.index') }}" wire:navigate class="mb-2 inline-flex items-center gap-1 text-sm text-gray-500 hover:text-carboli-600">← Back to Live Auctions</a>
    <x-panel.page-header :title="$auctionId ? 'Edit auction' : 'Create auction'"
        description="Configure the vehicle, schedule and pricing. Publishing is a separate, authorized step." />

    @php($label = 'block text-sm font-medium text-gray-700')
    @php($control = 'mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-carboli-500 focus:ring-carboli-500')

    <x-panel.card>
        <div class="grid gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <label class="{{ $label }}">Vehicle <span class="text-red-500">*</span></label>
                <select wire:model="car_id" class="{{ $control }}">
                    <option value="">Select vehicle</option>
                    @foreach ($cars as $c)
                        <option value="{{ $c->id }}">{{ $c->stock_inventory_id }} — {{ trim($c->make.' '.$c->model) }}</option>
                    @endforeach
                </select>
                @error('car_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div class="sm:col-span-2">
                <label class="{{ $label }}">Title <span class="text-red-500">*</span></label>
                <input type="text" wire:model="title" class="{{ $control }}">
                @error('title') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div class="sm:col-span-2">
                <label class="{{ $label }}">Description</label>
                <textarea wire:model="description" rows="2" class="{{ $control }}"></textarea>
            </div>
            <div>
                <label class="{{ $label }}">Start date & time</label>
                <input type="datetime-local" wire:model="start_at" class="{{ $control }}">
                @error('start_at') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="{{ $label }}">End date & time</label>
                <input type="datetime-local" wire:model="end_at" class="{{ $control }}">
                @error('end_at') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="{{ $label }}">Starting price (₹) <span class="text-red-500">*</span></label>
                <input type="number" wire:model="starting_price" class="{{ $control }}">
                @error('starting_price') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="{{ $label }}">Minimum bid increment (₹) <span class="text-red-500">*</span></label>
                <input type="number" wire:model="minimum_bid_increment" class="{{ $control }}">
                @error('minimum_bid_increment') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="{{ $label }}">Reserve price (₹)</label>
                <input type="number" wire:model="reserve_price" class="{{ $control }}">
                @error('reserve_price') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="{{ $label }}">Participation fee (₹)</label>
                <input type="number" wire:model="participation_fee" class="{{ $control }}">
                @error('participation_fee') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="mt-6 flex items-center justify-end gap-2 border-t border-gray-100 pt-4">
            <a href="{{ route('panel.auctions.index') }}" wire:navigate class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</a>
            <button wire:click="save" class="rounded-lg bg-carboli-500 px-4 py-2 text-sm font-semibold text-white hover:bg-carboli-600">{{ $auctionId ? 'Save changes' : 'Create auction' }}</button>
        </div>
    </x-panel.card>
</div>
