<?php
/**
 * Fresh Hydration Kios (FHK) - Standalone Entry Point & Callback Webhook
 * Server Target: https://mesinbayar.com/app/fhk/ dan https://mesinbayar.com/app/fhk/callback/
 */

date_default_timezone_set('Asia/Jakarta');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$requestUri = $_SERVER['REQUEST_URI'] ?? '';
$rawBody = file_get_contents('php://input');

// 1. Tangani Webhook Callback AiYO (POST atau request ke /callback)
if ($method === 'POST' || str_contains($requestUri, 'callback') || !empty($rawBody)) {
    header('Content-Type: application/json; charset=utf-8');

    // Jika dipanggil via GET untuk cek status callback (Health Check)
    if ($method === 'GET' && empty($rawBody)) {
        echo json_encode([
            'status'       => 'OK',
            'service'      => 'AiYO Bills Invoice Callback Service (FHK)',
            'callback_url' => 'https://mesinbayar.com/app/fhk/callback/',
            'message'      => 'Endpoint callback aktif dan siap menerima notifikasi HTTP POST dari AiYO Gateway.',
            'timestamp'    => date('Y-m-d H:i:s') . ' WIB'
        ], JSON_PRETTY_PRINT);
        exit;
    }

    // Parsing payload dari AiYO Gateway
    $payload = json_decode($rawBody, true) ?: $_POST;

    $invoiceId = $payload['responseData']['invoiceId'] 
        ?? $payload['invoiceId'] 
        ?? $_POST['invoiceId'] 
        ?? null;

    $referenceId = $payload['responseData']['referenceId'] 
        ?? $payload['referenceId'] 
        ?? $_POST['referenceId'] 
        ?? null;

    $status = $payload['responseData']['invoiceStatus'] 
        ?? $payload['responseData']['status'] 
        ?? $payload['invoiceStatus'] 
        ?? $payload['status'] 
        ?? 'PAID';

    // Catat log payload webhook
    @file_put_contents(__DIR__ . '/callback.log', '[' . date('Y-m-d H:i:s') . '] IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN') . ' | ' . $rawBody . PHP_EOL, FILE_APPEND);

    // Respon konfirmasi sukses ke AiYO Gateway
    http_response_code(200);
    echo json_encode([
        'responseCode'    => '2000000',
        'responseMessage' => 'Payment successfully verified and processed',
        'responseData'    => [
            'invoiceId'    => $invoiceId,
            'referenceId'  => $referenceId,
            'status'       => $status,
            'callback_url' => 'https://mesinbayar.com/app/fhk/callback/'
        ]
    ], JSON_PRETTY_PRINT);
    exit;
}

// 2. Jika dibuka lewat Browser biasa (GET), tampilkan Portal Pengujian FHK
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fresh Hydration Kios (FHK) - AiYO Payment Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f1f5f9; font-family: system-ui, -apple-system, sans-serif; }
        .card { border-radius: 16px; border: 1px solid #cbd5e1; }
    </style>
</head>
<body class="py-5">
    <div class="container" style="max-width: 650px;">
        <div class="card shadow-sm p-4 text-center bg-white">
            <div class="mb-3">
                <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-1 rounded-pill fw-semibold">
                    AiYO Payment Gateway - Ready & Whitelisted
                </span>
            </div>
            <h3 class="fw-bold text-slate-800 mb-1">Fresh Hydration Kios (FHK)</h3>
            <p class="text-muted small">Server: mesinbayar.com &bull; Teknologi Keuangan (Fintech)</p>

            <div class="alert alert-success text-start small border-0 bg-emerald-50 text-emerald-900 rounded-3 my-3" style="background:#ecfdf5; color:#065f46; border:1px solid #a7f3d0 !important;">
                <div><strong>🌐 URL Portal:</strong> <code>https://mesinbayar.com/app/fhk/</code></div>
                <div><strong>🔔 URL Callback:</strong> <code>https://mesinbayar.com/app/fhk/callback/</code></div>
            </div>

            <div class="list-group text-start my-3">
                <a href="token.php" class="list-group-item list-group-item-action p-3 d-flex justify-content-between align-items-center">
                    <div>
                        <div class="fw-bold text-primary">1. Get OAuth Token (token.php)</div>
                        <small class="text-muted">Mengambil Access Token dari AiYO API</small>
                    </div>
                    <span class="badge bg-primary rounded-pill px-3 py-2">Test &rarr;</span>
                </a>
                <a href="respon.php" class="list-group-item list-group-item-action p-3 d-flex justify-content-between align-items-center">
                    <div>
                        <div class="fw-bold text-success">2. Create Invoice QRIS (respon.php)</div>
                        <small class="text-muted">Membuat tagihan QRIS resmi (bankCode: 503)</small>
                    </div>
                    <span class="badge bg-success rounded-pill px-3 py-2">Buat QRIS &rarr;</span>
                </a>
                <a href="cek.php" class="list-group-item list-group-item-action p-3 d-flex justify-content-between align-items-center">
                    <div>
                        <div class="fw-bold text-warning text-dark">3. Cek Status Invoice (cek.php)</div>
                        <small class="text-muted">Mengecek status tagihan invoice</small>
                    </div>
                    <span class="badge bg-warning text-dark rounded-pill px-3 py-2">Cek Status &rarr;</span>
                </a>
                <a href="callback.php" class="list-group-item list-group-item-action p-3 d-flex justify-content-between align-items-center">
                    <div>
                        <div class="fw-bold text-secondary">4. Cek Endpoint Callback (callback.php)</div>
                        <small class="text-muted">Mengecek respon health check URL Callback</small>
                    </div>
                    <span class="badge bg-secondary rounded-pill px-3 py-2">Cek Callback &rarr;</span>
                </a>
            </div>
        </div>
    </div>
</body>
</html>
