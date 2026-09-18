<?php

use App\Http\Controllers\Iot\Esp32BridgeController;
use App\Http\Controllers\Iot\TelemetryController;
use App\Http\Controllers\Kiosk\OrderController;
use App\Http\Controllers\Payment\AiyoCallbackController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes untuk Kiosk Frontend & ESP32 Microcontroller
|--------------------------------------------------------------------------
*/

// Group Kiosk API
Route::prefix('kiosk')->group(function () {
    Route::post('/order', [OrderController::class, 'createOrder'])->name('api.kiosk.order');
    Route::get('/payment-status/{invoiceId}', [OrderController::class, 'checkPaymentStatus'])->name('api.kiosk.payment_status');
    Route::post('/simulate-paid/{invoiceId}', [OrderController::class, 'simulatePaymentSuccess'])->name('api.kiosk.simulate_paid');
});

// Webhook Callback AiYO Bills Invoice Gateway
Route::match(['get', 'post'], '/aiyo/callback', [AiyoCallbackController::class, 'handleCallback'])->name('api.aiyo.callback');
Route::match(['get', 'post'], '/app/fhk/callback', [AiyoCallbackController::class, 'handleCallback']);
Route::match(['get', 'post'], '/app/fhk/callback/', [AiyoCallbackController::class, 'handleCallback']);


// Group IoT ESP32 Bridge API
Route::prefix('iot/kiosk/{kiosk_id}')->group(function () {
    // 1. ESP32 Polling untuk mendapatkan perintah penuangan air (Dispense Job)
    Route::get('/command', [Esp32BridgeController::class, 'getDispenseCommand'])->name('api.iot.command');
    Route::post('/redeem', [Esp32BridgeController::class, 'redeemPickup'])->name('api.iot.redeem');
    
    // 2. ESP32 Lapor bahwa pengisian air & sterilisasi UV selesai
    Route::post('/dispense-complete', [Esp32BridgeController::class, 'reportDispenseComplete'])->name('api.iot.dispense_complete');
    
    // 3. ESP32 Mengirimkan telemetri level tangki air (Ultrasonic), suhu, & kesehatan UV
    Route::post('/telemetry', [TelemetryController::class, 'recordTelemetry'])->name('api.iot.telemetry');
});

// Endpoint status penuangan air untuk PWA frontend
Route::get('/dispense-status/{invoiceId}', [Esp32BridgeController::class, 'getDispenseStatus'])->name('api.kiosk.dispense_status');
Route::post('/simulate-dispense-complete/{invoiceId}', [Esp32BridgeController::class, 'simulateDispenseComplete'])->name('api.kiosk.simulate_dispense');
