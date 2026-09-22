<?php
/**
 * Pembuatan Invoice QRIS Dinamis AiYO Payment Gateway
 * Sesuai dengan Meeting 03 Slide 8, 23, 24, 25 (respon.php)
 * Mendukung Adaptive Channel Fallback & Format JSON
 */

include_once __DIR__ . "/token.php";

// 1. Parameter input
$waterType   = $_POST['water_type'] ?? $_GET['water_type'] ?? 'COLD';
$volumeMl    = (int) ($_POST['volume_ml'] ?? $_GET['volume_ml'] ?? 500);
$payAmountInput = $_POST['payAmount'] ?? $_GET['payAmount'] ?? null;
$priceMatrix = [
    'COLD'   => [ 250 => 2000, 500 => 3500, 1000 => 6000 ],
    'NORMAL' => [ 250 => 1500, 500 => 2500, 1000 => 4500 ],
];
$wtUpper = strtoupper($waterType);
$kioskIdInput = $_POST['kiosk_id'] ?? $_GET['kiosk_id'] ?? null;

// Harga dari database (KioskPricing) dengan fallback ke hardcoded matrix
try {
    if (class_exists(\App\Models\KioskPricing::class)) {
        $calculatedPrice = \App\Models\KioskPricing::getPrice($wtUpper, $volumeMl, $kioskIdInput);
    } else {
        $calculatedPrice = $priceMatrix[$wtUpper][$volumeMl] ?? 3500;
    }
} catch (\Throwable $e) {
    $calculatedPrice = $priceMatrix[$wtUpper][$volumeMl] ?? 3500;
}

if ($payAmountInput !== null && is_numeric($payAmountInput)) {
    $payAmount = (int) $payAmountInput;
} else {
    $payAmount = $calculatedPrice;
}
$referenceId = $_POST['referenceId'] ?? $_GET['referenceId'] ?? ('FHK' . date('ymdHis') . rand(10, 99));

// Deteksi Akun Pelanggan (User vs Guest)
$userName   = $_POST['userName'] ?? $_GET['userName'] ?? '';
$userEmail  = $_POST['userEmail'] ?? $_GET['userEmail'] ?? '';
$userPhone  = $_POST['userPhone'] ?? $_GET['userPhone'] ?? '';
$guestToken = $_SESSION['guest_order_id'] ?? $_COOKIE['guest_order_id'] ?? ($_REQUEST['guest_token'] ?? null);
$userId     = null;

if (function_exists('auth') && auth()->check()) {
    $u = auth()->user();
    $userId = $u->id;
    if (empty($userName) || $userName === 'Pengunjung Kios') $userName = $u->name;
    if (empty($userEmail) || $userEmail === 'customer@fhk.id') $userEmail = $u->email;
    if (empty($userPhone) || $userPhone === '0812000000') $userPhone = $u->phone ?? '';
} else {
    $guestHash = substr(md5($referenceId), 0, 6);
    if (empty($userName)) $userName = "Pengunjung Tamu FHK (#{$guestHash})";
    if (empty($userEmail)) $userEmail = "tamu.{$guestHash}@fhk.id";
}

$remarks     = $_POST['remarks'] ?? $_GET['remarks'] ?? "Refill Air {$waterType} {$volumeMl}ml";
$requestedFormat = $_GET['format'] ?? $_POST['format'] ?? '';
$isJson = ($requestedFormat === 'json') || (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json'));

// 2. Daftar variasi metode pembayaran yang dicoba ke AiYO
// null (General Invoice resmi AiYO) paling pertama agar langsung sukses tanpa delay penolakan bankCode
$paymentOptions = [
    null,                                      // Opsi 1: General Invoice resmi AiYO (Slide 12-14)
    ['type' => 'QRIS'],                        // Opsi 2: QRIS direct
    ['type' => 'QRIS', 'bankCode' => '503'],   // Opsi 3: Slide 8
];

// Jika user meminta tipe spesifik lewat URL (?type=none atau ?type=QRIS)
if (isset($_GET['type'])) {
    if ($_GET['type'] === 'none') {
        $paymentOptions = [null];
    } elseif ($_GET['type'] === 'QRIS_NO_BANK') {
        $paymentOptions = [['type' => 'QRIS']];
    } elseif ($_GET['type'] === 'QRIS') {
        $paymentOptions = [['type' => 'QRIS', 'bankCode' => $_GET['bankCode'] ?? '503']];
    } elseif ($_GET['type'] === 'VA_CLOSED') {
        $paymentOptions = [['type' => 'VA_CLOSED', 'bankCode' => $_GET['bankCode'] ?? '022']];
    }
}

$invoice = null;
$responseCreateInvoice = '';
$httpCode = 0;
$successfulBody = null;

// 3. Eksekusi Create Invoice ke AiYO API dengan fallback adaptif
foreach ($paymentOptions as $opt) {
    $bodyCreateInvoice = [
        "invoiceName"  => "FHK {$waterType} {$volumeMl}ml",
        "referenceId"  => $referenceId,
        "userName"     => $userName,
        "userEmail"    => $userEmail,
        "userPhone"    => $userPhone,
        "remarks"      => $remarks,
        "payAmount"    => $payAmount,
        "expireTime"   => date('Y-m-d\TH:i', strtotime('+3 hour')),
        "billMasterId" => $billMasterId,
        "items"        => [
            [
                "itemName"       => "Air Minum {$waterType} {$volumeMl}ml (UV Sterilized)",
                "itemType"       => "ITEM",
                "itemCount"      => "1",
                "itemTotalPrice" => (string) $payAmount,
            ]
        ]
    ];

    if ($opt !== null) {
        $bodyCreateInvoice["paymentMethod"] = $opt;
    }

    $pathInvoice = '/api/v1/invoice';
    $urlCreateInvoice = $host . $pathInvoice;
    $signRelativeURLCreateInvoice = parse_url($urlCreateInvoice, PHP_URL_PATH);
    $rawBodyCreateInvoice = json_encode($bodyCreateInvoice);
    $dataToSignCreateInvoice = $api_key . $signRelativeURLCreateInvoice . $rawBodyCreateInvoice;
    $signatureCreateInvoice = hash_hmac('sha256', $dataToSignCreateInvoice, $api_secret);

    $chCreateInvoice = curl_init($urlCreateInvoice);
    $headersCreateInvoice = [
        "Content-Type: application/json",
        "Authorization: Bearer " . $accessToken,
        "x-aiyo-key: " . $api_key,
        "x-aiyo-signature: " . $signatureCreateInvoice
    ];
    curl_setopt($chCreateInvoice, CURLOPT_TIMEOUT, 12);
    curl_setopt($chCreateInvoice, CURLOPT_POST, 1);
    curl_setopt($chCreateInvoice, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($chCreateInvoice, CURLOPT_SSL_VERIFYPEER, FALSE);
    curl_setopt($chCreateInvoice, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($chCreateInvoice, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
    curl_setopt($chCreateInvoice, CURLOPT_HTTPHEADER, $headersCreateInvoice);
    curl_setopt($chCreateInvoice, CURLOPT_POSTFIELDS, $rawBodyCreateInvoice);
    $responseCreateInvoice = curl_exec($chCreateInvoice);
    $httpCode = curl_getinfo($chCreateInvoice, CURLINFO_HTTP_CODE);
    curl_close($chCreateInvoice);

    $invoice = json_decode($responseCreateInvoice);
    if ($invoice && isset($invoice->responseCode) && $invoice->responseCode == '2000000') {
        $successfulBody = $bodyCreateInvoice;
        break; // Sukses, hentikan pengulangan
    }

    // Jika error bukan "Payment Bank Not Allowed" (misal Unauthorized IP), jangan ulangi
    if (!$invoice || !isset($invoice->responseMessage) || !str_contains($invoice->responseMessage, 'Bank Not Allowed')) {
        break;
    }
}

// 4. Proses respon sukses & simpan ke database
$invoiceId          = $invoice->responseData->invoiceId ?? null;
$invoiceAccessToken = $invoice->responseData->accessToken ?? null;
$qrContent          = $invoice->responseData->qrContent ?? $invoice->responseData->qrString ?? null;
$invoiceURL         = $invoice->responseData->invoiceURL ?? null;

if (!empty($invoiceId) && !empty($invoiceAccessToken)) {
    include_once __DIR__ . "/db_config.php";

    if ($conn && $successfulBody) {
        $itemsJson = json_encode($successfulBody['items']);
        $remarksClean = str_replace(array('\'', '"', ',', ';', '<', '>', '/'), ' ', $successfulBody['remarks']);

        $userIdVal = $userId ? "'" . $conn->real_escape_string($userId) . "'" : "NULL";
        $guestTokenVal = $guestToken ? "'" . $conn->real_escape_string($guestToken) . "'" : "NULL";
        $kioskIdVal = !empty($kioskIdInput) ? "'" . $conn->real_escape_string($kioskIdInput) . "'" : "'FHK-JAKARTA-01'";

        if ($dbType === 'mysql') {
            $sql = "INSERT INTO `transaksi`
                    (`referenceId`, `userName`, `userEmail`, `userPhone`, `remarks`, `payAmount`, `items`, `invoiceId`, `status`, `water_type`, `volume_ml`, `kiosk_id`, `user_id`, `guest_token`, `aiyo_access_token`, `created_at`, `updated_at`, `timestamp`)
                    VALUES (
                        '" . $conn->real_escape_string($successfulBody['referenceId']) . "',
                        '" . $conn->real_escape_string($successfulBody['userName']) . "',
                        '" . $conn->real_escape_string($successfulBody['userEmail']) . "',
                        '" . $conn->real_escape_string($successfulBody['userPhone']) . "',
                        '" . $conn->real_escape_string($remarksClean) . "',
                        '" . $conn->real_escape_string($successfulBody['payAmount']) . "',
                        '" . $conn->real_escape_string($itemsJson) . "',
                        '" . $conn->real_escape_string($invoiceId) . "',
                        'PENDING',
                        '" . $conn->real_escape_string($wtUpper) . "',
                        " . ((int) $volumeMl) . ",
                        {$kioskIdVal},
                        {$userIdVal},
                        {$guestTokenVal},
                        '" . $conn->real_escape_string($invoiceAccessToken) . "',
                        NOW(),
                        NOW(),
                        current_timestamp()
                    )";
            $conn->query($sql);
            $conn->close();
        } elseif ($dbType === 'sqlite') {
            try {
                $stmt = $conn->prepare("INSERT OR REPLACE INTO `transaksi`
                    (`referenceId`, `userName`, `userEmail`, `userPhone`, `remarks`, `payAmount`, `items`, `invoiceId`, `status`, `water_type`, `volume_ml`, `kiosk_id`, `user_id`, `guest_token`, `aiyo_access_token`, `created_at`, `updated_at`)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'PENDING', ?, ?, ?, ?, ?, ?, datetime('now'), datetime('now'))");
                $stmt->execute([
                    $successfulBody['referenceId'],
                    $successfulBody['userName'],
                    $successfulBody['userEmail'],
                    $successfulBody['userPhone'],
                    $remarksClean,
                    $successfulBody['payAmount'],
                    $itemsJson,
                    $invoiceId,
                    $wtUpper,
                    (int) $volumeMl,
                    $kioskIdInput ?: 'FHK-JAKARTA-01',
                    $userId,
                    $guestToken,
                    $invoiceAccessToken
                ]);
            } catch (Exception $e) {}
        }
    }

    // 5. Output Format JSON (Jika dipanggil oleh Kios / Service)
    if ($isJson) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success'      => true,
            'responseCode' => '2000000',
            'invoiceId'    => $invoiceId,
            'accessToken'  => $invoiceAccessToken,
            'referenceId'  => $referenceId,
            'payAmount'    => $payAmount,
            'qrContent'    => $qrContent ?: $invoiceURL,
            'invoiceUrl'   => $invoiceURL,
            'raw_response' => $invoice
        ], JSON_PRETTY_PRINT);
        exit;
    }

    // 6. Redirect Instan 302 ke Halaman QRIS Internal Website FHK (Tanpa Layar Perantara White Screen)
    $internalQrisUrl = function_exists('route')
        ? route('kiosk.qris', ['invoiceId' => $invoiceId])
        : (function_exists('url') ? url("kiosk/qris/{$invoiceId}") : "index.php/kiosk/qris/{$invoiceId}");

    header("Location: " . $internalQrisUrl);
    exit;

} else {
    // 5b. Output JSON Error
    if ($isJson) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(400);
        echo json_encode([
            'success'      => false,
            'responseCode' => $invoice->responseCode ?? '4000000',
            'message'      => $invoice->responseMessage ?? 'Gagal membuat tagihan invoice di AiYO',
            'raw_response' => $invoice ?: $responseCreateInvoice
        ], JSON_PRETTY_PRINT);
        exit;
    }

    // 6b. Tampilkan Pesan Error HTML
    echo "<div style='font-family: sans-serif; padding: 20px; max-width: 600px; margin: 20px auto; border: 1px solid #ef4444; border-radius: 12px; background: #fef2f2;'>";
    echo "<h2 style='color: #b91c1c;'>❌ Gagal Membuat Invoice AiYO</h2>";
    $msg = $invoice->responseMessage ?? 'Terjadi kesalahan komunikasi dengan AiYO Gateway';
    echo "<p><strong>Pesan Gateway:</strong> " . htmlspecialchars($msg) . "</p>";

    if (str_contains($msg, 'IP Address Not Allowed')) {
        preg_match('/([0-9a-fA-F\.:]+)$/', $msg, $ipMatch);
        $clientIp = $ipMatch[1] ?? 'IP Anda';
        echo "<div style='background: #fff; padding: 12px; border-radius: 6px; border-left: 4px solid #f59e0b; margin-top: 15px;'>";
        echo "<p style='margin:0; font-size: 14px;'><strong>💡 Catatan Whitelist IP AiYO:</strong></p>";
        echo "<p style='font-size: 13px; color: #475569; margin: 6px 0;'>IP Publik server/komputer Anda adalah: <code>{$clientIp}</code>.<br/>Login ke <a href='https://bills.aiyo.id/' target='_blank'>https://bills.aiyo.id/</a> lalu daftarkan IP tersebut ke daftar Allowed IP.</p>";
        echo "</div>";
    }
    echo "<p style='font-size: 12px; color: #64748b; margin-top: 15px;'>Raw Response: <pre style='background:#f8fafc; padding:10px; border-radius:4px; overflow-x:auto;'>" . htmlspecialchars($responseCreateInvoice ?: 'Tidak ada respon') . "</pre></p>";
    echo "</div>";
}
?>
