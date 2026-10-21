<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Custom Admin Panel routes
|--------------------------------------------------------------------------
|
| The custom Blade + Livewire admin panel lives under /admin. Access is gated
| by auth + the admin-access middleware (active status + an admin role).
| Route names are kept under the "panel." prefix for stability.
|
*/

Route::middleware(['auth', 'admin.access'])
    ->prefix('admin')
    ->name('panel.')
    ->group(function (): void {
        Route::livewire('/', 'panel.dashboard')->name('dashboard');

        // Car Management (custom Blade + Livewire module)
        Route::livewire('/cars', 'panel.cars.index')->name('cars.index');
        Route::livewire('/cars/create', 'panel.cars.wizard')->name('cars.create');
        Route::livewire('/cars/{car}/edit', 'panel.cars.wizard')->name('cars.edit');
        Route::livewire('/cars/{car}', 'panel.cars.show')->name('cars.show');

        // Customers (User Management)
        Route::livewire('/customers', 'panel.customers.index')->name('customers.index');
        Route::livewire('/customers/{customer}', 'panel.customers.show')->name('customers.show');

        // KYC Verification
        Route::livewire('/kyc', 'panel.kyc.index')->name('kyc.index');
        Route::livewire('/kyc/{kyc}', 'panel.kyc.show')->name('kyc.show');

        // Auctions
        Route::livewire('/auctions', 'panel.auctions.index')->name('auctions.index');
        Route::livewire('/auctions/create', 'panel.auctions.form')->name('auctions.create');
        Route::livewire('/auctions/{auction}/edit', 'panel.auctions.form')->name('auctions.edit');
        Route::livewire('/auctions/{auction}', 'panel.auctions.show')->name('auctions.show');

        // Participants, Bids, Results
        Route::livewire('/participants', 'panel.participants.index')->name('participants.index');
        Route::livewire('/bids', 'panel.bids.index')->name('bids.index');
        Route::livewire('/results', 'panel.results.index')->name('results.index');

        // Payments & Settlements (read-only)
        Route::livewire('/payments', 'panel.payments.index')->name('payments.index');
        Route::livewire('/payments/{payment}', 'panel.payments.show')->name('payments.show');

        // Inspections
        Route::livewire('/inspections', 'panel.inspections.index')->name('inspections.index');
        Route::livewire('/inspections/create', 'panel.inspections.form')->name('inspections.create');
        Route::livewire('/inspections/{inspection}/edit', 'panel.inspections.form')->name('inspections.edit');
        Route::livewire('/inspections/{inspection}', 'panel.inspections.show')->name('inspections.show');

        // Locations
        Route::livewire('/locations', 'panel.locations.index')->name('locations.index');
        Route::livewire('/locations/create', 'panel.locations.form')->name('locations.create');
        Route::livewire('/locations/{location}/edit', 'panel.locations.form')->name('locations.edit');

        // Staff Users
        Route::livewire('/staff', 'panel.staff.index')->name('staff.index');
        Route::livewire('/staff/create', 'panel.staff.form')->name('staff.create');
        Route::livewire('/staff/{staff}/edit', 'panel.staff.form')->name('staff.edit');

        // Roles & Permissions
        Route::livewire('/roles', 'panel.roles.index')->name('roles.index');
        Route::livewire('/roles/create', 'panel.roles.form')->name('roles.create');
        Route::livewire('/roles/{role}/edit', 'panel.roles.form')->name('roles.edit');

        // Reports & Analytics
        Route::livewire('/reports', 'panel.reports.index')->name('reports.index');

        // Notification Log
        Route::livewire('/notifications', 'panel.notifications.index')->name('notifications.index');

        // Audit Log
        Route::livewire('/audit-logs', 'panel.audit-logs.index')->name('audit-logs.index');
    });
