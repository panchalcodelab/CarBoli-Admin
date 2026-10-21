<?php

use App\Models\Auction;
use App\Models\Car;
use App\Models\CustomerKyc;
use App\Models\Inspection;
use App\Models\Payment;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('panel.layouts.app')] class extends Component
{
    /**
     * Build the permission-aware stat cards shown on the dashboard.
     *
     * Values are read-only aggregates over existing models. Trend is only
     * included when a prior full month provides a real comparison; otherwise
     * it is omitted rather than fabricated.
     *
     * @return array<int, array{label: string, value: string, trend: ?string, trendUp: bool, icon: string}>
     */
    public function stats(): array
    {
        $user = Auth::user();
        $cards = [];

        if ($user?->can('manage cars')) {
            $cards[] = [
                'label' => 'Total vehicles listed',
                'value' => number_format(Car::query()->count()),
                'trend' => null,
                'trendUp' => true,
                'icon' => 'car',
            ];
        }

        if ($user?->can('manage auctions')) {
            $cards[] = [
                'label' => 'Live auctions',
                'value' => number_format(Auction::query()->where('published', true)->whereIn('status', ['scheduled', 'live'])->count()),
                'trend' => null,
                'trendUp' => true,
                'icon' => 'gavel',
            ];
        }

        if ($user?->hasAnyPermission(['approve kyc', 'view customer kyc'])) {
            $cards[] = [
                'label' => 'Pending approvals',
                'value' => number_format(CustomerKyc::query()->whereIn('status', ['pending', 'under_review'])->count()),
                'trend' => null,
                'trendUp' => true,
                'icon' => 'document-check',
            ];
        }

        if ($user?->hasAnyPermission(['manage payments', 'view financial reports'])) {
            [$value, $trend, $up] = $this->auctionValueThisMonth();
            $cards[] = [
                'label' => 'Auction value this month',
                'value' => '₹'.$value,
                'trend' => $trend,
                'trendUp' => $up,
                'icon' => 'trending-up',
            ];
        }

        return $cards;
    }

    /**
     * @return array{0: string, 1: ?string, 2: bool}
     */
    private function auctionValueThisMonth(): array
    {
        $startOfThisMonth = now()->startOfMonth();
        $startOfLastMonth = now()->subMonthNoOverflow()->startOfMonth();

        $current = (float) Payment::query()
            ->where('status', 'captured')
            ->where('captured_at', '>=', $startOfThisMonth)
            ->sum('amount');

        $previous = (float) Payment::query()
            ->where('status', 'captured')
            ->whereBetween('captured_at', [$startOfLastMonth, $startOfThisMonth])
            ->sum('amount');

        $trend = null;
        $up = true;
        if ($previous > 0.0) {
            $pct = (($current - $previous) / $previous) * 100;
            $up = $pct >= 0;
            $trend = ($up ? '+' : '').number_format($pct, 1).'%';
        }

        return [$this->formatIndianShort($current), $trend, $up];
    }

    private function formatIndianShort(float $value): string
    {
        if ($value >= 10000000) {
            return number_format($value / 10000000, 2).'Cr';
        }
        if ($value >= 100000) {
            return number_format($value / 100000, 2).'L';
        }

        return number_format($value, 0);
    }

    /**
     * Latest cars for the "recently added" panel (read-only, permission-gated).
     *
     * @return \Illuminate\Support\Collection<int, Car>
     */
    public function recentCars(): \Illuminate\Support\Collection
    {
        if (! Auth::user()?->can('manage cars')) {
            return collect();
        }

        return Car::query()->with('location')->latest()->limit(5)->get();
    }

    /**
     * @return array<int, array{label: string, description: string, count: int, color: string}>
     */
    public function approvalQueue(): array
    {
        $user = Auth::user();
        $items = [];

        if ($user?->hasAnyPermission(['approve kyc', 'view customer kyc'])) {
            $items[] = [
                'label' => 'KYC verification',
                'description' => 'Customer KYC records awaiting review.',
                'count' => CustomerKyc::query()->whereIn('status', ['pending', 'under_review'])->count(),
                'color' => 'warning',
            ];
        }

        if ($user?->hasAnyPermission(['manage inspections', 'view inspections'])) {
            $items[] = [
                'label' => 'Vehicle inspection',
                'description' => 'Inspections not yet completed.',
                'count' => Inspection::query()->whereIn('status', ['requested', 'scheduled', 'assigned', 'confirmed', 'in_progress'])->count(),
                'color' => 'info',
            ];
        }

        return $items;
    }

    /**
     * Permission-gated shortcuts shown in the "Quick actions" panel.
     *
     * Each entry links to an existing /admin route and is only surfaced when
     * the user holds the relevant permission.
     *
     * @return array<int, array{title: string, subtitle: string, cta: string, route: string, icon: string}>
     */
    public function quickLinks(): array
    {
        $user = Auth::user();
        $links = [];

        if ($user?->can('manage cars')) {
            $links[] = [
                'title' => 'Add a new vehicle',
                'subtitle' => 'Create a listing in six easy steps.',
                'cta' => 'Start adding',
                'route' => route('panel.cars.create'),
                'icon' => 'car',
            ];
        }

        if ($user?->hasAnyPermission(['approve kyc', 'view customer kyc'])) {
            $links[] = [
                'title' => 'Review documents',
                'subtitle' => 'Verify pending customer KYC submissions.',
                'cta' => 'Open queue',
                'route' => route('panel.kyc.index'),
                'icon' => 'document-check',
            ];
        }

        if ($user?->hasAnyPermission(['manage inspections', 'view inspections'])) {
            $links[] = [
                'title' => 'Assign inspections',
                'subtitle' => 'Schedule and track vehicle inspections.',
                'cta' => 'Manage inspections',
                'route' => route('panel.inspections.index'),
                'icon' => 'clipboard',
            ];
        }

        return $links;
    }

    public function greeting(): string
    {
        $hour = (int) now()->format('G');

        return match (true) {
            $hour < 12 => 'Good morning',
            $hour < 17 => 'Good afternoon',
            default => 'Good evening',
        };
    }
}; ?>

<div>
    @section('title', 'Dashboard')
    @section('breadcrumb', 'Workspace / Dashboard')

    <div class="mb-6">
        <p class="text-sm font-semibold uppercase tracking-wide text-carboli-600">{{ now()->format('l, j F Y') }}</p>
        <h1 class="mt-1 text-2xl font-bold tracking-tight text-gray-950">
            {{ $this->greeting() }}{{ auth()->user() ? ', '.\Illuminate\Support\Str::of(auth()->user()->name)->explode(' ')->first() : '' }} 👋
        </h1>
        <p class="mt-1 text-sm text-gray-500">Here's what's happening with your CarBoli marketplace today.</p>
    </div>

    @php $stats = $this->stats(); @endphp
    @if ($stats !== [])
        <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ($stats as $stat)
                <x-panel.stat-card
                    :label="$stat['label']"
                    :value="$stat['value']"
                    :trend="$stat['trend']"
                    :trend-up="$stat['trendUp']"
                >
                    <x-slot:icon>
                        <x-panel.icon :name="$stat['icon']" class="h-5 w-5" />
                    </x-slot:icon>
                </x-panel.stat-card>
            @endforeach
        </div>
    @endif

    @php $quickLinks = $this->quickLinks(); @endphp
    @if ($quickLinks !== [])
        <x-panel.card class="mb-6" heading="Quick actions" description="Keep your marketplace moving.">
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($quickLinks as $link)
                    <a href="{{ $link['route'] }}" wire:navigate
                       class="group flex flex-col rounded-xl border border-gray-200 p-4 transition hover:border-carboli-300 hover:bg-carboli-50/40 focus:outline-none focus-visible:ring-2 focus-visible:ring-carboli-500">
                        <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-carboli-50 text-carboli-600">
                            <x-panel.icon :name="$link['icon']" class="h-5 w-5" />
                        </span>
                        <p class="mt-3 text-sm font-semibold text-gray-950">{{ $link['title'] }}</p>
                        <p class="mt-1 text-xs text-gray-500">{{ $link['subtitle'] }}</p>
                        <span class="mt-3 inline-flex items-center gap-1 text-xs font-semibold text-carboli-600 group-hover:text-carboli-700">
                            {{ $link['cta'] }}
                            <x-panel.icon name="arrow-right" class="h-4 w-4 transition group-hover:translate-x-0.5" />
                        </span>
                    </a>
                @endforeach
            </div>
        </x-panel.card>
    @endif

    <div class="grid gap-6 lg:grid-cols-2">
        <x-panel.card heading="Recently added vehicles" description="Latest cars added to your inventory">
            @php $cars = $this->recentCars(); @endphp
            @forelse ($cars as $car)
                <div class="flex items-center justify-between gap-4 border-b border-gray-100 py-3 last:border-0">
                    <div>
                        <p class="text-sm font-semibold text-gray-950">{{ trim($car->make.' '.$car->model) }}</p>
                        <p class="text-xs text-gray-500">{{ $car->registration_number ?? '—' }}{{ $car->manufacturing_year ? ' · '.$car->manufacturing_year : '' }}</p>
                    </div>
                    <x-panel.badge :color="match($car->status) {
                        'available', 'live_auction' => 'success',
                        'auction_scheduled', 'inspection_scheduled' => 'warning',
                        'sold' => 'info',
                        'draft' => 'gray',
                        default => 'gray',
                    }">{{ \Illuminate\Support\Str::of($car->status)->replace('_', ' ')->title() }}</x-panel.badge>
                </div>
            @empty
                <p class="text-sm text-gray-500">No vehicles yet.</p>
            @endforelse
        </x-panel.card>

        <x-panel.card heading="Approval queue" description="Items that need your attention">
            @forelse ($this->approvalQueue() as $item)
                <div class="flex items-center justify-between gap-4 border-b border-gray-100 py-3 last:border-0">
                    <div>
                        <p class="text-sm font-semibold text-gray-950">{{ $item['label'] }}</p>
                        <p class="text-sm text-gray-500">{{ $item['description'] }}</p>
                    </div>
                    <x-panel.badge :color="$item['color']">{{ $item['count'] }} pending</x-panel.badge>
                </div>
            @empty
                <p class="text-sm text-gray-500">Nothing waiting for review.</p>
            @endforelse
        </x-panel.card>
    </div>
</div>
