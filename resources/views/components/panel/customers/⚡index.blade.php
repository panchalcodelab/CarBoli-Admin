<?php

use App\Models\Customer;
use App\Services\CustomerAccountService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('panel.layouts.app')] class extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public string $account_status = '';

    #[Url]
    public string $kyc_status = '';

    public ?int $confirmingBlockId = null;

    public string $confirmingBlockName = '';

    public ?int $confirmingUnblockId = null;

    public string $confirmingUnblockName = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', Customer::class);
    }

    public function updating($name): void
    {
        if (in_array($name, ['search', 'account_status', 'kyc_status'], true)) {
            $this->resetPage();
        }
    }

    public function confirmBlock(int $customerId): void
    {
        $customer = Customer::query()->findOrFail($customerId);
        $this->confirmingBlockId = $customerId;
        $this->confirmingBlockName = (string) $customer->full_name;
    }

    public function cancelBlock(): void
    {
        $this->confirmingBlockId = null;
        $this->confirmingBlockName = '';
    }

    public function block(int $customerId, CustomerAccountService $service): void
    {
        $customer = Customer::query()->findOrFail($customerId);
        Gate::authorize('block', $customer);

        try {
            $service->block($customer);
            session()->flash('status', 'Customer account blocked.');
        } catch (ValidationException $e) {
            $this->addError('action', collect($e->errors())->flatten()->first());
        } finally {
            $this->confirmingBlockId = null;
            $this->confirmingBlockName = '';
        }
    }

    public function confirmUnblock(int $customerId): void
    {
        $customer = Customer::query()->findOrFail($customerId);
        $this->confirmingUnblockId = $customerId;
        $this->confirmingUnblockName = (string) $customer->full_name;
    }

    public function cancelUnblock(): void
    {
        $this->confirmingUnblockId = null;
        $this->confirmingUnblockName = '';
    }

    public function unblock(int $customerId, CustomerAccountService $service): void
    {
        $customer = Customer::query()->findOrFail($customerId);
        Gate::authorize('unblock', $customer);

        $service->unblock($customer);
        session()->flash('status', 'Customer account unblocked.');
        $this->confirmingUnblockId = null;
        $this->confirmingUnblockName = '';
    }

    public function with(): array
    {
        $customers = Customer::query()
            ->when($this->search !== '', function ($q) {
                $term = '%'.$this->search.'%';
                $q->where(fn ($sub) => $sub
                    ->where('full_name', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhere('mobile_number', 'like', $term));
            })
            ->when($this->account_status !== '', fn ($q) => $q->where('account_status', $this->account_status))
            ->when($this->kyc_status !== '', fn ($q) => $q->where('kyc_status', $this->kyc_status))
            ->latest()
            ->paginate(10);

        return [
            'customers' => $customers,
            'accountStatuses' => Customer::ACCOUNT_STATUSES,
            'kycStatuses' => Customer::KYC_STATUSES,
            'canManage' => Gate::allows('manage customers'),
        ];
    }

    public function accountColor(string $status): string
    {
        return match ($status) {
            'active' => 'success',
            'blocked', 'suspended' => 'danger',
            'inactive' => 'gray',
            default => 'gray',
        };
    }

    public function kycColor(string $status): string
    {
        return match ($status) {
            'approved' => 'success',
            'rejected' => 'danger',
            'under_review' => 'warning',
            'pending' => 'gray',
            default => 'gray',
        };
    }
}; ?>

<div>
    @section('title', 'User Management')
    @section('breadcrumb', 'Workspace / User Management')

    <x-panel.page-header overline="Customers" title="User Management"
        description="View customer accounts, KYC status and account standing." />

    @if (session('status'))
        <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('status') }}</div>
    @endif
    @error('action') <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $message }}</div> @enderror

    <x-panel.card heading="All customers" description="Search and filter customer accounts" flush>
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 p-4">
            <div class="relative flex-1 min-w-[240px]">
                <input type="search" wire:model.live.debounce.300ms="search"
                       placeholder="Search name, email, mobile..."
                       class="w-full rounded-lg border-gray-300 pl-9 text-sm focus:border-carboli-500 focus:ring-carboli-500">
                <svg class="absolute left-3 top-2.5 h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/></svg>
            </div>
            <div class="flex gap-2">
                <select wire:model.live="account_status" class="rounded-lg border-gray-300 text-sm focus:border-carboli-500 focus:ring-carboli-500">
                    <option value="">All account statuses</option>
                    @foreach ($accountStatuses as $s)
                        <option value="{{ $s }}">{{ \Illuminate\Support\Str::of($s)->replace('_',' ')->title() }}</option>
                    @endforeach
                </select>
                <select wire:model.live="kyc_status" class="rounded-lg border-gray-300 text-sm focus:border-carboli-500 focus:ring-carboli-500">
                    <option value="">All KYC statuses</option>
                    @foreach ($kycStatuses as $s)
                        <option value="{{ $s }}">{{ \Illuminate\Support\Str::of($s)->replace('_',' ')->title() }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-100 text-sm">
                <thead>
                    <tr class="text-left text-xs font-semibold uppercase tracking-wider text-gray-400">
                        <th class="px-4 py-3">Customer</th>
                        <th class="px-4 py-3">Mobile</th>
                        <th class="px-4 py-3">Account</th>
                        <th class="px-4 py-3">KYC</th>
                        <th class="px-4 py-3">Location</th>
                        <th class="px-4 py-3">Joined</th>
                        <th class="px-4 py-3 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($customers as $customer)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3">
                                <p class="font-semibold text-gray-900">{{ $customer->full_name }}</p>
                                <p class="text-xs text-gray-500">{{ $customer->email }}</p>
                            </td>
                            <td class="px-4 py-3 text-gray-700">{{ $customer->mobile_number ?: '—' }}</td>
                            <td class="px-4 py-3"><x-panel.badge :color="$this->accountColor($customer->account_status)">{{ \Illuminate\Support\Str::of($customer->account_status)->replace('_',' ')->title() }}</x-panel.badge></td>
                            <td class="px-4 py-3"><x-panel.badge :color="$this->kycColor($customer->kyc_status)">{{ \Illuminate\Support\Str::of($customer->kyc_status)->replace('_',' ')->title() }}</x-panel.badge></td>
                            <td class="px-4 py-3 text-gray-700">{{ trim(collect([$customer->city, $customer->state])->filter()->implode(', ')) ?: '—' }}</td>
                            <td class="px-4 py-3 text-gray-700">{{ $customer->created_at?->format('d M Y') }}</td>
                            <td class="px-4 py-3 text-right">
                                <div class="inline-flex items-center gap-2">
                                    <a href="{{ route('panel.customers.show', $customer) }}" wire:navigate class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50">View</a>
                                    @if ($canManage)
                                        @if ($customer->account_status === 'active')
                                            <button wire:click="confirmBlock({{ $customer->id }})" class="rounded-lg border border-red-300 px-3 py-1.5 text-xs font-medium text-red-700 hover:bg-red-50">Block</button>
                                        @elseif ($customer->account_status === 'blocked')
                                            <button wire:click="confirmUnblock({{ $customer->id }})" class="rounded-lg border border-green-300 px-3 py-1.5 text-xs font-medium text-green-700 hover:bg-green-50">Unblock</button>
                                        @endif
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-10 text-center text-sm text-gray-500">No customers found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-gray-100 p-4">{{ $customers->links() }}</div>
    </x-panel.card>

    <x-panel.confirm-modal
        :show="$confirmingBlockId !== null"
        title="Block customer account"
        confirm-label="Block account"
        busy-label="Blocking…"
        :confirm-action="'block('.(int) $confirmingBlockId.')'"
        cancel-action="cancelBlock"
    >
        Block the customer account for
        <span class="font-semibold text-gray-700">{{ $confirmingBlockName }}</span>?
        They will lose access until the account is unblocked.
    </x-panel.confirm-modal>

    <x-panel.confirm-modal
        :show="$confirmingUnblockId !== null"
        title="Unblock customer account"
        confirm-label="Unblock account"
        busy-label="Unblocking…"
        tone="primary"
        :confirm-action="'unblock('.(int) $confirmingUnblockId.')'"
        cancel-action="cancelUnblock"
    >
        Unblock the customer account for
        <span class="font-semibold text-gray-700">{{ $confirmingUnblockName }}</span>?
        They will regain access to their account.
    </x-panel.confirm-modal>
</div>
