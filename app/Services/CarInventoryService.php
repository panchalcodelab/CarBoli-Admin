<?php

namespace App\Services;

use App\Models\Auction;
use App\Models\Car;
use App\Models\CarSeller;
use App\Models\Inspection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CarInventoryService
{
    public function __construct(private AuditLogService $auditLogService) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Car
    {
        Gate::authorize('create', Car::class);

        $validated = $this->validate($data);

        return DB::transaction(fn (): Car => Car::query()->create($validated));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Car $car, array $data): Car
    {
        Gate::authorize('update', $car);

        $validated = $this->validate($data, $car);

        return DB::transaction(function () use ($car, $validated): Car {
            $car->update($validated);

            return $car->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function validate(array $data, ?Car $car = null): array
    {
        return Validator::make($data, [
            'location_id' => ['nullable', 'integer', Rule::exists('locations', 'id')],
            'stock_inventory_id' => [
                'required',
                'string',
                'max:255',
                Rule::unique('cars', 'stock_inventory_id')->ignore($car),
            ],
            'make' => ['required', 'string', 'max:255'],
            'model' => ['required', 'string', 'max:255'],
            'variant' => ['nullable', 'string', 'max:255'],
            'manufacturing_year' => ['nullable', 'integer', 'between:1900,'.(now()->year + 1)],
            'registration_year' => ['nullable', 'integer', 'between:1900,'.(now()->year + 1)],
            'fuel_type' => ['nullable', 'string', 'max:100'],
            'transmission' => ['nullable', 'string', 'max:100'],
            'body_type' => ['nullable', 'string', 'max:100'],
            'color' => ['nullable', 'string', 'max:100'],
            'kilometer' => ['nullable', 'numeric', 'min:0'],
            'number_of_owners' => ['nullable', 'integer', 'between:0,20'],
            'registration_number' => ['nullable', 'string', 'max:255'],
            'vin' => ['nullable', 'string', 'max:255'],
            'engine_number' => ['nullable', 'string', 'max:255'],
            'expected_price' => ['nullable', 'numeric', 'min:0'],
            'reserve_price' => ['nullable', 'numeric', 'min:0'],
            'starting_auction_price' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', Rule::in(Car::STATUSES)],
            'short_description' => ['nullable', 'string', 'max:1000'],
            'detailed_description' => ['nullable', 'string'],
            'specifications' => ['nullable', 'string'],
        ])->validate();
    }

    /**
     * Persist the full six-step wizard payload for a new or existing car:
     * car fields + seller + a draft inspection + a draft auction, atomically.
     * Media (images/documents/video) is handled separately by CarMediaService
     * once the car exists.
     *
     * @param  array<string, mixed>  $data
     */
    public function saveWizard(array $data, ?Car $car = null): Car
    {
        Gate::authorize($car ? 'update' : 'create', $car ?? Car::class);

        $validated = $this->validateWizard($data, $car);

        return DB::transaction(function () use ($validated, $car): Car {
            $carAttributes = $validated['car'];
            // A wizard-created car always starts as a draft; status transitions are
            // controlled by CarApprovalService, never set directly from the form.
            if ($car === null) {
                $carAttributes['status'] = 'draft';
                $car = Car::query()->create($carAttributes);
                $this->auditLogService->record('cars', 'car.created', $car, null, [
                    'stock_inventory_id' => $car->stock_inventory_id,
                ]);
            } else {
                $lockedCar = Car::query()->lockForUpdate()->findOrFail($car->getKey());
                unset($carAttributes['status']); // never change status through the wizard
                $lockedCar->update($carAttributes);
                $car = $lockedCar->refresh();
            }

            // Seller (one-to-one) — upsert.
            $car->seller()->updateOrCreate(
                ['car_id' => $car->getKey()],
                $validated['seller'],
            );

            // Inspection (draft) — reuse existing lifecycle; attach/update one row.
            if ($validated['inspection'] !== null) {
                $inspection = Inspection::query()
                    ->where('car_id', $car->getKey())
                    ->latest('id')
                    ->first();

                $inspectionData = $validated['inspection'] + ['car_id' => $car->getKey()];

                if ($inspection) {
                    $inspection->update($inspectionData);
                } else {
                    Inspection::query()->create($inspectionData);
                }
            }

            // Draft auction configuration — never published here.
            if ($validated['auction'] !== null) {
                $auction = Auction::query()
                    ->where('car_id', $car->getKey())
                    ->where('status', 'draft')
                    ->where('published', false)
                    ->latest('id')
                    ->first();

                $auctionData = $validated['auction'] + [
                    'car_id' => $car->getKey(),
                    'title' => $validated['auction']['title'] ?? trim($car->make.' '.$car->model.' auction'),
                    'status' => 'draft',
                    'published' => false,
                ];

                if ($auction) {
                    $auction->update($auctionData);
                } else {
                    $auctionData['created_by'] = Auth::id();
                    $auctionData['participation_fee'] ??= 0;
                    Auction::query()->create($auctionData);
                }
            }

            return $car->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{car: array<string, mixed>, seller: array<string, mixed>, inspection: ?array<string, mixed>, auction: ?array<string, mixed>}
     */
    public function validateWizard(array $data, ?Car $car = null): array
    {
        $currentYear = now()->year + 1;

        $validated = Validator::make($data, [
            // Step 1 — vehicle details
            'category_id' => ['nullable', 'integer', Rule::exists('vehicle_categories', 'id')],
            'make_id' => ['nullable', 'integer', Rule::exists('makes', 'id')],
            'stock_inventory_id' => ['required', 'string', 'max:255', Rule::unique('cars', 'stock_inventory_id')->ignore($car)],
            'make' => ['required', 'string', 'max:255'],
            'model' => ['required', 'string', 'max:255'],
            'variant' => ['nullable', 'string', 'max:255'],
            'manufacturing_year' => ['required', 'integer', 'between:1900,'.$currentYear],
            'registration_date' => ['nullable', 'date'],
            'number_of_owners' => ['nullable', 'integer', 'between:0,20'],
            'fuel_type' => ['required', 'string', 'max:100'],
            'transmission' => ['required', 'string', 'max:100'],
            'engine_capacity_cc' => ['nullable', 'integer', 'between:0,20000'],
            'kilometer' => ['required', 'numeric', 'min:0'],
            'color' => ['required', 'string', 'max:100'],
            'seating_capacity' => ['nullable', 'integer', 'between:1,50'],
            'vin' => ['nullable', 'string', 'max:255'],
            'engine_number' => ['nullable', 'string', 'max:255'],
            'registration_number' => ['required', 'string', 'max:255'],
            'detailed_description' => ['nullable', 'string'],

            // Step 2 — owner & seller
            'location_id' => ['required', 'integer', Rule::exists('locations', 'id')],
            'assigned_manager_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'seller_type' => ['required', Rule::in(CarSeller::SELLER_TYPES)],
            'seller_name' => ['required', 'string', 'max:255'],
            'seller_mobile' => ['required', 'string', 'max:20'],
            'seller_email' => ['nullable', 'email', 'max:255'],
            'seller_external_reference' => ['nullable', 'string', 'max:255'],
            'seller_customer_id' => ['nullable', 'integer', Rule::exists('customers', 'id')],
            'seller_address' => ['required', 'string', 'max:500'],
            'seller_city' => ['required', 'string', 'max:255'],
            'seller_state' => ['required', 'string', 'max:255'],
            'seller_pincode' => ['required', 'string', 'max:10'],

            // Step 5 — inspection & condition
            'inspection_status' => ['nullable', Rule::in(Inspection::STATUSES)],
            'assigned_inspector_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'exterior_condition' => ['nullable', Rule::in(Inspection::CONDITION_RATINGS)],
            'interior_condition' => ['nullable', Rule::in(Inspection::CONDITION_RATINGS)],
            'engine_condition' => ['nullable', Rule::in(Inspection::CONDITION_RATINGS)],
            'transmission_condition' => ['nullable', Rule::in(Inspection::CONDITION_RATINGS)],
            'tyre_condition' => ['nullable', Rule::in(Inspection::CONDITION_RATINGS)],
            'battery_condition' => ['nullable', Rule::in(Inspection::CONDITION_RATINGS)],
            'accident_history' => ['nullable', Rule::in(Inspection::ACCIDENT_HISTORY)],
            'flood_damage' => ['nullable', Rule::in(Inspection::FLOOD_DAMAGE)],
            'inspection_score' => ['nullable', 'integer', 'between:0,100'],
            'inspector_remarks' => ['nullable', 'string', 'max:5000'],

            // Step 6 — auction configuration (draft)
            'expected_price' => ['nullable', 'numeric', 'min:0'],
            'reserve_price' => ['nullable', 'numeric', 'min:0'],
            'starting_price' => ['nullable', 'numeric', 'min:0'],
            'minimum_bid_increment' => ['nullable', 'numeric', 'gt:0'],
            'auction_type' => ['nullable', Rule::in(Auction::AUCTION_TYPES)],
            'auction_start_at' => ['nullable', 'date'],
            'auction_end_at' => ['nullable', 'date', 'after:auction_start_at'],
            'bidder_eligibility' => ['nullable', Rule::in(Auction::BIDDER_ELIGIBILITY)],
            'listing_visibility' => ['nullable', Rule::in(Auction::LISTING_VISIBILITY)],
            'buyer_premium_percent' => ['nullable', 'numeric', 'between:0,100'],
            'seller_commission_percent' => ['nullable', 'numeric', 'between:0,100'],
            'payment_deadline_days' => ['nullable', 'integer', 'between:0,365'],
        ])->validate();

        return [
            'car' => [
                'category_id' => $validated['category_id'] ?? null,
                'make_id' => $validated['make_id'] ?? null,
                'stock_inventory_id' => $validated['stock_inventory_id'],
                'make' => $validated['make'],
                'model' => $validated['model'],
                'variant' => $validated['variant'] ?? null,
                'manufacturing_year' => $validated['manufacturing_year'],
                'registration_date' => $validated['registration_date'] ?? null,
                'number_of_owners' => $validated['number_of_owners'] ?? null,
                'fuel_type' => $validated['fuel_type'],
                'transmission' => $validated['transmission'],
                'engine_capacity_cc' => $validated['engine_capacity_cc'] ?? null,
                'kilometer' => $validated['kilometer'],
                'color' => $validated['color'],
                'seating_capacity' => $validated['seating_capacity'] ?? null,
                'vin' => $validated['vin'] ?? null,
                'engine_number' => $validated['engine_number'] ?? null,
                'registration_number' => $validated['registration_number'],
                'detailed_description' => $validated['detailed_description'] ?? null,
                'location_id' => $validated['location_id'],
                'assigned_manager_id' => $validated['assigned_manager_id'] ?? null,
                'expected_price' => $validated['expected_price'] ?? null,
                'reserve_price' => $validated['reserve_price'] ?? null,
                'starting_auction_price' => $validated['starting_price'] ?? null,
            ],
            'seller' => [
                'customer_id' => $validated['seller_customer_id'] ?? null,
                'seller_type' => $validated['seller_type'],
                'name' => $validated['seller_name'],
                'mobile' => $validated['seller_mobile'],
                'email' => $validated['seller_email'] ?? null,
                'external_reference' => $validated['seller_external_reference'] ?? null,
                'address' => $validated['seller_address'],
                'city' => $validated['seller_city'],
                'state' => $validated['seller_state'],
                'pincode' => $validated['seller_pincode'],
            ],
            'inspection' => isset($validated['inspection_status']) || isset($validated['exterior_condition']) ? [
                'status' => $validated['inspection_status'] ?? 'requested',
                'assigned_staff_id' => $validated['assigned_inspector_id'] ?? null,
                'exterior_condition' => $validated['exterior_condition'] ?? null,
                'interior_condition' => $validated['interior_condition'] ?? null,
                'engine_condition' => $validated['engine_condition'] ?? null,
                'transmission_condition' => $validated['transmission_condition'] ?? null,
                'tyre_condition' => $validated['tyre_condition'] ?? null,
                'battery_condition' => $validated['battery_condition'] ?? null,
                'accident_history' => $validated['accident_history'] ?? null,
                'flood_damage' => $validated['flood_damage'] ?? null,
                'inspection_score' => $validated['inspection_score'] ?? null,
                'inspector_remarks' => $validated['inspector_remarks'] ?? null,
            ] : null,
            'auction' => isset($validated['starting_price']) || isset($validated['reserve_price']) ? [
                'reserve_price' => $validated['reserve_price'] ?? null,
                'starting_price' => $validated['starting_price'] ?? 0,
                'minimum_bid_increment' => $validated['minimum_bid_increment'] ?? 0,
                'auction_type' => $validated['auction_type'] ?? null,
                'start_at' => $validated['auction_start_at'] ?? null,
                'end_at' => $validated['auction_end_at'] ?? null,
                'bidder_eligibility' => $validated['bidder_eligibility'] ?? null,
                'listing_visibility' => $validated['listing_visibility'] ?? null,
                'buyer_premium_percent' => $validated['buyer_premium_percent'] ?? null,
                'seller_commission_percent' => $validated['seller_commission_percent'] ?? null,
                'payment_deadline_days' => $validated['payment_deadline_days'] ?? null,
            ] : null,
        ];
    }
}
