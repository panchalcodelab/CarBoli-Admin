@php
    $user = auth()->user();

    /** Each item: [label, route name, permission(s)|null, icon key] */
    $workspace = [
        ['Dashboard', 'panel.dashboard', null, 'dashboard'],
        ['User Management', 'panel.customers.index', ['manage customers', 'view customers'], 'users'],
        ['KYC Verification', 'panel.kyc.index', ['approve kyc', 'view customer kyc'], 'shield'],
        ['Car Management', 'panel.cars.index', ['manage cars'], 'car'],
        ['Live Auctions', 'panel.auctions.index', ['manage auctions', 'create auctions', 'edit auctions', 'publish auctions'], 'gavel'],
        ['Participants', 'panel.participants.index', ['manage auction participants'], 'user-group'],
        ['Bids', 'panel.bids.index', ['manage auctions', 'manage auction results'], 'trending-up'],
        ['Results', 'panel.results.index', ['manage auction results', 'manage auctions'], 'trophy'],
        ['Inspections', 'panel.inspections.index', ['manage inspections', 'view inspections', 'update assigned inspections'], 'clipboard'],
        ['Payments & Settlements', 'panel.payments.index', ['manage payments', 'view financial reports'], 'credit-card'],
        ['Locations', 'panel.locations.index', ['manage cars'], 'map-pin'],
    ];

    $system = [
        ['Reports & Analytics', 'panel.reports.index', ['view financial reports', 'view customers'], 'chart'],
        ['Notification Log', 'panel.notifications.index', ['manage notifications'], 'bell'],
        ['Audit Log', 'panel.audit-logs.index', ['view audit logs'], 'document-check'],
        ['Staff Users', 'panel.staff.index', ['manage staff'], 'cog'],
        ['Roles & Permissions', 'panel.roles.index', ['manage roles'], 'key'],
    ];

    $canSee = function (?array $permissions) use ($user): bool {
        if ($permissions === null) {
            return true;
        }
        return $user !== null && $user->hasAnyPermission($permissions);
    };
@endphp

@foreach (['Workspace' => $workspace, 'System' => $system] as $heading => $items)
    @php $visible = array_filter($items, fn ($item) => $canSee($item[2])); @endphp
    @if ($visible !== [])
        <p class="px-3 pb-2 pt-4 text-xs font-semibold uppercase tracking-wider text-gray-400">{{ $heading }}</p>
        @foreach ($visible as [$label, $routeName, $permissions, $icon])
            @php
                $active = request()->routeIs($routeName) || request()->routeIs(str_replace('.index', '', $routeName).'.*');
                $exists = \Illuminate\Support\Facades\Route::has($routeName);
            @endphp
            <a href="{{ $exists ? route($routeName) : '#' }}" wire:navigate
               @class([
                   'flex items-center gap-3 rounded-lg px-3 py-2 text-sm transition-colors',
                   'bg-carboli-50 text-carboli-700 font-semibold' => $active,
                   'text-gray-700 hover:bg-gray-50' => ! $active,
               ])
               @if ($active) aria-current="page" @endif>
                <x-panel.icon :name="$icon" class="h-5 w-5 shrink-0" />
                <span>{{ $label }}</span>
                @unless ($exists)
                    <span class="ml-auto text-[10px] uppercase text-gray-300">soon</span>
                @endunless
            </a>
        @endforeach
    @endif
@endforeach
