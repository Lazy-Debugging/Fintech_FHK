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
$payAmount   = (int) ($_POST['payAmount'] ?? $_GET['payAmount'] ?? 3500);
$userName    = $_POST['userName'] ?? $_GET['userName'] ?? 'Pengunjung Kios';
$userEmail   = $_POST['userEmail'] ?? $_GET['userEmail'] ?? 'customer@fhk.id';
$userPhone   = $_POST['userPhone'] ?? $_GET['userPhone'] ?? '0812000000';
$referenceId = $_POST['referenceId'] ?? $_GET['referenceId'] ?? ('FHK' . date('ymdHis') . rand(10, 99));
$remarks     = $_POST['remarks'] ?? $_GET['remarks'] ?? "Refill Air {$waterType} {$volumeMl}ml";
$requestedFormat = $_GET['format'] ?? $_POST['format'] ?? '';
$isJson = ($requestedFormat === 'json') || (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json'));

// 2. Daftar variasi metode pembayaran yang dicoba ke AiYO
// null (General Invoice resmi AiYO) paling pertama agar langsung sukses tanpa delay penolakan bankCode
$paymentOptions = [
    null,                                      // Opsi 1: General Invoice resmi AiYO (Slide 12-14)
    ['type' => 'QRIS'],                       // Opsi 2: QRIS direct
    ['type' => 'QRIS', 'bankCode' => '503'],  // Opsi 3: Slide 8
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

        if ($dbType === 'mysql') {
            $sql = "INSERT INTO `transaksi`
                    (`referenceId`, `userName`, `userEmail`, `userPhone`, `remarks`, `payAmount`, `items`, `invoiceId`, `status`, `timestamp`)
                    VALUES (
                        '" . $conn->real_escape_string($successfulBody['referenceId']) . "',
                        '" . $conn->real_escape_string($successfulBody['userName']) . "',
                        '" . $conn->real_escape_string($successfulBody['userEmail']) . "',
                        '" . $conn->real_escape_string($successfulBody['userPhone']) . "',
                        '" . $conn->real_escape_string($remarksClean) . "',
                        '" . $conn->real_escape_string($successfulBody['payAmount']) . "',
                        '" . $conn->real_escape_string($itemsJson) . "',
                        '" . $conn->real_escape_string($invoiceId) . "',
                        'NEW',
                        current_timestamp()
                    )";
            $conn->query($sql);
            $conn->close();
        } elseif ($dbType === 'sqlite') {
            try {
                $stmt = $conn->prepare("INSERT OR REPLACE INTO `transaksi`
                    (`referenceId`, `userName`, `userEmail`, `userPhone`, `remarks`, `payAmount`, `items`, `invoiceId`, `status`, `created_at`, `updated_at`)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'NEW', datetime('now'), datetime('now'))");
                $stmt->execute([
                    $successfulBody['referenceId'],
                    $successfulBody['userName'],
                    $successfulBody['userEmail'],
                    $successfulBody['userPhone'],
                    $remarksClean,
                    $successfulBody['payAmount'],
                    $itemsJson,
                    $invoiceId
                ]);
            } catch (Exception $e) {}
        }
    }
}

// 5. Output Format JSON (Jika dipanggil oleh Kios / Service)
if ($isJson) {
    header('Content-Type: application/json; charset=utf-8');
    if (!empty($invoiceId)) {
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
    } else {
        http_response_code(400);
        echo json_encode([
            'success'      => false,
            'responseCode' => $invoice->responseCode ?? '4000000',
            'message'      => $invoice->responseMessage ?? 'Gagal membuat tagihan invoice di AiYO',
            'raw_response' => $invoice ?: $responseCreateInvoice
        ], JSON_PRETTY_PRINT);
    }
    exit;
}

// 6. Output HTML Biasa Sesuai Slide 25 & 26 (atau redirect langsung jika dipanggil dari Kios)
if (!empty($invoiceId) && !empty($invoiceAccessToken)) {
    if (empty($invoiceURL)) {
        $invoiceURL = "https://bills-invoice.aiyo.id/bills/invoice/{$invoiceId}?accessToken=" . urlencode($invoiceAccessToken);
    }

    // Tampilkan halaman konfirmasi resmi sesuai modul Slide 25 & 26 dengan tombol QRIS dan auto-redirect
    echo "<!DOCTYPE html>";
    echo "<html lang='id'>";
    echo "<head>";
    echo "<meta charset='UTF-8'>";
    echo "<meta name='viewport' content='width=device-width, initial-scale=1.0'>";
    echo "<title>Invoice AiYO QRIS Berhasil Dibuat</title>";
    echo "</head>";
    echo "<body style='background:#f8fafc; font-family:-apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, sans-serif; margin:0; padding:20px;'>";
    echo "<div style='padding: 24px; max-width: 580px; margin: 30px auto; border: 1px solid #10b981; border-radius: 16px; background: #ffffff; box-shadow: 0 10px 25px rgba(0,0,0,0.08);'>";
    echo "<div style='display:flex; align-items:center; gap:12px; margin-bottom: 16px;'>";
    echo "<div style='background:#ecfdf5; border-radius:50%; width:44px; height:44px; display:flex; align-items:center; justify-content:center; font-size:22px;'>✅</div>";
    echo "<div>";
    echo "<h2 style='margin:0; color:#047857; font-size: 20px;'>Invoice AiYO QRIS Berhasil Dibuat</h2>";
    echo "<p style='margin:2px 0 0; color:#64748b; font-size: 13px;'>Diterbitkan langsung oleh AiYO Bills Invoice Gateway</p>";
    echo "</div>";
    echo "</div>";

    echo "<div style='background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:16px; margin:16px 0; font-size: 14px; line-height: 1.8;'>";
    echo "<div style='display:flex; justify-content:space-between;'><span style='color:#64748b;'>Pesanan:</span><strong style='color:#0f172a;'>Refill Air " . htmlspecialchars($waterType) . " " . htmlspecialchars($volumeMl) . " ml</strong></div>";
    echo "<div style='display:flex; justify-content:space-between;'><span style='color:#64748b;'>Invoice ID:</span><strong style='color:#0284c7; font-family:monospace;'>" . htmlspecialchars($invoiceId) . "</strong></div>";
    echo "<div style='display:flex; justify-content:space-between;'><span style='color:#64748b;'>Metode:</span><span style='background:#dbeafe; color:#1e40af; padding:2px 8px; border-radius:6px; font-weight:bold; font-size:12px;'>QRIS Dinamis</span></div>";
    echo "<div style='display:flex; justify-content:space-between;'><span style='color:#64748b;'>URL Callback:</span><code style='font-size:11px;'>" . htmlspecialchars($callbackUrl) . "</code></div>";
    echo "<div style='display:flex; justify-content:space-between; border-top:1px dashed #cbd5e1; padding-top:8px; margin-top:8px;'><span style='color:#64748b; font-weight:bold;'>Total Tagihan:</span><strong style='color:#059669; font-size:20px;'>Rp " . number_format($payAmount, 0, ',', '.') . "</strong></div>";
    echo "</div>";

    echo "<div style='display:flex; flex-direction:column; gap:10px; margin-top:20px;'>";
    echo "<a id='btn-pay-aiyo' href='" . htmlspecialchars($invoiceURL) . "' style='display:block; text-align:center; padding:14px; background:#0284c7; color:white; text-decoration:none; border-radius:10px; font-weight:bold; font-size:15px; box-shadow:0 4px 12px rgba(2,132,199,0.3);'>📱 Buka Halaman Pembayaran QRIS AiYO &rarr;</a>";
    echo "<a href='cek.php?invoiceId=" . urlencode($invoiceId) . "&accessToken=" . urlencode($invoiceAccessToken) . "' style='display:block; text-align:center; padding:12px; background:#f1f5f9; color:#334155; text-decoration:none; border-radius:10px; font-weight:bold; font-size:13px; border:1px solid #cbd5e1;'>🔍 Cek Status Invoice (cek.php)</a>";
    echo "</div>";

    echo "<p style='text-align:center; font-size:12px; color:#94a3b8; margin-top:16px;'>Dialihkan otomatis ke AiYO Gateway dalam <span id='countdown'>2</span> detik...</p>";

    echo "<script>";
    echo "let count = 2;";
    echo "const timer = setInterval(() => {";
    echo "    count--;";
    echo "    const el = document.getElementById('countdown');";
    echo "    if (el) el.textContent = count;";
    echo "    if (count <= 0) {";
    echo "        clearInterval(timer);";
    echo "        window.location.href = '" . addslashes($invoiceURL) . "';";
    echo "    }";
    echo "}, 1000);";
    echo "</script>";
    echo "</div>";
    echo "</body>";
    echo "</html>";
} else {
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
