@php
    $label = 'block text-sm font-medium text-gray-700';
    $control = 'mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-carboli-500 focus:ring-carboli-500';
    $err = 'mt-1 text-xs text-red-600';
@endphp

<p class="mb-3 text-sm font-semibold text-gray-900">Seller & location details</p>
<div class="grid gap-4 sm:grid-cols-2">
    <div>
        <label class="{{ $label }}">Seller type <span class="text-red-500">*</span></label>
        <select wire:model="seller_type" class="{{ $control }}">
            <option value="">Select seller type</option>
            @foreach ($sellerTypes as $t)
                <option value="{{ $t }}">{{ ucfirst($t) }}</option>
            @endforeach
        </select>
        @error('seller_type') <p class="{{ $err }}">{{ $message }}</p> @enderror
    </div>
    <div>
        <label class="{{ $label }}">Seller / owner name <span class="text-red-500">*</span></label>
        <input type="text" wire:model="seller_name" placeholder="Full legal name" class="{{ $control }}">
        @error('seller_name') <p class="{{ $err }}">{{ $message }}</p> @enderror
    </div>
    <div>
        <label class="{{ $label }}">Mobile number <span class="text-red-500">*</span></label>
        <input type="text" wire:model="seller_mobile" placeholder="+91 98765 43210" class="{{ $control }}">
        @error('seller_mobile') <p class="{{ $err }}">{{ $message }}</p> @enderror
    </div>
    <div>
        <label class="{{ $label }}">Email address</label>
        <input type="email" wire:model="seller_email" placeholder="seller@example.com" class="{{ $control }}">
        @error('seller_email') <p class="{{ $err }}">{{ $message }}</p> @enderror
    </div>
    <div>
        <label class="{{ $label }}">Seller / customer ID</label>
        <input type="text" wire:model="seller_external_reference" placeholder="e.g. CUST-00128" class="{{ $control }}">
    </div>
    <div>
        <label class="{{ $label }}">Pickup / yard location <span class="text-red-500">*</span></label>
        <select wire:model="location_id" class="{{ $control }}">
            <option value="">Select pickup / yard location</option>
            @foreach ($locations as $loc)
                <option value="{{ $loc->id }}">{{ $loc->name }}</option>
            @endforeach
        </select>
        @error('location_id') <p class="{{ $err }}">{{ $message }}</p> @enderror
    </div>
    <div>
        <label class="{{ $label }}">Address <span class="text-red-500">*</span></label>
        <input type="text" wire:model="seller_address" placeholder="Street / locality" class="{{ $control }}">
        @error('seller_address') <p class="{{ $err }}">{{ $message }}</p> @enderror
    </div>
    <div>
        <label class="{{ $label }}">City <span class="text-red-500">*</span></label>
        <input type="text" wire:model="seller_city" placeholder="e.g. Ahmedabad" class="{{ $control }}">
        @error('seller_city') <p class="{{ $err }}">{{ $message }}</p> @enderror
    </div>
    <div>
        <label class="{{ $label }}">State <span class="text-red-500">*</span></label>
        <input type="text" wire:model="seller_state" placeholder="Select state" class="{{ $control }}">
        @error('seller_state') <p class="{{ $err }}">{{ $message }}</p> @enderror
    </div>
    <div>
        <label class="{{ $label }}">PIN code <span class="text-red-500">*</span></label>
        <input type="text" wire:model="seller_pincode" placeholder="6-digit PIN" class="{{ $control }}">
        @error('seller_pincode') <p class="{{ $err }}">{{ $message }}</p> @enderror
    </div>
    <div>
        <label class="{{ $label }}">Assigned manager</label>
        <select wire:model="assigned_manager_id" class="{{ $control }}">
            <option value="">Select assigned manager</option>
            @foreach ($managers as $u)
                <option value="{{ $u->id }}">{{ $u->name }}</option>
            @endforeach
        </select>
    </div>
</div>
<div class="mt-4 rounded-lg bg-carboli-50 px-4 py-3 text-sm text-carboli-800">
    Existing seller KYC should be checked before approval. Link an existing customer via the Seller / customer ID when applicable.
</div>
