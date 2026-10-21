<?php

use App\Models\Auction;
use App\Models\Car;
use App\Models\CarImage;
use App\Models\CarSeller;
use App\Models\Inspection;
use App\Models\Location;
use App\Models\Make;
use App\Models\User;
use App\Models\VehicleCategory;
use App\Services\CarApprovalService;
use App\Services\CarInventoryService;
use App\Services\CarMediaService;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Layout('panel.layouts.app')] class extends Component
{
    use WithFileUploads;

    public ?int $carId = null;
    public int $step = 1;
    public int $maxStep = 6;

    // Step 1 — Vehicle details
    public ?int $category_id = null;
    public string $registration_number = '';
    public ?int $make_id = null;
    public string $make = '';
    public string $model = '';
    public string $variant = '';
    public ?int $manufacturing_year = null;
    public ?string $registration_date = null;
    public ?int $number_of_owners = null;
    public string $fuel_type = '';
    public string $transmission = '';
    public ?int $engine_capacity_cc = null;
    public ?string $kilometer = null;
    public string $color = '';
    public ?int $seating_capacity = null;
    public string $vin = '';
    public string $engine_number = '';
    public string $stock_inventory_id = '';
    public string $detailed_description = '';

    // Step 2 — Owner & seller
    public string $seller_type = '';
    public string $seller_name = '';
    public string $seller_mobile = '';
    public string $seller_email = '';
    public string $seller_external_reference = '';
    public ?int $seller_customer_id = null;
    public ?int $location_id = null;
    public string $seller_address = '';
    public string $seller_city = '';
    public string $seller_state = '';
    public string $seller_pincode = '';
    public ?int $assigned_manager_id = null;

    // Step 3 — Documents + Step 4 — Photos/video (uploads)
    public $documentUploads = [];   // ['rc' => UploadedFile, ...]
    public $imageUploads = [];      // ['front' => UploadedFile, ...]
    public $walkaroundVideo = null;

    // Step 5 — Inspection & condition
    public string $inspection_status = '';
    public ?int $assigned_inspector_id = null;
    public string $exterior_condition = '';
    public string $interior_condition = '';
    public string $engine_condition = '';
    public string $transmission_condition = '';
    public string $tyre_condition = '';
    public string $battery_condition = '';
    public string $accident_history = '';
    public string $flood_damage = '';
    public ?int $inspection_score = null;
    public string $inspector_remarks = '';

    // Step 6 — Auction configuration
    public ?string $expected_price = null;
    public ?string $reserve_price = null;
    public ?string $starting_price = null;
    public ?string $minimum_bid_increment = null;
    public string $auction_type = '';
    public ?string $auction_start_at = null;
    public ?string $auction_end_at = null;
    public string $bidder_eligibility = '';
    public string $listing_visibility = '';
    public ?string $buyer_premium_percent = null;
    public ?string $seller_commission_percent = null;
    public ?int $payment_deadline_days = null;
    public bool $confirmed = false;

    public function mount(?int $car = null): void
    {
        Gate::authorize($car ? 'update' : 'create', $car ? Car::query()->findOrFail($car) : Car::class);

        if ($car) {
            $record = Car::query()->with('seller')->findOrFail($car);
            $this->carId = $record->id;
            $this->fillFromCar($record);
        }
    }

    private function fillFromCar(Car $car): void
    {
        $this->category_id = $car->category_id;
        $this->registration_number = (string) $car->registration_number;
        $this->make_id = $car->make_id;
        $this->make = (string) $car->make;
        $this->model = (string) $car->model;
        $this->variant = (string) $car->variant;
        $this->manufacturing_year = $car->manufacturing_year;
        $this->registration_date = $car->registration_date?->format('Y-m-d');
        $this->number_of_owners = $car->number_of_owners;
        $this->fuel_type = (string) $car->fuel_type;
        $this->transmission = (string) $car->transmission;
        $this->engine_capacity_cc = $car->engine_capacity_cc;
        $this->kilometer = $car->kilometer !== null ? (string) $car->kilometer : null;
        $this->color = (string) $car->color;
        $this->seating_capacity = $car->seating_capacity;
        $this->vin = (string) $car->vin;
        $this->engine_number = (string) $car->engine_number;
        $this->stock_inventory_id = (string) $car->stock_inventory_id;
        $this->detailed_description = (string) $car->detailed_description;
        $this->location_id = $car->location_id;
        $this->assigned_manager_id = $car->assigned_manager_id;
        $this->expected_price = $car->expected_price !== null ? (string) $car->expected_price : null;

        if ($seller = $car->seller) {
            $this->seller_type = (string) $seller->seller_type;
            $this->seller_name = (string) $seller->name;
            $this->seller_mobile = (string) $seller->mobile;
            $this->seller_email = (string) $seller->email;
            $this->seller_external_reference = (string) $seller->external_reference;
            $this->seller_customer_id = $seller->customer_id;
            $this->seller_address = (string) $seller->address;
            $this->seller_city = (string) $seller->city;
            $this->seller_state = (string) $seller->state;
            $this->seller_pincode = (string) $seller->pincode;
        }
    }

    public function nextStep(): void
    {
        $this->validateStep($this->step);
        if ($this->step < $this->maxStep) {
            $this->step++;
        }
    }

    public function previousStep(): void
    {
        if ($this->step > 1) {
            $this->step--;
        }
    }

    public function goToStep(int $target): void
    {
        if ($target < $this->step) {
            $this->step = $target;
        }
    }

    private function validateStep(int $step): void
    {
        $rules = match ($step) {
            1 => [
                'registration_number' => ['required', 'string', 'max:255'],
                'make' => ['required', 'string', 'max:255'],
                'model' => ['required', 'string', 'max:255'],
                'variant' => ['required', 'string', 'max:255'],
                'manufacturing_year' => ['required', 'integer', 'between:1900,'.(now()->year + 1)],
                'fuel_type' => ['required', 'string'],
                'transmission' => ['required', 'string'],
                'kilometer' => ['required', 'numeric', 'min:0'],
                'color' => ['required', 'string', 'max:100'],
                'stock_inventory_id' => ['required', 'string', 'max:255'],
            ],
            2 => [
                'seller_type' => ['required', 'string'],
                'seller_name' => ['required', 'string', 'max:255'],
                'seller_mobile' => ['required', 'string', 'max:20'],
                'location_id' => ['required', 'integer'],
                'seller_address' => ['required', 'string', 'max:500'],
                'seller_city' => ['required', 'string', 'max:255'],
                'seller_state' => ['required', 'string', 'max:255'],
                'seller_pincode' => ['required', 'string', 'max:10'],
            ],
            4 => [
                'imageUploads.*' => ['nullable', 'image', 'max:2048'],
                'walkaroundVideo' => ['nullable', 'file', 'mimes:mp4,webm,mov', 'max:'.$this->maxVideoKb()],
            ],
            5 => [
                'inspection_status' => ['required', 'string'],
                'exterior_condition' => ['required', 'string'],
                'interior_condition' => ['required', 'string'],
                'engine_condition' => ['required', 'string'],
                'transmission_condition' => ['required', 'string'],
                'tyre_condition' => ['required', 'string'],
                'accident_history' => ['required', 'string'],
                'flood_damage' => ['required', 'string'],
            ],
            6 => [
                'reserve_price' => ['required', 'numeric', 'min:0'],
                'starting_price' => ['required', 'numeric', 'min:0'],
                'minimum_bid_increment' => ['required', 'numeric', 'gt:0'],
                'auction_type' => ['required', 'string'],
                'auction_start_at' => ['required', 'date'],
                'auction_end_at' => ['required', 'date', 'after:auction_start_at'],
                'bidder_eligibility' => ['required', 'string'],
                'listing_visibility' => ['required', 'string'],
            ],
            default => [],
        };

        if ($rules !== []) {
            $this->validate($rules);
        }
    }

    public function maxVideoKb(): int
    {
        // Product target is 15 MB; clamp to the server's configured upload limit.
        $target = 15 * 1024;
        $serverKb = (int) ($this->bytesFromIni(ini_get('upload_max_filesize')) / 1024);

        return $serverKb > 0 ? min($target, $serverKb) : $target;
    }

    private function bytesFromIni(string $value): int
    {
        $value = trim($value);
        $unit = strtolower(substr($value, -1));
        $num = (int) $value;

        return match ($unit) {
            'g' => $num * 1024 ** 3,
            'm' => $num * 1024 ** 2,
            'k' => $num * 1024,
            default => (int) $value,
        };
    }

    public function saveDraft(CarInventoryService $service, CarMediaService $media): void
    {
        // A draft only needs step 1 + 2 minimally valid to persist a record.
        $this->validateStep(1);
        $this->validateStep(2);
        $car = $service->saveWizard($this->payload(), $this->currentCar());
        $this->carId = $car->id;
        $this->persistMedia($car, $media);
        session()->flash('status', 'Draft saved.');
        $this->redirectRoute('panel.cars.edit', ['car' => $car->id], navigate: true);
    }

    public function submit(CarInventoryService $service, CarMediaService $media, CarApprovalService $approval): void
    {
        foreach ([1, 2, 4, 5, 6] as $s) {
            $this->validateStep($s);
        }

        $this->validate(['confirmed' => ['accepted']], [
            'confirmed.accepted' => 'Please confirm the information is accurate before submitting.',
        ]);

        $car = $service->saveWizard($this->payload(), $this->currentCar());
        $this->carId = $car->id;
        $this->persistMedia($car, $media);

        // Figma: submitting sends the listing to "Under review".
        $approval->submitForReview($car);

        session()->flash('status', 'Listing submitted for review.');
        $this->redirectRoute('panel.cars.show', ['car' => $car->id], navigate: true);
    }

    private function currentCar(): ?Car
    {
        return $this->carId ? Car::query()->find($this->carId) : null;
    }

    private function persistMedia(Car $car, CarMediaService $media): void
    {
        foreach ($this->imageUploads as $slot => $file) {
            if ($file) {
                $media->storeImage($car, $file, $slot);
            }
        }
        $this->imageUploads = [];

        foreach ($this->documentUploads as $type => $file) {
            if ($file) {
                $media->storeDocument($car, $file, $type);
            }
        }
        $this->documentUploads = [];

        if ($this->walkaroundVideo) {
            $media->storeWalkaroundVideo($car, $this->walkaroundVideo);
            $this->walkaroundVideo = null;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        return [
            'category_id' => $this->category_id,
            'make_id' => $this->make_id,
            'stock_inventory_id' => $this->stock_inventory_id,
            'make' => $this->make,
            'model' => $this->model,
            'variant' => $this->variant ?: null,
            'manufacturing_year' => $this->manufacturing_year,
            'registration_date' => $this->registration_date ?: null,
            'number_of_owners' => $this->number_of_owners,
            'fuel_type' => $this->fuel_type,
            'transmission' => $this->transmission,
            'engine_capacity_cc' => $this->engine_capacity_cc,
            'kilometer' => $this->kilometer,
            'color' => $this->color,
            'seating_capacity' => $this->seating_capacity,
            'vin' => $this->vin ?: null,
            'engine_number' => $this->engine_number ?: null,
            'registration_number' => $this->registration_number,
            'detailed_description' => $this->detailed_description ?: null,
            'location_id' => $this->location_id,
            'assigned_manager_id' => $this->assigned_manager_id,
            'seller_type' => $this->seller_type,
            'seller_name' => $this->seller_name,
            'seller_mobile' => $this->seller_mobile,
            'seller_email' => $this->seller_email ?: null,
            'seller_external_reference' => $this->seller_external_reference ?: null,
            'seller_customer_id' => $this->seller_customer_id,
            'seller_address' => $this->seller_address,
            'seller_city' => $this->seller_city,
            'seller_state' => $this->seller_state,
            'seller_pincode' => $this->seller_pincode,
            'inspection_status' => $this->inspection_status ?: null,
            'assigned_inspector_id' => $this->assigned_inspector_id,
            'exterior_condition' => $this->exterior_condition ?: null,
            'interior_condition' => $this->interior_condition ?: null,
            'engine_condition' => $this->engine_condition ?: null,
            'transmission_condition' => $this->transmission_condition ?: null,
            'tyre_condition' => $this->tyre_condition ?: null,
            'battery_condition' => $this->battery_condition ?: null,
            'accident_history' => $this->accident_history ?: null,
            'flood_damage' => $this->flood_damage ?: null,
            'inspection_score' => $this->inspection_score,
            'inspector_remarks' => $this->inspector_remarks ?: null,
            'expected_price' => $this->expected_price,
            'reserve_price' => $this->reserve_price,
            'starting_price' => $this->starting_price,
            'minimum_bid_increment' => $this->minimum_bid_increment,
            'auction_type' => $this->auction_type ?: null,
            'auction_start_at' => $this->auction_start_at ?: null,
            'auction_end_at' => $this->auction_end_at ?: null,
            'bidder_eligibility' => $this->bidder_eligibility ?: null,
            'listing_visibility' => $this->listing_visibility ?: null,
            'buyer_premium_percent' => $this->buyer_premium_percent,
            'seller_commission_percent' => $this->seller_commission_percent,
            'payment_deadline_days' => $this->payment_deadline_days,
        ];
    }

    public function with(): array
    {
        return [
            'categories' => VehicleCategory::query()->where('is_active', true)->orderBy('name')->get(),
            'makes' => Make::query()->where('is_active', true)->orderBy('name')->get(),
            'locations' => Location::query()->orderBy('name')->get(),
            'managers' => User::query()->orderBy('name')->get(),
            'steps' => [
                1 => ['Vehicle details', 'Identity & specifications'],
                2 => ['Owner & seller', 'Seller and pickup details'],
                3 => ['Documents', 'RC, insurance and supporting files'],
                4 => ['Photos & media', 'Exterior, interior and condition photos'],
                5 => ['Inspection', 'Condition and inspection report'],
                6 => ['Auction setup', 'Pricing, eligibility and schedule'],
            ],
            'conditionRatings' => Inspection::CONDITION_RATINGS,
            'sellerTypes' => CarSeller::SELLER_TYPES,
            'auctionTypes' => Auction::AUCTION_TYPES,
            'bidderEligibilities' => Auction::BIDDER_ELIGIBILITY,
            'listingVisibilities' => Auction::LISTING_VISIBILITY,
            'imageSlots' => [
                'front' => ['Front view', 'Required'],
                'rear' => ['Rear view', 'Required'],
                'left' => ['Left side', 'Required'],
                'right' => ['Right side', 'Required'],
                'interior' => ['Interior / dashboard', 'Required'],
                'odometer' => ['Odometer', 'Required'],
                'engine_bay' => ['Engine bay', 'Recommended'],
                'tyres' => ['Tyres / damage', 'If applicable'],
                'additional' => ['Additional photos', 'Optional'],
            ],
        ];
    }
}; ?>

<div>
    @section('title', 'Add new car')
    @section('breadcrumb', 'Workspace / Car Management / ' . ($carId ? 'Edit' : 'Add new car'))

    <a href="{{ route('panel.cars.index') }}" wire:navigate class="mb-2 inline-flex items-center gap-1 text-sm text-gray-500 hover:text-carboli-600">← Back to Car Management</a>
    <p class="text-sm font-semibold uppercase tracking-wide text-carboli-600">Car Management / {{ $carId ? 'Edit listing' : 'Create listing' }}</p>
    <h1 class="mt-1 text-2xl font-bold tracking-tight text-gray-950">{{ $carId ? 'Edit car' : 'Add new car' }}</h1>
    <p class="mt-1 text-sm text-gray-500">Add vehicle information, verify documents and prepare it for auction.</p>

    <div class="mt-6 flex flex-col gap-6 lg:flex-row lg:items-start">
        {{-- Stepper --}}
        <aside class="w-full shrink-0 lg:w-72">
            <div class="rounded-xl border border-gray-200 bg-white p-4">
                <p class="px-1 pb-2 text-xs font-semibold uppercase tracking-wider text-gray-400">Vehicle setup</p>
                @foreach ($steps as $index => [$label, $desc])
                    <button type="button" wire:click="goToStep({{ $index }})"
                        @class([
                            'flex w-full items-start gap-3 rounded-lg px-3 py-2 text-left',
                            'bg-carboli-50' => $step === $index,
                            'cursor-default' => $index > $step,
                        ])>
                        <span @class([
                            'flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-xs font-semibold',
                            'bg-carboli-500 text-white' => $step === $index,
                            'bg-green-100 text-green-700' => $index < $step,
                            'bg-gray-100 text-gray-500' => $index > $step,
                        ])>{{ $index < $step ? '✓' : $index }}</span>
                        <span>
                            <span @class(['block text-sm font-medium', 'text-carboli-700' => $step === $index, 'text-gray-900' => $step !== $index])>{{ $label }}</span>
                            <span class="block text-xs text-gray-500">{{ $desc }}</span>
                        </span>
                    </button>
                @endforeach
            </div>
        </aside>

        {{-- Step panel --}}
        <div class="min-w-0 flex-1">
            <x-panel.card>
                <p class="text-sm font-semibold uppercase tracking-wide text-carboli-600">Step {{ $step }} of {{ $maxStep }}</p>
                <h2 class="mt-1 text-lg font-bold text-gray-950">{{ $steps[$step][0] }}</h2>
                <p class="mb-5 text-sm text-gray-500">{{ $steps[$step][1] }}</p>

                @include('panel.cars.wizard-steps.step-' . $step)

                <div class="mt-6 flex items-center justify-between border-t border-gray-100 pt-4">
                    <span class="text-xs text-amber-600">● Your changes are not saved yet</span>
                    <div class="flex items-center gap-2">
                        @if ($step > 1)
                            <button type="button" wire:click="previousStep" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">‹ Previous</button>
                        @endif
                        <button type="button" wire:click="saveDraft" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Save draft</button>
                        @if ($step < $maxStep)
                            <button type="button" wire:click="nextStep" class="rounded-lg bg-carboli-500 px-4 py-2 text-sm font-semibold text-white hover:bg-carboli-600">Save & continue </button>
                        @else
                            <button type="button" wire:click="submit" class="rounded-lg bg-carboli-500 px-4 py-2 text-sm font-semibold text-white hover:bg-carboli-600">Review & submit </button>
                        @endif
                    </div>
                </div>
            </x-panel.card>
        </div>
    </div>
</div>
