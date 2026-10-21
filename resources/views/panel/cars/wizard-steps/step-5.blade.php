@php
    $label = 'block text-sm font-medium text-gray-700';
    $control = 'mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-carboli-500 focus:ring-carboli-500';
    $err = 'mt-1 text-xs text-red-600';
    $ratingOptions = fn () => collect($conditionRatings)->mapWithKeys(fn ($r) => [$r => ucfirst($r)]);
@endphp

<p class="mb-3 text-sm font-semibold text-gray-900">Vehicle condition assessment</p>
<div class="grid gap-4 sm:grid-cols-2">
    <div>
        <label class="{{ $label }}">Inspection status <span class="text-red-500">*</span></label>
        <select wire:model="inspection_status" class="{{ $control }}">
            <option value="">Select inspection status</option>
            @foreach (\App\Models\Inspection::STATUSES as $s)
                <option value="{{ $s }}">{{ \Illuminate\Support\Str::of($s)->replace('_',' ')->title() }}</option>
            @endforeach
        </select>
        @error('inspection_status') <p class="{{ $err }}">{{ $message }}</p> @enderror
    </div>
    <div>
        <label class="{{ $label }}">Assigned inspector</label>
        <select wire:model="assigned_inspector_id" class="{{ $control }}">
            <option value="">Select assigned inspector</option>
            @foreach ($managers as $u)
                <option value="{{ $u->id }}">{{ $u->name }}</option>
            @endforeach
        </select>
    </div>

    @foreach ([
        'exterior_condition' => 'Exterior condition',
        'interior_condition' => 'Interior condition',
        'engine_condition' => 'Engine condition',
        'transmission_condition' => 'Transmission condition',
        'tyre_condition' => 'Tyre condition',
        'battery_condition' => 'Battery condition',
    ] as $field => $fieldLabel)
        @php($required = ! in_array($field, ['battery_condition'], true))
        <div>
            <label class="{{ $label }}">{{ $fieldLabel }} @if($required)<span class="text-red-500">*</span>@endif</label>
            <select wire:model="{{ $field }}" class="{{ $control }}">
                <option value="">Select {{ strtolower($fieldLabel) }}</option>
                @foreach ($ratingOptions() as $v => $l)
                    <option value="{{ $v }}">{{ $l }}</option>
                @endforeach
            </select>
            @error($field) <p class="{{ $err }}">{{ $message }}</p> @enderror
        </div>
    @endforeach

    <div>
        <label class="{{ $label }}">Accident history <span class="text-red-500">*</span></label>
        <select wire:model="accident_history" class="{{ $control }}">
            <option value="">Select accident history</option>
            @foreach (\App\Models\Inspection::ACCIDENT_HISTORY as $a)
                <option value="{{ $a }}">{{ ucfirst($a) }}</option>
            @endforeach
        </select>
        @error('accident_history') <p class="{{ $err }}">{{ $message }}</p> @enderror
    </div>
    <div>
        <label class="{{ $label }}">Flood damage <span class="text-red-500">*</span></label>
        <select wire:model="flood_damage" class="{{ $control }}">
            <option value="">Select flood damage</option>
            @foreach (\App\Models\Inspection::FLOOD_DAMAGE as $f)
                <option value="{{ $f }}">{{ ucfirst($f) }}</option>
            @endforeach
        </select>
        @error('flood_damage') <p class="{{ $err }}">{{ $message }}</p> @enderror
    </div>
    <div>
        <label class="{{ $label }}">Inspection score</label>
        <input type="number" min="0" max="100" wire:model="inspection_score" placeholder="0-100" class="{{ $control }}">
        @error('inspection_score') <p class="{{ $err }}">{{ $message }}</p> @enderror
    </div>
</div>

<div class="mt-4">
    <label class="{{ $label }}">Inspector remarks</label>
    <textarea wire:model="inspector_remarks" rows="3" placeholder="Add findings and recommendations" class="{{ $control }}"></textarea>
</div>

<div class="mt-4 rounded-xl border border-dashed border-gray-300 p-4">
    <p class="text-sm font-semibold text-gray-900">Inspection report <span class="font-normal text-gray-400">· Optional PDF report</span></p>
    <input type="file" wire:model="documentUploads.inspection_report" accept=".pdf" class="mt-2 block w-full text-sm text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-carboli-50 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-carboli-700">
    <div wire:loading wire:target="documentUploads.inspection_report" class="mt-1 text-xs text-gray-400">Uploading…</div>
</div>
