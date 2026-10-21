<?php

use App\Http\Controllers\Admin\CustomerKycDocumentController;
use App\Http\Controllers\Admin\ReportExportController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    // No public front-end: send visitors straight to the admin panel.
    // Authenticated admins reach the dashboard; everyone else hits login.
    return auth()->check()
        ? redirect()->route('panel.dashboard')
        : redirect()->route('login');
});

Route::get('/dashboard', function () {
    $user = auth()->user();

    // Admin-panel users land on the custom CarBoli admin dashboard.
    if ($user && $user->hasAnyRole([
        'Super Admin',
        'Auction Manager',
        'Car / Inventory Manager',
        'Inspection Staff',
        'Finance Manager',
        'Customer Support',
    ])) {
        return redirect()->route('panel.dashboard');
    }

    return view('dashboard');
})
    ->middleware('auth')
    ->name('dashboard');

Route::get('/admin/reports/export', ReportExportController::class)
    ->middleware('auth')
    ->name('admin.reports.export');

Route::middleware('auth')->group(function (): void {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::get('/admin/kyc/{customerKyc}/documents/{document}', CustomerKycDocumentController::class)
    ->middleware('auth')
    ->name('admin.kyc.documents');

require __DIR__.'/auth.php';
