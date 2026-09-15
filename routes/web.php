<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Kiosk\KioskScreenController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes - Fresh Hydration Kios (FHK) PWA & Admin Dashboard
|--------------------------------------------------------------------------
*/

// Layar Frontend Kios PWA
Route::get('/', [KioskScreenController::class, 'index'])->name('kiosk.home');
Route::get('/kiosk/qris/{invoiceId}', [KioskScreenController::class, 'qris'])->name('kiosk.qris');
Route::get('/kiosk/dispensing/{invoiceId}', [KioskScreenController::class, 'dispensing'])->name('kiosk.dispensing');
Route::get('/kiosk/receipt/{invoiceId}', [KioskScreenController::class, 'receipt'])->name('kiosk.receipt');

// Admin & Maintenance Monitoring Dashboard
Route::prefix('admin')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('admin.dashboard');
    Route::post('/trigger-uv/{kioskId}', [DashboardController::class, 'triggerUvSterilization'])->name('admin.trigger_uv');
    Route::post('/reset-filter/{filterId}', [DashboardController::class, 'resetFilter'])->name('admin.reset_filter');
    Route::get('/simulator', [DashboardController::class, 'simulator'])->name('admin.simulator');
});
