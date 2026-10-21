<?php

use App\Services\ReportService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('panel.layouts.app')] class extends Component
{
    public ?string $from = null;
    public ?string $to = null;

    public function mount(): void
    {
        $user = Auth::user();
        abort_unless(
            $user !== null && ($user->can('view financial reports') || $user->can('view customers')),
            403,
        );
    }

    public function with(): array
    {
        $user = Auth::user();
        // Calculations are delegated entirely to the existing ReportService
        // (permission-scoped, date-ranged). No logic is duplicated here.
        $summary = app(ReportService::class)->summary($user, $this->from ?: null, $this->to ?: null);

        return [
            'summary' => $summary,
            'canViewFinancials' => $user?->can('view financial reports') ?? false,
            'canViewCustomers' => $user?->can('view customers') ?? false,
        ];
    }
}; ?>

<div>
    @section('title', 'Reports & Analytics')
    @section('breadcrumb', 'System / Reports & Analytics')

    <x-panel.page-header overline="System" title="Reports & Analytics"
        description="Operational and financial summary with date filtering and CSV export." />

    <x-panel.card heading="Date range" class="mb-6">
        <div class="flex flex-wrap items-end gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700">From</label>
                <input type="date" wire:model.live="from" class="mt-1 rounded-lg border-gray-300 text-sm focus:border-carboli-500 focus:ring-carboli-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">To</label>
                <input type="date" wire:model.live="to" class="mt-1 rounded-lg border-gray-300 text-sm focus:border-carboli-500 focus:ring-carboli-500">
            </div>
        </div>
    </x-panel.card>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        @if ($canViewFinancials)
            <x-panel.stat-card label="Auctions" :value="number_format($summary['auction_count'] ?? 0)">
                <x-slot:icon><x-panel.icon name="gavel" class="h-5 w-5" /></x-slot:icon>
            </x-panel.stat-card>
            <x-panel.stat-card label="Payments" :value="number_format($summary['payment_count'] ?? 0)">
                <x-slot:icon><x-panel.icon name="credit-card" class="h-5 w-5" /></x-slot:icon>
            </x-panel.stat-card>
            <x-panel.stat-card label="Captured amount" :value="'₹'.number_format((float) ($summary['captured_payment_total'] ?? 0), 2)">
                <x-slot:icon><x-panel.icon name="trending-up" class="h-5 w-5" /></x-slot:icon>
            </x-panel.stat-card>
            <x-panel.stat-card label="Inspections" :value="number_format($summary['inspection_count'] ?? 0)">
                <x-slot:icon><x-panel.icon name="clipboard" class="h-5 w-5" /></x-slot:icon>
            </x-panel.stat-card>
        @endif
        @if ($canViewCustomers)
            <x-panel.stat-card label="Customers" :value="number_format($summary['customer_count'] ?? 0)">
                <x-slot:icon><x-panel.icon name="users" class="h-5 w-5" /></x-slot:icon>
            </x-panel.stat-card>
        @endif
    </div>

    <x-panel.card heading="Export data" description="Download CSV reports for the selected date range" class="mt-6">
        <div class="flex flex-wrap gap-3">
            @php($range = array_filter(['from' => $from, 'to' => $to]))
            @if ($canViewFinancials)
                @foreach (['auctions' => 'Auctions CSV', 'payments' => 'Payments CSV', 'inspections' => 'Inspections CSV'] as $type => $text)
                    <a href="{{ route('admin.reports.export', array_merge(['type' => $type], $range)) }}"
                       class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ $text }}</a>
                @endforeach
            @endif
            @if ($canViewCustomers)
                <a href="{{ route('admin.reports.export', array_merge(['type' => 'customers'], $range)) }}"
                   class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Customers CSV</a>
            @endif
        </div>
        <p class="mt-3 text-xs text-gray-400">Exports reuse the existing, permission-checked report export endpoint.</p>
    </x-panel.card>
</div>
