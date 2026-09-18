<?php
/**
 * Pembuatan Invoice QRIS Dinamis AiYO Payment Gateway
 * Sesuai dengan Meeting 03 Slide 8, 23, 24, 25 (respon.php)
 */

include_once __DIR__ . "/token.php";

// Parameter input (bisa via POST, GET, atau default)
$waterType   = $_POST['water_type'] ?? $_GET['water_type'] ?? 'COLD';
$volumeMl    = (int) ($_POST['volume_ml'] ?? $_GET['volume_ml'] ?? 500);
$payAmount   = (int) ($_POST['payAmount'] ?? $_GET['payAmount'] ?? 3500);
$userName    = $_POST['userName'] ?? $_GET['userName'] ?? 'Pengunjung Kios';
$userEmail   = $_POST['userEmail'] ?? $_GET['userEmail'] ?? 'customer@fhk.id';
$userPhone   = $_POST['userPhone'] ?? $_GET['userPhone'] ?? '0812000000';
$referenceId = $_POST['referenceId'] ?? $_GET['referenceId'] ?? ('FHK' . date('ymdHis') . rand(10, 99));
$remarks     = $_POST['remarks'] ?? $_GET['remarks'] ?? "Refill Air {$waterType} {$volumeMl}ml";

// ==== Susun body Create Invoice (Slide 23 & Slide 8: QRIS bankCode 503) ====
$bodyCreateInvoice = array(
    "invoiceName"  => "FHK {$waterType} {$volumeMl}ml",
    "referenceId"  => $referenceId,
    "userName"     => $userName,
    "userEmail"    => $userEmail,
    "userPhone"    => $userPhone,
    "remarks"      => $remarks,
    "payAmount"    => $payAmount,
    "expireTime"   => date('Y-m-d\TH:i', strtotime('+3 hour')), // Sesuai format slide 23
    "billMasterId" => $billMasterId,
    "paymentMethod" => array(
        "type"     => "QRIS", // Sesuai Slide 8: type 'QRIS'
        "bankCode" => "503"   // Sesuai Slide 8: bankCode '503' untuk Create Invoice w/ QRIS
    ),
    "items" => array(
        array(
            "itemName"       => "Air Minum {$waterType} {$volumeMl}ml (UV Sterilized)",
            "itemType"       => "ITEM",
            "itemCount"      => "1",
            "itemTotalPrice" => (string) $payAmount,
        )
    )
);

// ==== Hitung HMAC-SHA256 signature (Slide 24) ====
$pathInvoice = '/api/v1/invoice';
$urlCreateInvoice = $host . $pathInvoice;
$signRelativeURLCreateInvoice = parse_url($urlCreateInvoice, PHP_URL_PATH);
$rawBodyCreateInvoice = json_encode($bodyCreateInvoice);
$dataToSignCreateInvoice = $api_key . $signRelativeURLCreateInvoice . $rawBodyCreateInvoice;
$signatureCreateInvoice = hash_hmac('sha256', $dataToSignCreateInvoice, $api_secret);

// ==== Panggil API Create Invoice dengan cURL (Slide 24) ====
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
curl_setopt($chCreateInvoice, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
curl_setopt($chCreateInvoice, CURLOPT_HTTPHEADER, $headersCreateInvoice);
curl_setopt($chCreateInvoice, CURLOPT_POSTFIELDS, $rawBodyCreateInvoice);
$sentHeaders = curl_getinfo($chCreateInvoice, CURLINFO_HEADER_OUT);
$responseCreateInvoice = curl_exec($chCreateInvoice);
$httpCode = curl_getinfo($chCreateInvoice, CURLINFO_HTTP_CODE);
curl_close($chCreateInvoice);

// ==== Proses hasil & simpan ke database (Slide 25) ====
$invoice = json_decode($responseCreateInvoice);
$invoiceId = null;
$invoiceAccessToken = null;
$qrContent = null;
$invoiceURL = null;

if ($invoice && isset($invoice->responseCode) && $invoice->responseCode == '2000000') {
    $invoiceId          = $invoice->responseData->invoiceId ?? null;
    $invoiceAccessToken = $invoice->responseData->accessToken ?? null;
    $qrContent          = $invoice->responseData->qrContent ?? $invoice->responseData->qrString ?? null;
    $invoiceURL         = $invoice->responseData->invoiceURL ?? null;

    include_once __DIR__ . "/db_config.php";

    if ($conn) {
        $itemsJson = json_encode($bodyCreateInvoice['items']);
        $remarksClean = str_replace(array('\'', '"', ',', ';', '<', '>', '/'), ' ', $bodyCreateInvoice['remarks']);

        if ($dbType === 'mysql') {
            $sql = "INSERT INTO `transaksi`
                    (`referenceId`, `userName`, `userEmail`, `userPhone`, `remarks`, `payAmount`, `items`, `invoiceId`, `status`, `timestamp`)
                    VALUES (
                        '" . $conn->real_escape_string($bodyCreateInvoice['referenceId']) . "',
                        '" . $conn->real_escape_string($bodyCreateInvoice['userName']) . "',
                        '" . $conn->real_escape_string($bodyCreateInvoice['userEmail']) . "',
                        '" . $conn->real_escape_string($bodyCreateInvoice['userPhone']) . "',
                        '" . $conn->real_escape_string($remarksClean) . "',
                        '" . $conn->real_escape_string($bodyCreateInvoice['payAmount']) . "',
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
                    $bodyCreateInvoice['referenceId'],
                    $bodyCreateInvoice['userName'],
                    $bodyCreateInvoice['userEmail'],
                    $bodyCreateInvoice['userPhone'],
                    $remarksClean,
                    $bodyCreateInvoice['payAmount'],
                    $itemsJson,
                    $invoiceId
                ]);
            } catch (Exception $e) {
                // Ignore query error for script display
            }
        }
    }
}

// ==== Output Tampilan Sesuai Slide 25 & 26 ====
if (!empty($invoiceId) && !empty($invoiceAccessToken)) {
    echo "<div style='font-family: sans-serif; padding: 20px; max-width: 600px; margin: 20px auto; border: 1px solid #10b981; border-radius: 12px; background: #f0fdf4;'>";
    echo "<h2 style='color: #047857;'>✅ Invoice Berhasil Dibuat (AiYO QRIS)!</h2>";
    echo "<p><strong>Invoice ID:</strong> " . htmlspecialchars($invoiceId) . "</p>";
    echo "<p><strong>Access Token:</strong> " . htmlspecialchars($invoiceAccessToken) . "</p>";
    echo "<p><strong>Metode Pembayaran:</strong> QRIS (BankCode: 503)</p>";
    echo "<p><strong>Nominal:</strong> Rp " . number_format($payAmount, 0, ',', '.') . "</p>";
    echo "<p><strong>URL Callback:</strong> <code>{$callbackUrl}</code></p>";
    echo "<hr style='border: 0; border-top: 1px dashed #6ee7b7; margin: 15px 0;'>";
    echo "<a style='display:inline-block; padding: 10px 18px; background: #059669; color: white; text-decoration: none; border-radius: 6px; font-weight: bold;' href='cek.php?invoiceId=" . urlencode($invoiceId) . "&accessToken=" . urlencode($invoiceAccessToken) . "'>🔍 Cek Status Invoice (cek.php)</a>";
    if (!empty($invoiceURL)) {
        echo " &nbsp; <a style='display:inline-block; padding: 10px 18px; background: #2563eb; color: white; text-decoration: none; border-radius: 6px; font-weight: bold;' href='" . htmlspecialchars($invoiceURL) . "' target='_blank'>📱 Buka Halaman QRIS AiYO</a>";
    }
    echo "</div>";
} else {
    echo "<div style='font-family: sans-serif; padding: 20px; max-width: 600px; margin: 20px auto; border: 1px solid #ef4444; border-radius: 12px; background: #fef2f2;'>";
    echo "<h2 style='color: #b91c1c;'>❌ Gagal Membuat Invoice AiYO</h2>";
    $msg = $invoice->responseMessage ?? 'Terjadi kesalahan komunikasi dengan AiYO Gateway';
    echo "<p><strong>Pesan Gateway:</strong> " . htmlspecialchars($msg) . "</p>";
    
    // Bantuan jika terkena IP Whitelist
    if (str_contains($msg, 'IP Address Not Allowed')) {
        preg_match('/([0-9a-fA-F\.:]+)$/', $msg, $ipMatch);
        $clientIp = $ipMatch[1] ?? 'IP Anda';
        echo "<div style='background: #fff; padding: 12px; border-radius: 6px; border-left: 4px solid #f59e0b; margin-top: 15px;'>";
        echo "<p style='margin:0; font-size: 14px;'><strong>💡 Catatan Whitelist IP AiYO:</strong></p>";
        echo "<p style='font-size: 13px; color: #475569; margin: 6px 0;'>IP Publik server/komputer Anda adalah: <code>{$clientIp}</code>.<br/>Sesuai Slide 16 & 17, login ke <a href='https://bills.aiyo.id/' target='_blank'>https://bills.aiyo.id/</a> lalu daftarkan IP tersebut ke daftar Allowed IP untuk Merchant Anda.</p>";
        echo "</div>";
    }
    echo "<p style='font-size: 12px; color: #64748b; margin-top: 15px;'>Raw Response: <pre style='background:#f8fafc; padding:10px; border-radius:4px; overflow-x:auto;'>" . htmlspecialchars($responseCreateInvoice ?: 'Tidak ada respon') . "</pre></p>";
    echo "</div>";
}
?>
