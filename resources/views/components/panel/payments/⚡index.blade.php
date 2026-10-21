<?php

use App\Models\Auction;
use App\Models\Payment;
use Illuminate\Support\Facades\Gate;
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
    public string $status = '';

    #[Url]
    public string $payment_type = '';

    #[Url]
    public string $auction_id = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', Payment::class);
    }

    public function updating($name): void
    {
        if (in_array($name, ['search', 'status', 'payment_type', 'auction_id'], true)) {
            $this->resetPage();
        }
    }

    public function with(): array
    {
        $payments = Payment::query()
            ->with(['auction', 'customer'])
            ->when($this->search !== '', function ($q) {
                $term = '%'.$this->search.'%';
                $q->where('gateway_reference_id', 'like', $term)
                    ->orWhereHas('customer', fn ($c) => $c->where('full_name', 'like', $term)->orWhere('email', 'like', $term));
            })
            ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
            ->when($this->payment_type !== '', fn ($q) => $q->where('payment_type', $this->payment_type))
            ->when($this->auction_id !== '', fn ($q) => $q->where('auction_id', $this->auction_id))
            ->latest()
            ->paginate(15);

        return [
            'payments' => $payments,
            'statuses' => Payment::STATUSES,
            'types' => Payment::TYPES,
            'auctions' => Auction::query()->orderBy('title')->get(['id', 'title']),
            'capturedTotal' => (float) Payment::query()->where('status', 'captured')->sum('amount'),
        ];
    }

    public function statusColor(string $status): string
    {
        return match ($status) {
            'captured' => 'success',
            'failed' => 'danger',
            'released', 'refunded' => 'info',
            'pending' => 'warning',
            default => 'gray',
        };
    }
}; ?>

<div>
    @section('title', 'Payments & Settlements')
    @section('breadcrumb', 'Workspace / Payments & Settlements')

    <x-panel.page-header overline="Finance" title="Payments & Settlements"
        description="Read-only record of entry-fee and winning-bid payments and their settlement status." />

    <div class="mb-6 grid gap-4 sm:grid-cols-3">
        <x-panel.stat-card label="Captured total" :value="'₹'.number_format($capturedTotal)">
            <x-slot:icon><x-panel.icon name="credit-card" class="h-5 w-5" /></x-slot:icon>
        </x-panel.stat-card>
        <x-panel.stat-card label="Total records" :value="number_format($payments->total())">
            <x-slot:icon><x-panel.icon name="document" class="h-5 w-5" /></x-slot:icon>
        </x-panel.stat-card>
        <x-panel.stat-card label="Pending" :value="number_format(\App\Models\Payment::query()->where('status','pending')->count())">
            <x-slot:icon><x-panel.icon name="clipboard" class="h-5 w-5" /></x-slot:icon>
        </x-panel.stat-card>
    </div>

    <x-panel.card heading="All payments" description="Search and filter transactions" flush>
        <div class="flex flex-wrap items-center gap-3 border-b border-gray-100 p-4">
            <div class="relative flex-1 min-w-[220px]">
                <input type="search" wire:model.live.debounce.300ms="search" placeholder="Search customer or gateway reference..."
                       class="w-full rounded-lg border-gray-300 pl-9 text-sm focus:border-carboli-500 focus:ring-carboli-500">
                <svg class="absolute left-3 top-2.5 h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/></svg>
            </div>
            <select wire:model.live="status" class="rounded-lg border-gray-300 text-sm">
                <option value="">All statuses</option>
                @foreach ($statuses as $s)<option value="{{ $s }}">{{ \Illuminate\Support\Str::of($s)->replace('_',' ')->title() }}</option>@endforeach
            </select>
            <select wire:model.live="payment_type" class="rounded-lg border-gray-300 text-sm">
                <option value="">All types</option>
                @foreach ($types as $t)<option value="{{ $t }}">{{ \Illuminate\Support\Str::of($t)->replace('_',' ')->title() }}</option>@endforeach
            </select>
            <select wire:model.live="auction_id" class="rounded-lg border-gray-300 text-sm">
                <option value="">All auctions</option>
                @foreach ($auctions as $a)<option value="{{ $a->id }}">{{ $a->title }}</option>@endforeach
            </select>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-100 text-sm">
                <thead>
                    <tr class="text-left text-xs font-semibold uppercase tracking-wider text-gray-400">
                        <th class="px-4 py-3">#</th>
                        <th class="px-4 py-3">Customer</th>
                        <th class="px-4 py-3">Auction</th>
                        <th class="px-4 py-3">Type</th>
                        <th class="px-4 py-3">Amount</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Captured</th>
                        <th class="px-4 py-3 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($payments as $payment)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-gray-500">#{{ $payment->id }}</td>
                            <td class="px-4 py-3 text-gray-900">{{ $payment->customer?->full_name ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-700">{{ $payment->auction?->title ?? '—' }}</td>
                            <td class="px-4 py-3"><x-panel.badge>{{ \Illuminate\Support\Str::of($payment->payment_type)->replace('_',' ')->title() }}</x-panel.badge></td>
                            <td class="px-4 py-3 font-semibold text-gray-900">{{ number_format((float) $payment->amount, 2) }} {{ $payment->currency }}</td>
                            <td class="px-4 py-3"><x-panel.badge :color="$this->statusColor($payment->status)">{{ \Illuminate\Support\Str::of($payment->status)->replace('_',' ')->title() }}</x-panel.badge></td>
                            <td class="px-4 py-3 text-gray-700">{{ $payment->captured_at?->format('d M Y') ?? 'Not captured' }}</td>
                            <td class="px-4 py-3 text-right"><a href="{{ route('panel.payments.show', $payment) }}" wire:navigate class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50">View</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-4 py-10 text-center text-sm text-gray-500">No payments found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-gray-100 p-4">{{ $payments->links() }}</div>
    </x-panel.card>
</div>
