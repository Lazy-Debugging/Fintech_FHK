<?php

/**
 * Fresh Hydration Kios (FHK) - Laravel Front Controller
 * Target: https://mesinbayar.com/app/fhk/
 */

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// 1. Cek mode maintenance Laravel
if (file_exists($maintenance = __DIR__ . '/storage/framework/maintenance.php')) {
    require $maintenance;
}

// 2. Load Autoloader Composer
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require __DIR__ . '/vendor/autoload.php';
} else {
    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><body style="font-family:sans-serif;text-align:center;padding:50px;background:#f8fafc;">';
    echo '<h2 style="color:#e11d48;">Folder <code>vendor</code> Belum Terupload Lengkap</h2>';
    echo '<p style="color:#475569;">Autoloader <code>vendor/autoload.php</code> belum ditemukan di direktori cPanel.</p>';
    echo '<p>Silakan upload folder <code>vendor</code> dari komputer lokal ke server via FileZilla.</p>';
    echo '</body></html>';
    exit;
}

// 3. Bootstrap Framework Laravel & Tangani HTTP Request
$app = require_once __DIR__ . '/bootstrap/app.php';

$kernel = $app->make(Kernel::class);

$response = $kernel->handle(
    $request = Request::capture()
);

$response->send();

$kernel->terminate($request, $response);
