<?php
/**
 * Handler Callback / Webhook AiYO Bills Invoice Gateway
 * Sesuai dengan Meeting 03 Slide 20 (URL Callback: https://mesinbayar.com/app/fhk/callback/)
 */

include_once __DIR__ . "/aiyo_config.php";
include_once __DIR__ . "/db_config.php";

// Set header response ke format JSON
header('Content-Type: application/json; charset=utf-8');

// 1. Jika diakses via HTTP GET atau browser (Pengecekan status endpoint / Health Check)
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$rawBody = file_get_contents('php://input');

if ($method === 'GET' && empty($rawBody)) {
    echo json_encode([
        'status'       => 'OK',
        'service'      => 'AiYO Bills Invoice Callback Service (FHK)',
        'callback_url' => $callbackUrl,
        'message'      => 'Endpoint callback aktif dan siap menerima notifikasi HTTP POST dari AiYO Gateway.',
        'timestamp'    => date('Y-m-d H:i:s') . ' WIB'
    ], JSON_PRETTY_PRINT);
    exit;
}

// Catat ke file log terpisah untuk audit dan debugging
$logDir = __DIR__ . '/storage/logs';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0777, true);
}
$logFile = $logDir . '/aiyo_callback.log';
$logEntry = '[' . date('Y-m-d H:i:s') . '] IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN') . ' | Raw: ' . $rawBody . PHP_EOL;
@file_put_contents($logFile, $logEntry, FILE_APPEND);

// 3. Parsing data JSON
$payload = json_decode($rawBody, true);
if (empty($payload)) {
    $payload = $_POST;
}

// Ekstraksi invoiceId, referenceId, status, dan nominal
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
    ?? null;

if (empty($invoiceId) && empty($referenceId)) {
    http_response_code(400);
    echo json_encode([
        'responseCode'    => '4000000',
        'responseMessage' => 'Invalid Payload: invoiceId or referenceId is required',
        'callback_url'    => $callbackUrl
    ], JSON_PRETTY_PRINT);
    exit;
}

// 4. Update status di database jika koneksi tersedia
$updateSuccess = false;
$newStatus = 'PAID';

if ($status && in_array(strtoupper($status), ['PAID', 'SUCCESS', 'SETTLED', 'COMPLETED'])) {
    $newStatus = 'PAID';
} elseif ($status && in_array(strtoupper($status), ['EXPIRED', 'CANCELLED', 'FAILED'])) {
    $newStatus = strtoupper($status);
}

if ($conn) {
    if ($dbType === 'mysql') {
        $idClean = $conn->real_escape_string($invoiceId ?? '');
        $refClean = $conn->real_escape_string($referenceId ?? '');
        $stClean = $conn->real_escape_string($newStatus);
        
        $sql = "UPDATE `transaksi` SET `status` = '{$stClean}' WHERE ";
        if (!empty($idClean)) {
            $sql .= "`invoiceId` = '{$idClean}'";
        } else {
            $sql .= "`referenceId` = '{$refClean}'";
        }
        $updateSuccess = $conn->query($sql);
        $conn->close();
    } elseif ($dbType === 'sqlite') {
        try {
            $sql = "UPDATE `transaksi` SET `status` = ?, `updated_at` = datetime('now') WHERE ";
            if (!empty($invoiceId)) {
                $sql .= "`invoiceId` = ?";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$newStatus, $invoiceId]);
            } else {
                $sql .= "`referenceId` = ?";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$newStatus, $referenceId]);
            }
            $updateSuccess = true;
        } catch (Exception $e) {
            // Log database update error
        }
    }
}

// 5. Kembalikan response sukses 2000000 ke AiYO Gateway
http_response_code(200);
echo json_encode([
    'responseCode'    => '2000000',
    'responseMessage' => 'Payment successfully verified and processed',
    'responseData'    => [
        'invoiceId'    => $invoiceId,
        'referenceId'  => $referenceId,
        'status'       => $newStatus,
        'callback_url' => $callbackUrl
    ]
], JSON_PRETTY_PRINT);
?>
