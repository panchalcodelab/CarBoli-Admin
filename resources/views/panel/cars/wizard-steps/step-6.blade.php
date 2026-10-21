@php
    $label = 'block text-sm font-medium text-gray-700';
    $control = 'mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-carboli-500 focus:ring-carboli-500';
    $err = 'mt-1 text-xs text-red-600';
@endphp

<div class="rounded-lg bg-carboli-50 px-4 py-3 text-sm text-carboli-800">
    Saving this form will not publish the vehicle or start bidding. Auction publication requires approval and a separate action.
</div>

<p class="mb-3 mt-4 text-sm font-semibold text-gray-900">Pricing</p>
<div class="grid gap-4 sm:grid-cols-2">
    <div>
        <label class="{{ $label }}">Estimated market value (₹)</label>
        <input type="number" wire:model="expected_price" placeholder="e.g. 550000" class="{{ $control }}">
    </div>
    <div>
        <label class="{{ $label }}">Reserve price (₹) <span class="text-red-500">*</span></label>
        <input type="number" wire:model="reserve_price" placeholder="e.g. 400000" class="{{ $control }}">
        @error('reserve_price') <p class="{{ $err }}">{{ $message }}</p> @enderror
    </div>
    <div>
        <label class="{{ $label }}">Starting bid (₹) <span class="text-red-500">*</span></label>
        <input type="number" wire:model="starting_price" placeholder="e.g. 350000" class="{{ $control }}">
        @error('starting_price') <p class="{{ $err }}">{{ $message }}</p> @enderror
    </div>
    <div>
        <label class="{{ $label }}">Minimum bid increment (₹) <span class="text-red-500">*</span></label>
        <select wire:model="minimum_bid_increment" class="{{ $control }}">
            <option value="">Select minimum bid increment (₹)</option>
            @foreach ([1000, 2500, 5000, 10000, 25000] as $inc)
                <option value="{{ $inc }}">₹{{ number_format($inc) }}</option>
            @endforeach
        </select>
        @error('minimum_bid_increment') <p class="{{ $err }}">{{ $message }}</p> @enderror
    </div>
    <div>
        <label class="{{ $label }}">Auction type <span class="text-red-500">*</span></label>
        <select wire:model="auction_type" class="{{ $control }}">
            <option value="">Select auction type</option>
            @foreach ($auctionTypes as $t)
                <option value="{{ $t }}">{{ \Illuminate\Support\Str::of($t)->replace('_',' ')->title() }}</option>
            @endforeach
        </select>
        @error('auction_type') <p class="{{ $err }}">{{ $message }}</p> @enderror
    </div>
    <div>
        <label class="{{ $label }}">Auction start date & time <span class="text-red-500">*</span></label>
        <input type="datetime-local" wire:model="auction_start_at" class="{{ $control }}">
        @error('auction_start_at') <p class="{{ $err }}">{{ $message }}</p> @enderror
    </div>
</div>

<p class="mb-3 mt-6 text-sm font-semibold text-gray-900">Auction schedule & eligibility</p>
<div class="grid gap-4 sm:grid-cols-2">
    <div>
        <label class="{{ $label }}">Auction end date & time <span class="text-red-500">*</span></label>
        <input type="datetime-local" wire:model="auction_end_at" class="{{ $control }}">
        @error('auction_end_at') <p class="{{ $err }}">{{ $message }}</p> @enderror
    </div>
    <div>
        <label class="{{ $label }}">Bidder eligibility <span class="text-red-500">*</span></label>
        <select wire:model="bidder_eligibility" class="{{ $control }}">
            <option value="">Select bidder eligibility</option>
            @foreach ($bidderEligibilities as $b)
                <option value="{{ $b }}">{{ \Illuminate\Support\Str::of($b)->replace('_',' ')->title() }}</option>
            @endforeach
        </select>
        @error('bidder_eligibility') <p class="{{ $err }}">{{ $message }}</p> @enderror
    </div>
    <div>
        <label class="{{ $label }}">Listing visibility <span class="text-red-500">*</span></label>
        <select wire:model="listing_visibility" class="{{ $control }}">
            <option value="">Select listing visibility</option>
            @foreach ($listingVisibilities as $v)
                <option value="{{ $v }}">{{ ucfirst($v) }}</option>
            @endforeach
        </select>
        @error('listing_visibility') <p class="{{ $err }}">{{ $message }}</p> @enderror
    </div>
    <div>
        <label class="{{ $label }}">Buyer premium (%)</label>
        <input type="number" step="0.01" wire:model="buyer_premium_percent" placeholder="e.g. 2" class="{{ $control }}">
    </div>
    <div>
        <label class="{{ $label }}">Seller commission (%)</label>
        <input type="number" step="0.01" wire:model="seller_commission_percent" placeholder="e.g. 1.5" class="{{ $control }}">
    </div>
    <div>
        <label class="{{ $label }}">Payment deadline (days)</label>
        <input type="number" wire:model="payment_deadline_days" placeholder="e.g. 3" class="{{ $control }}">
    </div>
</div>

<label class="mt-5 flex items-start gap-2 text-sm text-gray-700">
    <input type="checkbox" wire:model="confirmed" class="mt-0.5 rounded border-gray-300 text-carboli-500 focus:ring-carboli-500">
    <span>I confirm that the information entered is accurate to the best of my knowledge and the vehicle can be sent for review.</span>
</label>
@error('confirmed') <p class="{{ $err }}">{{ $message }}</p> @enderror

<div class="mt-4 rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
    <p class="text-sm font-semibold text-gray-900">Next step: review & approval</p>
    <p class="mt-1 text-sm text-gray-500">Your listing will be saved as "Under review". Documents and inspection must be approved before an auction can be published.</p>
</div>
