@php
    $label = 'block text-sm font-medium text-gray-700';
    $control = 'mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-carboli-500 focus:ring-carboli-500';
    $err = 'mt-1 text-xs text-red-600';
@endphp

<p class="mb-3 text-sm font-semibold text-gray-900">Vehicle identification</p>
<div class="grid gap-4 sm:grid-cols-2">
    <div>
        <label class="{{ $label }}">Vehicle category</label>
        <select wire:model="category_id" class="{{ $control }}">
            <option value="">Select vehicle category</option>
            @foreach ($categories as $c)
                <option value="{{ $c->id }}">{{ $c->name }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="{{ $label }}">Registration number <span class="text-red-500">*</span></label>
        <input type="text" wire:model="registration_number" placeholder="e.g. GJ01AB1234" class="{{ $control }}">
        @error('registration_number') <p class="{{ $err }}">{{ $message }}</p> @enderror
    </div>
    <div>
        <label class="{{ $label }}">Make / brand <span class="text-red-500">*</span></label>
        <select wire:model="make_id" class="{{ $control }}">
            <option value="">Select make / brand</option>
            @foreach ($makes as $m)
                <option value="{{ $m->id }}">{{ $m->name }}</option>
            @endforeach
        </select>
        <input type="text" wire:model="make" placeholder="Make (as on documents)" class="{{ $control }} mt-2">
        @error('make') <p class="{{ $err }}">{{ $message }}</p> @enderror
    </div>
    <div>
        <label class="{{ $label }}">Model <span class="text-red-500">*</span></label>
        <input type="text" wire:model="model" placeholder="e.g. Creta" class="{{ $control }}">
        @error('model') <p class="{{ $err }}">{{ $message }}</p> @enderror
    </div>
    <div>
        <label class="{{ $label }}">Variant <span class="text-red-500">*</span></label>
        <input type="text" wire:model="variant" placeholder="e.g. SX(O)" class="{{ $control }}">
        @error('variant') <p class="{{ $err }}">{{ $message }}</p> @enderror
    </div>
    <div>
        <label class="{{ $label }}">Manufacturing year <span class="text-red-500">*</span></label>
        <input type="number" wire:model="manufacturing_year" placeholder="e.g. 2023" class="{{ $control }}">
        @error('manufacturing_year') <p class="{{ $err }}">{{ $message }}</p> @enderror
    </div>
    <div>
        <label class="{{ $label }}">Registration date</label>
        <input type="date" wire:model="registration_date" class="{{ $control }}">
    </div>
    <div>
        <label class="{{ $label }}">Ownership sequence</label>
        <select wire:model="number_of_owners" class="{{ $control }}">
            <option value="">Select ownership sequence</option>
            @for ($i = 1; $i <= 10; $i++)
                <option value="{{ $i }}">{{ $i }}{{ [1=>'st',2=>'nd',3=>'rd'][$i] ?? 'th' }} owner</option>
            @endfor
        </select>
    </div>
</div>

<p class="mb-3 mt-6 text-sm font-semibold text-gray-900">Specifications</p>
<div class="grid gap-4 sm:grid-cols-2">
    <div>
        <label class="{{ $label }}">Fuel type <span class="text-red-500">*</span></label>
        <select wire:model="fuel_type" class="{{ $control }}">
            <option value="">Select fuel type</option>
            @foreach (['petrol'=>'Petrol','diesel'=>'Diesel','cng'=>'CNG','electric'=>'Electric','hybrid'=>'Hybrid','other'=>'Other'] as $v=>$l)
                <option value="{{ $v }}">{{ $l }}</option>
            @endforeach
        </select>
        @error('fuel_type') <p class="{{ $err }}">{{ $message }}</p> @enderror
    </div>
    <div>
        <label class="{{ $label }}">Transmission <span class="text-red-500">*</span></label>
        <select wire:model="transmission" class="{{ $control }}">
            <option value="">Select transmission</option>
            <option value="manual">Manual</option>
            <option value="automatic">Automatic</option>
        </select>
        @error('transmission') <p class="{{ $err }}">{{ $message }}</p> @enderror
    </div>
    <div>
        <label class="{{ $label }}">Kilometres driven <span class="text-red-500">*</span></label>
        <input type="number" wire:model="kilometer" placeholder="e.g. 45000" class="{{ $control }}">
        @error('kilometer') <p class="{{ $err }}">{{ $message }}</p> @enderror
    </div>
    <div>
        <label class="{{ $label }}">Engine capacity (cc)</label>
        <input type="number" wire:model="engine_capacity_cc" placeholder="e.g. 1197" class="{{ $control }}">
    </div>
    <div>
        <label class="{{ $label }}">Exterior colour <span class="text-red-500">*</span></label>
        <input type="text" wire:model="color" placeholder="e.g. Pearl white" class="{{ $control }}">
        @error('color') <p class="{{ $err }}">{{ $message }}</p> @enderror
    </div>
    <div>
        <label class="{{ $label }}">Seating capacity</label>
        <select wire:model="seating_capacity" class="{{ $control }}">
            <option value="">Select seating capacity</option>
            @foreach ([2,4,5,6,7,8] as $s)
                <option value="{{ $s }}">{{ $s }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="{{ $label }}">VIN / chassis number</label>
        <input type="text" wire:model="vin" placeholder="Enter chassis number" class="{{ $control }}">
    </div>
    <div>
        <label class="{{ $label }}">Engine number</label>
        <input type="text" wire:model="engine_number" placeholder="Enter engine number" class="{{ $control }}">
    </div>
    <div>
        <label class="{{ $label }}">Stock / inventory ID <span class="text-red-500">*</span></label>
        <input type="text" wire:model="stock_inventory_id" placeholder="e.g. CB-2026-001" class="{{ $control }}">
        @error('stock_inventory_id') <p class="{{ $err }}">{{ $message }}</p> @enderror
    </div>
</div>

<p class="mb-3 mt-6 text-sm font-semibold text-gray-900">Additional information</p>
<div>
    <label class="{{ $label }}">Vehicle description</label>
    <textarea wire:model="detailed_description" rows="3" placeholder="Mention features, condition or relevant details" class="{{ $control }}"></textarea>
</div>
