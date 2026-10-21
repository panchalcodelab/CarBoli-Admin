<?php

use App\Models\Auction;
use App\Models\AuctionParticipant;
use App\Models\Customer;
use App\Services\AuctionParticipantService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('panel.layouts.app')] class extends Component
{
    use WithPagination;

    #[Url]
    public string $auction_id = '';

    #[Url]
    public string $eligibility_status = '';

    #[Url]
    public string $payment_status = '';

    // Register form
    public bool $showRegister = false;
    public ?int $reg_auction_id = null;
    public ?int $reg_customer_id = null;

    public function mount(): void
    {
        Gate::authorize('viewAny', AuctionParticipant::class);
    }

    public function updating($name): void
    {
        if (in_array($name, ['auction_id', 'eligibility_status', 'payment_status'], true)) {
            $this->resetPage();
        }
    }

    public function register(AuctionParticipantService $service): void
    {
        Gate::authorize('create', AuctionParticipant::class);
        $this->validate([
            'reg_auction_id' => ['required', 'integer', 'exists:auctions,id'],
            'reg_customer_id' => ['required', 'integer', 'exists:customers,id'],
        ]);

        try {
            // Idempotency is enforced inside the service (firstOrCreate); repeated
            // submissions will not create duplicate participant rows.
            $service->register((int) $this->reg_auction_id, (int) $this->reg_customer_id);
            session()->flash('status', 'Participant registered.');
            $this->reset(['showRegister', 'reg_auction_id', 'reg_customer_id']);
        } catch (ValidationException $e) {
            $this->addError('register', collect($e->errors())->flatten()->first());
        }
    }

    public function recheck(int $participantId, AuctionParticipantService $service): void
    {
        $participant = AuctionParticipant::query()->findOrFail($participantId);
        Gate::authorize('recheckEligibility', $participant);
        $service->recheckEligibility($participant);
        session()->flash('status', 'Eligibility rechecked.');
    }

    public function with(): array
    {
        $participants = AuctionParticipant::query()
            ->with(['auction', 'customer'])
            ->when($this->auction_id !== '', fn ($q) => $q->where('auction_id', $this->auction_id))
            ->when($this->eligibility_status !== '', fn ($q) => $q->where('eligibility_status', $this->eligibility_status))
            ->when($this->payment_status !== '', fn ($q) => $q->where('payment_status', $this->payment_status))
            ->latest()
            ->paginate(10);

        return [
            'participants' => $participants,
            'auctions' => Auction::query()->orderBy('title')->get(['id', 'title']),
            'openAuctions' => Auction::query()->where('published', true)->whereIn('status', ['scheduled', 'live'])->orderBy('title')->get(['id', 'title']),
            'customers' => Customer::query()->orderBy('full_name')->get(['id', 'full_name']),
            'eligibilityStatuses' => AuctionParticipant::ELIGIBILITY_STATUSES,
            'canManage' => Gate::allows('create', AuctionParticipant::class),
        ];
    }

    public function eligibilityColor(string $status): string
    {
        return match ($status) {
            'eligible' => 'success',
            'ineligible' => 'danger',
            default => 'gray',
        };
    }
}; ?>

<div>
    @section('title', 'Participants')
    @section('breadcrumb', 'Workspace / Participants')

    <x-panel.page-header overline="Auctions" title="Participants" description="Auction registrations, eligibility and payment status.">
        <x-slot:actions>
            @if ($canManage)
                <button wire:click="$toggle('showRegister')" class="rounded-lg bg-carboli-500 px-4 py-2 text-sm font-semibold text-white hover:bg-carboli-600">Register participant</button>
            @endif
        </x-slot:actions>
    </x-panel.page-header>

    @if (session('status'))
        <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('status') }}</div>
    @endif
    @error('register') <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $message }}</div> @enderror

    @if ($showRegister && $canManage)
        <x-panel.card heading="Register a participant" class="mb-4">
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Auction</label>
                    <select wire:model="reg_auction_id" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                        <option value="">Select open auction</option>
                        @foreach ($openAuctions as $a)
                            <option value="{{ $a->id }}">{{ $a->title }}</option>
                        @endforeach
                    </select>
                    @error('reg_auction_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Customer</label>
                    <select wire:model="reg_customer_id" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                        <option value="">Select customer</option>
                        @foreach ($customers as $c)
                            <option value="{{ $c->id }}">{{ $c->full_name }}</option>
                        @endforeach
                    </select>
                    @error('reg_customer_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>
            <div class="mt-4 flex justify-end gap-2">
                <button wire:click="$set('showRegister', false)" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
                <button wire:click="register" class="rounded-lg bg-carboli-500 px-4 py-2 text-sm font-semibold text-white hover:bg-carboli-600">Register</button>
            </div>
        </x-panel.card>
    @endif

    <x-panel.card heading="All participants" description="Filter by auction, eligibility and payment" flush>
        <div class="flex flex-wrap items-center gap-3 border-b border-gray-100 p-4">
            <select wire:model.live="auction_id" class="rounded-lg border-gray-300 text-sm">
                <option value="">All auctions</option>
                @foreach ($auctions as $a)
                    <option value="{{ $a->id }}">{{ $a->title }}</option>
                @endforeach
            </select>
            <select wire:model.live="eligibility_status" class="rounded-lg border-gray-300 text-sm">
                <option value="">All eligibility</option>
                @foreach ($eligibilityStatuses as $s)
                    <option value="{{ $s }}">{{ ucfirst($s) }}</option>
                @endforeach
            </select>
            <select wire:model.live="payment_status" class="rounded-lg border-gray-300 text-sm">
                <option value="">All payment statuses</option>
                @foreach (['pending' => 'Pending', 'captured' => 'Captured', 'not_required' => 'Not required'] as $v => $l)
                    <option value="{{ $v }}">{{ $l }}</option>
                @endforeach
            </select>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-100 text-sm">
                <thead>
                    <tr class="text-left text-xs font-semibold uppercase tracking-wider text-gray-400">
                        <th class="px-4 py-3">Auction</th>
                        <th class="px-4 py-3">Customer</th>
                        <th class="px-4 py-3">Registration</th>
                        <th class="px-4 py-3">KYC</th>
                        <th class="px-4 py-3">Payment</th>
                        <th class="px-4 py-3">Eligibility</th>
                        <th class="px-4 py-3 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($participants as $p)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-gray-900">{{ $p->auction?->title ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-900">{{ $p->customer?->full_name ?? '—' }}</td>
                            <td class="px-4 py-3"><x-panel.badge>{{ \Illuminate\Support\Str::of($p->registration_status)->replace('_',' ')->title() }}</x-panel.badge></td>
                            <td class="px-4 py-3"><x-panel.badge :color="$p->kyc_status === 'approved' ? 'success' : 'gray'">{{ \Illuminate\Support\Str::of($p->kyc_status)->replace('_',' ')->title() }}</x-panel.badge></td>
                            <td class="px-4 py-3"><x-panel.badge :color="$p->payment_status === 'captured' ? 'success' : 'gray'">{{ \Illuminate\Support\Str::of($p->payment_status)->replace('_',' ')->title() }}</x-panel.badge></td>
                            <td class="px-4 py-3"><x-panel.badge :color="$this->eligibilityColor($p->eligibility_status)">{{ ucfirst($p->eligibility_status) }}</x-panel.badge></td>
                            <td class="px-4 py-3 text-right">
                                @if ($canManage)
                                    <button wire:click="recheck({{ $p->id }})" class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50">Recheck</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-10 text-center text-sm text-gray-500">No participants found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-gray-100 p-4">{{ $participants->links() }}</div>
    </x-panel.card>
</div>
