<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\PricingController;
use App\Http\Controllers\Admin\VoucherController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Kiosk\KioskScreenController;
use App\Http\Controllers\MobileOrderController;
use App\Http\Controllers\Payment\AiyoCallbackController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes - Fresh Hydration Kios (FHK) PWA & Admin Dashboard
|--------------------------------------------------------------------------
*/

// Dashboard kios adalah halaman paling awal; login/tamu hanya opsi tambahan.
Route::get('/', [KioskScreenController::class, 'index'])->name('home');

// Dashboard monitoring publik (read-only, tanpa gerbang login).
Route::get('/dashboard', function () {
    return view('welcome');
})->name('public.dashboard');

Route::get('/profile', [ProfileController::class, 'index'])->name('profile');

Route::get('/login', [AuthController::class, 'showLogin'])->middleware('guest')->name('login');
Route::get('/admin/login', [AuthController::class, 'showAdminLogin'])->middleware('guest')->name('admin.login');
Route::post('/login/admin', [AuthController::class, 'adminLogin'])->name('login.admin');
Route::post('/continue-as-guest', [AuthController::class, 'guest'])->name('login.guest');
Route::get('/auth/google', [AuthController::class, 'redirectToGoogle'])->name('google.redirect');
Route::get('/auth/google/callback', [AuthController::class, 'handleGoogleCallback'])->name('google.callback');
Route::get('/index.php/auth/google/callback', [AuthController::class, 'handleGoogleCallback']);
Route::get('/fhk/index.php/auth/google/callback', [AuthController::class, 'handleGoogleCallback']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
Route::get('/orders/{invoiceId}/collect', [MobileOrderController::class, 'collect'])->name('orders.collect');

// PWA resources are routed so they work behind a subdirectory front controller.
Route::get('/manifest.webmanifest', function () {
    return response()->json([
        'name' => 'Fresh Hydration Kios (FHK)',
        'short_name' => 'FHK Kiosk',
        'id' => './kiosk',
        'start_url' => './kiosk?kiosk_id=FHK-JAKARTA-01',
        'scope' => './',
        'display' => 'standalone',
        'background_color' => '#0a0f1d',
        'theme_color' => '#06b6d4',
        'orientation' => 'any',
        'description' => 'Dispenser air minum otomatis dengan pembayaran QRIS AiYO.',
        'icons' => [
            ['src' => 'icons/icon-192.svg', 'sizes' => '192x192', 'type' => 'image/svg+xml', 'purpose' => 'any'],
            ['src' => 'icons/icon-512.svg', 'sizes' => '512x512', 'type' => 'image/svg+xml', 'purpose' => 'any maskable'],
        ],
    ])->header('Content-Type', 'application/manifest+json');
})->name('pwa.manifest');

Route::get('/sw.js', function () {
    return response()->file(public_path('sw.js'), [
        'Content-Type' => 'application/javascript; charset=UTF-8',
        'Cache-Control' => 'no-cache',
        'Service-Worker-Allowed' => request()->getBaseUrl().'/',
    ]);
})->name('pwa.service_worker');

Route::get('/offline', function () {
    return response()->view('pwa.offline');
})->name('pwa.offline');

Route::get('/icons/{icon}', function (string $icon) {
    abort_unless(in_array($icon, ['icon-192.svg', 'icon-512.svg'], true), 404);

    return response()->file(public_path('icons/'.$icon), [
        'Content-Type' => 'image/svg+xml',
        'Cache-Control' => 'public, max-age=604800',
    ]);
})->where('icon', '[A-Za-z0-9.-]+')->name('pwa.icon');

// Layar Frontend Kios PWA
Route::get('/kiosk', [KioskScreenController::class, 'index'])->name('kiosk.home');
// URL compatibility for the dashboard/external AiYO entry point.
Route::get('/app/fhk', [KioskScreenController::class, 'index']);
Route::get('/app/fhk/', [KioskScreenController::class, 'index']);
Route::get('/app/fhk/kiosk', [KioskScreenController::class, 'index'])->name('kiosk.external');
Route::get('/kiosk/qris/{invoiceId}', [KioskScreenController::class, 'qris'])->name('kiosk.qris');
Route::get('/kiosk/dispensing/{invoiceId}', [KioskScreenController::class, 'dispensing'])->name('kiosk.dispensing');
Route::get('/kiosk/receipt/{invoiceId}', [KioskScreenController::class, 'receipt'])->name('kiosk.receipt');
Route::post('/kiosk/order', [\App\Http\Controllers\Kiosk\OrderController::class, 'createOrder'])->name('kiosk.order');
Route::post('/app/fhk/kiosk/order', [\App\Http\Controllers\Kiosk\OrderController::class, 'createOrder']);
Route::post('/fhk/kiosk/order', [\App\Http\Controllers\Kiosk\OrderController::class, 'createOrder']);
Route::post('/api/kiosk/order', [\App\Http\Controllers\Kiosk\OrderController::class, 'createOrder'])->name('api.kiosk.order.web');
Route::post('/app/fhk/api/kiosk/order', [\App\Http\Controllers\Kiosk\OrderController::class, 'createOrder']);
Route::post('/fhk/api/kiosk/order', [\App\Http\Controllers\Kiosk\OrderController::class, 'createOrder']);
Route::get('/api/kiosk/payment-status/{invoiceId}', [\App\Http\Controllers\Kiosk\OrderController::class, 'checkPaymentStatus']);
Route::get('/fhk/api/kiosk/payment-status/{invoiceId}', [\App\Http\Controllers\Kiosk\OrderController::class, 'checkPaymentStatus']);
Route::get('/app/fhk/api/kiosk/payment-status/{invoiceId}', [\App\Http\Controllers\Kiosk\OrderController::class, 'checkPaymentStatus']);

Route::match(['get', 'post'], '/orders/{invoiceId}/cancel', [\App\Http\Controllers\Kiosk\OrderController::class, 'cancelOrder'])->name('orders.cancel');
Route::match(['get', 'post'], '/fhk/orders/{invoiceId}/cancel', [\App\Http\Controllers\Kiosk\OrderController::class, 'cancelOrder']);
Route::match(['get', 'post'], '/app/fhk/orders/{invoiceId}/cancel', [\App\Http\Controllers\Kiosk\OrderController::class, 'cancelOrder']);

Route::match(['get', 'post'], '/orders/{invoiceId}/redeem-scan', [\App\Http\Controllers\Kiosk\OrderController::class, 'redeemScan'])->name('orders.redeem_scan');
Route::match(['get', 'post'], '/fhk/orders/{invoiceId}/redeem-scan', [\App\Http\Controllers\Kiosk\OrderController::class, 'redeemScan']);
Route::match(['get', 'post'], '/app/fhk/orders/{invoiceId}/redeem-scan', [\App\Http\Controllers\Kiosk\OrderController::class, 'redeemScan']);

Route::get('/api/kiosk/{kioskId}/poll-dispense', [\App\Http\Controllers\Kiosk\OrderController::class, 'pollDispense'])->name('api.kiosk.poll_dispense');
Route::get('/fhk/api/kiosk/{kioskId}/poll-dispense', [\App\Http\Controllers\Kiosk\OrderController::class, 'pollDispense']);
Route::get('/app/fhk/api/kiosk/{kioskId}/poll-dispense', [\App\Http\Controllers\Kiosk\OrderController::class, 'pollDispense']);

// AiYO Bills Invoice Gateway Callback Webhook (Target: https://mesinbayar.com/app/fhk/callback/)
Route::match(['get', 'post'], '/app/fhk/callback', [AiyoCallbackController::class, 'handleCallback'])->name('aiyo.callback');
Route::match(['get', 'post'], '/app/fhk/callback/', [AiyoCallbackController::class, 'handleCallback']);
Route::match(['get', 'post'], '/callback', [AiyoCallbackController::class, 'handleCallback']);
Route::match(['get', 'post'], '/callback/', [AiyoCallbackController::class, 'handleCallback']);
Route::match(['get', 'post'], '/callback.php', [AiyoCallbackController::class, 'handleCallback']);
Route::match(['get', 'post'], '/app/fhk/callback.php', [AiyoCallbackController::class, 'handleCallback']);
Route::match(['get', 'post'], '/fhk/callback.php', [AiyoCallbackController::class, 'handleCallback']);

// Standalone PPT DBI Route Aliases (respon.php, token.php, cek.php)
Route::match(['get', 'post'], '/respon.php', function () {
    require base_path('respon.php');
})->name('respon.php');
Route::match(['get', 'post'], '/app/fhk/respon.php', function () {
    require base_path('respon.php');
});
Route::match(['get', 'post'], '/fhk/respon.php', function () {
    require base_path('respon.php');
});
Route::match(['get', 'post'], '/index.php/respon.php', function () {
    require base_path('respon.php');
});
Route::match(['get', 'post'], '/fhk/index.php/respon.php', function () {
    require base_path('respon.php');
});
Route::match(['get', 'post'], '/app/fhk/index.php/respon.php', function () {
    require base_path('respon.php');
});

Route::match(['get', 'post'], '/token.php', function () {
    require base_path('token.php');
});
Route::match(['get', 'post'], '/app/fhk/token.php', function () {
    require base_path('token.php');
});
Route::match(['get', 'post'], '/fhk/token.php', function () {
    require base_path('token.php');
});
Route::match(['get', 'post'], '/index.php/token.php', function () {
    require base_path('token.php');
});
Route::match(['get', 'post'], '/fhk/index.php/token.php', function () {
    require base_path('token.php');
});

Route::match(['get', 'post'], '/cek.php', function () {
    require base_path('cek.php');
});
Route::match(['get', 'post'], '/app/fhk/cek.php', function () {
    require base_path('cek.php');
});
Route::match(['get', 'post'], '/fhk/cek.php', function () {
    require base_path('cek.php');
});
Route::match(['get', 'post'], '/index.php/cek.php', function () {
    require base_path('cek.php');
});
Route::match(['get', 'post'], '/fhk/index.php/cek.php', function () {
    require base_path('cek.php');
});

Route::match(['get', 'post'], '/migrate.php', function () {
    require base_path('migrate.php');
});
Route::match(['get', 'post'], '/app/fhk/migrate.php', function () {
    require base_path('migrate.php');
});
Route::match(['get', 'post'], '/fhk/migrate.php', function () {
    require base_path('migrate.php');
});
Route::match(['get', 'post'], '/index.php/migrate.php', function () {
    require base_path('migrate.php');
});
Route::match(['get', 'post'], '/fhk/index.php/migrate.php', function () {
    require base_path('migrate.php');
});


// Area administrasi terpisah dari dashboard publik.
Route::prefix('admin')->as('admin.')->middleware(['auth', 'admin'])->group(function () {
    Route::redirect('/', '/admin/dashboard')->name('home');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('/trigger-uv/{kioskId}', [DashboardController::class, 'triggerUvSterilization'])->name('trigger_uv');
    Route::post('/reset-filter/{filterId}', [DashboardController::class, 'resetFilter'])->name('reset_filter');
    Route::get('/simulator', [DashboardController::class, 'simulator'])->name('simulator');
    Route::get('/vouchers', [VoucherController::class, 'index'])->name('vouchers.index');
    Route::post('/vouchers', [VoucherController::class, 'store'])->name('vouchers.store');
    Route::patch('/vouchers/{voucher}', [VoucherController::class, 'update'])->name('vouchers.update');
    Route::get('/pricing', [PricingController::class, 'index'])->name('pricing.index');
    Route::post('/pricing', [PricingController::class, 'update'])->name('pricing.update');
    Route::post('/pricing/reset', [PricingController::class, 'resetToGlobal'])->name('pricing.reset');
});
