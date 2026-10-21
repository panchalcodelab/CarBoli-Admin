<?php

use App\Models\Customer;
use App\Services\CustomerAccountService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('panel.layouts.app')] class extends Component
{
    public int $customerId;

    /** Which action is awaiting confirmation: block|unblock|null. */
    public ?string $confirming = null;

    public function mount(int $customer): void
    {
        $record = Customer::query()->findOrFail($customer);
        Gate::authorize('view', $record);
        $this->customerId = $record->id;
    }

    public function customer(): Customer
    {
        return Customer::query()->with(['kyc.reviewer'])->findOrFail($this->customerId);
    }

    public function confirmAction(string $action): void
    {
        $this->confirming = in_array($action, ['block', 'unblock'], true) ? $action : null;
    }

    public function cancelConfirm(): void
    {
        $this->confirming = null;
    }

    public function block(CustomerAccountService $service): void
    {
        $customer = $this->customer();
        Gate::authorize('block', $customer);
        try {
            $service->block($customer);
            session()->flash('status', 'Customer account blocked.');
        } catch (ValidationException $e) {
            $this->addError('action', collect($e->errors())->flatten()->first());
        } finally {
            $this->confirming = null;
        }
    }

    public function unblock(CustomerAccountService $service): void
    {
        $customer = $this->customer();
        Gate::authorize('unblock', $customer);
        $service->unblock($customer);
        session()->flash('status', 'Customer account unblocked.');
        $this->confirming = null;
    }
}; ?>

<div>
    @php($customer = $this->customer())
    @php($canViewKyc = Gate::allows('view customer kyc'))
    @section('title', $customer->full_name)
    @section('breadcrumb', 'Workspace / User Management / ' . $customer->full_name)

    <a href="{{ route('panel.customers.index') }}" wire:navigate class="mb-2 inline-flex items-center gap-1 text-sm text-gray-500 hover:text-carboli-600">← Back to User Management</a>

    <x-panel.page-header :title="$customer->full_name" :description="$customer->email">
        <x-slot:actions>
            @can('manage customers')
                @if ($customer->account_status === 'active')
                    <button wire:click="confirmAction('block')" class="rounded-lg border border-red-300 px-4 py-2 text-sm font-medium text-red-700 hover:bg-red-50">Block account</button>
                @elseif ($customer->account_status === 'blocked')
                    <button wire:click="confirmAction('unblock')" class="rounded-lg border border-green-300 px-4 py-2 text-sm font-medium text-green-700 hover:bg-green-50">Unblock account</button>
                @endif
            @endcan
        </x-slot:actions>
    </x-panel.page-header>

    @if (session('status'))
        <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('status') }}</div>
    @endif
    @error('action') <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $message }}</div> @enderror

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-panel.card heading="Customer profile">
                <dl class="grid grid-cols-2 gap-4 text-sm">
                    @foreach ([
                        'Full name' => $customer->full_name,
                        'Email' => $customer->email,
                        'Mobile number' => $customer->mobile_number,
                        'Last login' => $customer->last_login_at?->format('d M Y H:i') ?? 'Never',
                        'Address' => $customer->address,
                        'City' => $customer->city,
                        'State' => $customer->state,
                        'Pincode' => $customer->pincode,
                        'Country' => $customer->country,
                    ] as $k => $v)
                        <div>
                            <dt class="text-xs uppercase tracking-wide text-gray-400">{{ $k }}</dt>
                            <dd class="mt-0.5 text-gray-900">{{ $v !== null && $v !== '' ? $v : '—' }}</dd>
                        </div>
                    @endforeach
                </dl>
            </x-panel.card>

            @if ($canViewKyc)
                <x-panel.card heading="KYC records" description="Identity verification history">
                    @forelse ($customer->kyc as $kyc)
                        <div class="flex items-center justify-between gap-4 border-b border-gray-100 py-3 last:border-0">
                            <div>
                                <p class="text-sm font-semibold text-gray-900">{{ \Illuminate\Support\Str::of($kyc->status)->replace('_',' ')->title() }}</p>
                                <p class="text-xs text-gray-500">Reviewer: {{ $kyc->reviewer?->name ?? '—' }} · {{ $kyc->reviewed_at?->format('d M Y') ?? 'Not reviewed' }}</p>
                            </div>
                            <a href="{{ route('panel.kyc.show', $kyc) }}" wire:navigate class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50">Review</a>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500">No KYC records submitted.</p>
                    @endforelse
                </x-panel.card>
            @endif
        </div>

        <div class="space-y-6">
            <x-panel.card heading="Account & verification">
                <dl class="space-y-3 text-sm">
                    <div class="flex items-center justify-between">
                        <dt class="text-gray-500">Account status</dt>
                        <dd><x-panel.badge :color="$customer->account_status === 'active' ? 'success' : ($customer->account_status === 'blocked' ? 'danger' : 'gray')">{{ \Illuminate\Support\Str::of($customer->account_status)->replace('_',' ')->title() }}</x-panel.badge></dd>
                    </div>
                    <div class="flex items-center justify-between">
                        <dt class="text-gray-500">Blocked</dt>
                        <dd><x-panel.badge :color="$customer->is_blocked ? 'danger' : 'success'">{{ $customer->is_blocked ? 'Blocked' : 'Not blocked' }}</x-panel.badge></dd>
                    </div>
                    <div class="flex items-center justify-between">
                        <dt class="text-gray-500">KYC status</dt>
                        <dd><x-panel.badge :color="$customer->kyc_status === 'approved' ? 'success' : ($customer->kyc_status === 'rejected' ? 'danger' : ($customer->kyc_status === 'under_review' ? 'warning' : 'gray'))">{{ \Illuminate\Support\Str::of($customer->kyc_status)->replace('_',' ')->title() }}</x-panel.badge></dd>
                    </div>
                    @if ($canViewKyc)
                        <div><dt class="text-gray-500">KYC submitted</dt><dd class="text-gray-900">{{ $customer->kyc_submitted_at?->format('d M Y') ?? 'Not submitted' }}</dd></div>
                        <div><dt class="text-gray-500">KYC approved</dt><dd class="text-gray-900">{{ $customer->kyc_approved_at?->format('d M Y') ?? 'Not approved' }}</dd></div>
                        @if ($customer->kyc_rejection_reason)
                            <div><dt class="text-gray-500">Rejection reason</dt><dd class="text-gray-900">{{ $customer->kyc_rejection_reason }}</dd></div>
                        @endif
                    @endif
                    <div><dt class="text-gray-500">Joined</dt><dd class="text-gray-900">{{ $customer->created_at?->format('d M Y') }}</dd></div>
                </dl>
            </x-panel.card>
        </div>
    </div>

    <x-panel.confirm-modal
        :show="$confirming === 'block'"
        title="Block customer account"
        confirm-label="Block account"
        busy-label="Blocking…"
        confirm-action="block"
        cancel-action="cancelConfirm"
    >
        Block the customer account for
        <span class="font-semibold text-gray-700">{{ $customer->full_name }}</span>?
        They will lose access until the account is unblocked.
    </x-panel.confirm-modal>

    <x-panel.confirm-modal
        :show="$confirming === 'unblock'"
        title="Unblock customer account"
        confirm-label="Unblock account"
        busy-label="Unblocking…"
        tone="primary"
        confirm-action="unblock"
        cancel-action="cancelConfirm"
    >
        Unblock the customer account for
        <span class="font-semibold text-gray-700">{{ $customer->full_name }}</span>?
        They will regain access to their account.
    </x-panel.confirm-modal>
</div>
