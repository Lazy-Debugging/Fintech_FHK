<?php
/**
 * respon_mobile.php
 * Sesuai materi kuliah: Meeting 20 Dunia Bayar Mobile Apps (Slide 5 & 6)
 * Endpoint integrasi AiYO Bills Invoice untuk aplikasi mobile (MIT App Inventor).
 */

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, x-aiyo-key, x-aiyo-signature');

if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// 1. Muat token & konfigurasi AiYO (token.php & aiyo_config.php)
include_once __DIR__ . "/token.php";

// 2. Dukung input baik dari x-www-form-urlencoded maupun Raw JSON dari MIT App Inventor
$rawInput = file_get_contents('php://input');
if (!empty($rawInput)) {
    $jsonDecoded = json_decode($rawInput, true);
    if (is_array($jsonDecoded)) {
        $_POST = array_merge($jsonDecoded, $_POST);
    }
}

// 3. Fallback parameter agar tidak error saat diuji coba langsung di browser
$invoiceName = $_POST['invoiceName'] ?? "Tagihan FHK Mobile";
$referenceId = $_POST['referenceId'] ?? ("FHK" . date('ymdHis') . rand(10, 99));
$userName    = $_POST['userName'] ?? "Pelanggan Mobile";
$userEmail   = $_POST['userEmail'] ?? "customer@fhk.id";
$userPhone   = $_POST['userPhone'] ?? "081234567890";
$remarks     = $_POST['remarks'] ?? "Pembayaran Mobile App FHK";
$payAmount   = isset($_POST['payAmount']) ? (int) $_POST['payAmount'] : 10000;
$paymentType = $_POST['paymentType'] ?? "VA_CLOSED";
$bankCode    = $_POST['bankCode'] ?? "022";

// 4. Struktur data pembuatan invoice sesuai Slide 5 (respon_mobile.php (1))
$bodyCreateInvoice = array(
    "invoiceName"   => $invoiceName,
    "referenceId"   => $referenceId,
    "userName"      => $userName,
    "userEmail"     => $userEmail,
    "userPhone"     => $userPhone,
    "remarks"       => $remarks,
    "payAmount"     => $payAmount,
    "expireTime"    => date('Y-m-d\TH:i', strtotime('+3 hour')),
    "billMasterId"  => $billMasterId ?? "uxGSWGOqpeqLaG5Qn1DH",
    "paymentMethod" => array(
        "type"     => $paymentType,
        "bankCode" => $bankCode
    ),
    "items"         => array()
);

// Format items array (Mendukung array itemName[] maupun single itemName)
if (isset($_POST['itemName'])) {
    if (is_array($_POST['itemName'])) {
        for ($i = 0; $i < count($_POST['itemName']); $i++) {
            $item = array(
                "itemName"       => (string) ($_POST['itemName'][$i] ?? 'Item ' . ($i + 1)),
                "itemType"       => "ITEM",
                "itemCount"      => (string) ($_POST['itemCount'][$i] ?? '1'),
                "itemTotalPrice" => (string) ($_POST['itemTotalPrice'][$i] ?? $payAmount)
            );
            $bodyCreateInvoice['items'][] = $item;
        }
    } else {
        $item = array(
            "itemName"       => (string) $_POST['itemName'],
            "itemType"       => "ITEM",
            "itemCount"      => (string) ($_POST['itemCount'] ?? '1'),
            "itemTotalPrice" => (string) ($_POST['itemTotalPrice'] ?? $payAmount)
        );
        $bodyCreateInvoice['items'][] = $item;
    }
} else {
    // Default item jika tidak dikirim dari form MIT App Inventor
    $bodyCreateInvoice['items'][] = array(
        "itemName"       => $invoiceName,
        "itemType"       => "ITEM",
        "itemCount"      => "1",
        "itemTotalPrice" => (string) $payAmount
    );
}

// 5. Signature & Eksekusi cURL sesuai Slide 6 (respon_mobile.php (2))
$pathInvoice = '/api/v1/invoice';
$urlCreateInvoice = $host . $pathInvoice;
$signRelativeURLCreateInvoice = parse_url($urlCreateInvoice, PHP_URL_PATH);
$rawBodyCreateInvoice = json_encode($bodyCreateInvoice);
$dataToSignCreateInvoice = $api_key . $signRelativeURLCreateInvoice . $rawBodyCreateInvoice;
$signatureCreateInvoice = hash_hmac('sha256', $dataToSignCreateInvoice, $api_secret);

$chCreateInvoice = curl_init($urlCreateInvoice);
$headersCreateInvoice = array(
    "Content-Type: application/json",
    "Authorization: Bearer " . $accessToken,
    "x-aiyo-key: " . $api_key,
    "x-aiyo-signature: " . $signatureCreateInvoice
);

curl_setopt($chCreateInvoice, CURLOPT_TIMEOUT, 30);
curl_setopt($chCreateInvoice, CURLOPT_POST, 1);
curl_setopt($chCreateInvoice, CURLOPT_RETURNTRANSFER, TRUE);
curl_setopt($chCreateInvoice, CURLOPT_SSL_VERIFYPEER, FALSE);
curl_setopt($chCreateInvoice, CURLOPT_SSL_VERIFYHOST, 0);
curl_setopt($chCreateInvoice, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
curl_setopt($chCreateInvoice, CURLOPT_HTTPHEADER, $headersCreateInvoice);
curl_setopt($chCreateInvoice, CURLOPT_POSTFIELDS, $rawBodyCreateInvoice);

$sentHeaders = curl_getinfo($chCreateInvoice, CURLINFO_HEADER_OUT);
$responseCreateInvoice = curl_exec($chCreateInvoice);
$curlError = curl_error($chCreateInvoice);
$httpCode = curl_getinfo($chCreateInvoice, CURLINFO_HTTP_CODE);
curl_close($chCreateInvoice);

// 6. Simpan transaksi ke database lokal FHK jika berhasil dibuat
$resObj = json_decode($responseCreateInvoice);
if ($resObj && isset($resObj->responseCode) && $resObj->responseCode === '2000000') {
    $invoiceData = $resObj->responseData ?? null;
    if ($invoiceData && file_exists(__DIR__ . '/db_config.php')) {
        @include_once __DIR__ . '/db_config.php';
        if (isset($conn) && $conn) {
            $invId = $invoiceData->invoiceId ?? '';
            $invToken = $invoiceData->accessToken ?? '';
            $itemsJson = json_encode($bodyCreateInvoice['items']);
            if (isset($dbType) && $dbType === 'mysql') {
                $sql = "INSERT INTO `transaksi`
                        (`referenceId`, `userName`, `userEmail`, `userPhone`, `remarks`, `payAmount`, `items`, `invoiceId`, `status`, `aiyo_access_token`, `created_at`, `updated_at`)
                        VALUES (
                            '" . $conn->real_escape_string($referenceId) . "',
                            '" . $conn->real_escape_string($userName) . "',
                            '" . $conn->real_escape_string($userEmail) . "',
                            '" . $conn->real_escape_string($userPhone) . "',
                            '" . $conn->real_escape_string($remarks) . "',
                            '" . $conn->real_escape_string($payAmount) . "',
                            '" . $conn->real_escape_string($itemsJson) . "',
                            '" . $conn->real_escape_string($invId) . "',
                            'PENDING',
                            '" . $conn->real_escape_string($invToken) . "',
                            NOW(),
                            NOW()
                        )";
                @$conn->query($sql);
            } elseif (isset($dbType) && $dbType === 'sqlite') {
                try {
                    $stmt = $conn->prepare("INSERT OR REPLACE INTO `transaksi`
                        (`referenceId`, `userName`, `userEmail`, `userPhone`, `remarks`, `payAmount`, `items`, `invoiceId`, `status`, `aiyo_access_token`, `created_at`, `updated_at`)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'PENDING', ?, datetime('now'), datetime('now'))");
                    $stmt->execute([
                        $referenceId, $userName, $userEmail, $userPhone, $remarks, $payAmount, $itemsJson, $invId, $invToken
                    ]);
                } catch (\Throwable $eDb) {}
            }
        }
    }
}

// 7. Ambil info invoice utama & dukung mode Auto-Redirect
$invoiceData = $resObj->responseData ?? null;
$invoiceURL  = $invoiceData->invoiceURL ?? null;
$invoiceId   = $invoiceData->invoiceId ?? null;
$vaNumber    = $invoiceData->paymentMethod->paymentAccountNumber ?? null;

// Cek apakah request meminta auto-redirect (misal dari WebViewer, browser, atau parameter redirect=1)
$isAutoRedirect = isset($_REQUEST['redirect']) && in_array(strtolower((string)$_REQUEST['redirect']), ['1', 'true', 'yes'], true);

if ($isAutoRedirect && !empty($invoiceURL)) {
    // Mode Auto-Redirect: Langsung buka URL invoice AiYO di browser / tab baru
    header("Location: " . $invoiceURL);
    echo '<!DOCTYPE html><html><head><meta charset="utf-8">';
    echo '<title>Membuka Invoice AiYO...</title>';
    echo '<meta http-equiv="refresh" content="0;url=' . htmlspecialchars($invoiceURL) . '">';
    echo '<script>window.location.href = "' . addslashes($invoiceURL) . '";</script>';
    echo '</head><body>';
    echo '<p>Membuka invoice pembayaran... Jika tidak terbuka otomatis, <a href="' . htmlspecialchars($invoiceURL) . '" target="_blank">klik di sini</a>.</p>';
    echo '</body></html>';
    exit;
}

// 8. Kembalikan response JSON ke MIT App Inventor
header('Content-Type: application/json; charset=utf-8');
if ($responseCreateInvoice !== false && !empty($responseCreateInvoice)) {
    if ($resObj && isset($resObj->responseCode)) {
        // Enriched JSON: sertakan invoiceURL, url, invoiceId, vaNumber di level teratas
        // agar MIT App Inventor bisa langsung ambil nilainya tanpa perlu traversal nested key yang rumit
        $enrichedResponse = [
            'responseCode'    => $resObj->responseCode,
            'responseMessage' => $resObj->responseMessage ?? 'Success',
            'invoiceURL'      => $invoiceURL,
            'invoiceUrl'      => $invoiceURL,
            'url'             => $invoiceURL,
            'invoiceId'       => $invoiceId,
            'vaNumber'        => $vaNumber,
            'responseData'    => $invoiceData
        ];
        echo json_encode($enrichedResponse, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    } else {
        echo $responseCreateInvoice;
    }
} else {
    echo json_encode([
        'responseCode'    => '5000000',
        'responseMessage' => 'Gagal menghubungi AiYO Gateway: ' . ($curlError ?: 'Koneksi timeout'),
        'httpCode'        => $httpCode
    ], JSON_PRETTY_PRINT);
}

