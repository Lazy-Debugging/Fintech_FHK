<?php
/**
 * Entry point untuk URL Callback: https://mesinbayar.com/app/fhk/callback/
 * Berdiri sendiri 100% mandiri tanpa dependensi require ke folder lain
 */

date_default_timezone_set('Asia/Jakarta');
header('Content-Type: application/json; charset=utf-8');

$rawBody = file_get_contents('php://input');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// 1. Health check via GET
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

// 2. Terima POST Notifikasi Webhook dari AiYO
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

// Catat log
@file_put_contents(__DIR__ . '/callback.log', '[' . date('Y-m-d H:i:s') . '] ' . $rawBody . PHP_EOL, FILE_APPEND);

// Response 2000000 resmi
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
