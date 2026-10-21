<?php

use App\Models\Car;
use App\Services\CarApprovalService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('panel.layouts.app')] class extends Component
{
    public int $carId;
    public string $rejectReason = '';

    public function mount(int $car): void
    {
        $record = Car::query()->findOrFail($car);
        Gate::authorize('view', $record);
        $this->carId = $record->id;
    }

    public function car(): Car
    {
        return Car::query()
            ->with(['location', 'seller.customer', 'category', 'makeRef', 'images', 'documents', 'inspections', 'assignedManager', 'approvedBy'])
            ->findOrFail($this->carId);
    }

    public function approveDocuments(CarApprovalService $service): void
    {
        $service->approveDocuments($this->car());
        session()->flash('status', 'Documents approved.');
    }

    public function approve(CarApprovalService $service): void
    {
        try {
            $service->approve($this->car());
            session()->flash('status', 'Car approved and available.');
        } catch (ValidationException $e) {
            $this->addError('approve', collect($e->errors())->flatten()->first());
        }
    }

    public function reject(CarApprovalService $service): void
    {
        $this->validate(['rejectReason' => ['required', 'string', 'max:2000']]);
        $service->reject($this->car(), $this->rejectReason);
        $this->rejectReason = '';
        session()->flash('status', 'Car rejected and returned to draft.');
    }

    public function submitForReview(CarApprovalService $service): void
    {
        $service->submitForReview($this->car());
        session()->flash('status', 'Car submitted for review.');
    }
}; ?>

<div>
    @php($car = $this->car())
    @section('title', trim($car->make.' '.$car->model))
    @section('breadcrumb', 'Workspace / Car Management / ' . trim($car->make.' '.$car->model))

    <a href="{{ route('panel.cars.index') }}" wire:navigate class="mb-2 inline-flex items-center gap-1 text-sm text-gray-500 hover:text-carboli-600">← Back to Car Management</a>

    <x-panel.page-header :title="trim($car->make.' '.$car->model)" :description="$car->stock_inventory_id">
        <x-slot:actions>
            @can('update', $car)
                <a href="{{ route('panel.cars.edit', $car) }}" wire:navigate class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Edit</a>
            @endcan
            <x-panel.badge :color="$car->status === 'available' ? 'success' : ($car->status === 'under_review' ? 'warning' : 'gray')">
                {{ \Illuminate\Support\Str::of($car->status)->replace('_',' ')->title() }}
            </x-panel.badge>
        </x-slot:actions>
    </x-panel.page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-panel.card heading="Vehicle">
                <dl class="grid grid-cols-2 gap-4 text-sm">
                    @foreach ([
                        'Registration' => $car->registration_number,
                        'Make' => $car->make,
                        'Model' => $car->model,
                        'Variant' => $car->variant,
                        'Category' => $car->category?->name,
                        'Year' => $car->manufacturing_year,
                        'Fuel' => $car->fuel_type ? \Illuminate\Support\Str::title($car->fuel_type) : null,
                        'Transmission' => $car->transmission ? \Illuminate\Support\Str::title($car->transmission) : null,
                        'Engine (cc)' => $car->engine_capacity_cc,
                        'Seating' => $car->seating_capacity,
                        'Kilometers' => $car->kilometer,
                        'Colour' => $car->color,
                        'VIN' => $car->vin,
                        'Location' => $car->location?->name,
                    ] as $k => $v)
                        <div>
                            <dt class="text-xs uppercase tracking-wide text-gray-400">{{ $k }}</dt>
                            <dd class="mt-0.5 text-gray-900">{{ $v !== null && $v !== '' ? $v : '—' }}</dd>
                        </div>
                    @endforeach
                </dl>
            </x-panel.card>

            <x-panel.card heading="Photos">
                @if ($car->images->isEmpty())
                    <p class="text-sm text-gray-500">No images uploaded.</p>
                @else
                    <div class="grid grid-cols-3 gap-3">
                        @foreach ($car->images as $image)
                            <div class="rounded-lg border border-gray-200 p-2 text-center">
                                <p class="truncate text-xs text-gray-500">{{ \Illuminate\Support\Str::of($image->slot ?? 'additional')->replace('_',' ')->title() }}</p>
                                @if ($image->is_primary)<span class="text-[10px] font-semibold text-carboli-600">Primary</span>@endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-panel.card>

            <x-panel.card heading="Documents">
                @if ($car->documents->isEmpty())
                    <p class="text-sm text-gray-500">No documents uploaded.</p>
                @else
                    <ul class="divide-y divide-gray-100 text-sm">
                        @foreach ($car->documents as $doc)
                            <li class="flex items-center justify-between py-2">
                                <span class="text-gray-900">{{ \Illuminate\Support\Str::of($doc->type)->replace('_',' ')->title() }}</span>
                                <span class="text-xs text-gray-500">{{ $doc->file_name }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-panel.card>
        </div>

        <div class="space-y-6">
            <x-panel.card heading="Seller">
                @if ($car->seller)
                    <dl class="space-y-2 text-sm">
                        <div><dt class="text-xs uppercase text-gray-400">Name</dt><dd class="text-gray-900">{{ $car->seller->name }}</dd></div>
                        <div><dt class="text-xs uppercase text-gray-400">Type</dt><dd class="text-gray-900">{{ ucfirst($car->seller->seller_type) }}</dd></div>
                        <div><dt class="text-xs uppercase text-gray-400">Mobile</dt><dd class="text-gray-900">{{ $car->seller->mobile }}</dd></div>
                        <div><dt class="text-xs uppercase text-gray-400">Email</dt><dd class="text-gray-900">{{ $car->seller->email ?: '—' }}</dd></div>
                        <div><dt class="text-xs uppercase text-gray-400">Linked customer</dt><dd class="text-gray-900">{{ $car->seller->customer?->full_name ?? 'Not linked' }}</dd></div>
                    </dl>
                @else
                    <p class="text-sm text-gray-500">No seller recorded.</p>
                @endif
            </x-panel.card>

            @can('approve', $car)
                <x-panel.card heading="Approval">
                    @if (session('status'))
                        <p class="mb-3 rounded-lg bg-green-50 px-3 py-2 text-xs text-green-700">{{ session('status') }}</p>
                    @endif
                    <p class="mb-3 text-sm text-gray-600">Status: <strong>{{ \Illuminate\Support\Str::of($car->status)->replace('_',' ')->title() }}</strong></p>

                    @if ($car->status === 'draft')
                        <button wire:click="submitForReview" class="w-full rounded-lg bg-carboli-500 px-4 py-2 text-sm font-semibold text-white hover:bg-carboli-600">Submit for review</button>
                    @elseif ($car->status === 'under_review')
                        <div class="space-y-3">
                            <div class="flex items-center justify-between text-sm">
                                <span>Documents approved</span>
                                @if ($car->documents_approved_at)
                                    <x-panel.badge color="success">Yes</x-panel.badge>
                                @else
                                    <button wire:click="approveDocuments" class="rounded-lg border border-gray-300 px-3 py-1 text-xs font-medium hover:bg-gray-50">Approve documents</button>
                                @endif
                            </div>
                            <div class="flex items-center justify-between text-sm">
                                <span>Inspection completed</span>
                                <x-panel.badge :color="$car->inspections->firstWhere('status','completed') ? 'success' : 'gray'">
                                    {{ $car->inspections->firstWhere('status','completed') ? 'Yes' : 'Pending' }}
                                </x-panel.badge>
                            </div>
                            <button wire:click="approve" class="w-full rounded-lg bg-carboli-500 px-4 py-2 text-sm font-semibold text-white hover:bg-carboli-600">Approve car</button>
                            @error('approve') <p class="text-xs text-red-600">{{ $message }}</p> @enderror

                            <div class="border-t border-gray-100 pt-3">
                                <textarea wire:model="rejectReason" rows="2" placeholder="Rejection reason" class="w-full rounded-lg border-gray-300 text-sm focus:border-carboli-500 focus:ring-carboli-500"></textarea>
                                @error('rejectReason') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                                <button wire:click="reject" class="mt-2 w-full rounded-lg border border-red-300 px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-50">Reject</button>
                            </div>
                        </div>
                    @else
                        <p class="text-sm text-gray-500">No pending approval actions.</p>
                    @endif
                </x-panel.card>
            @endcan
        </div>
    </div>
</div>
