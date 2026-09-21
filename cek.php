<?php
/**
 * Memeriksa Status Pembayaran Invoice
 * Sesuai dengan Meeting 03 Slide 27 & 28 (cek.php)
 * Otomatis memperbarui status di DB & Auto Redirect kembali ke Website saat Lunas
 */

include_once __DIR__ . "/aiyo_config.php";

$pathInvoice  = '/api/v1/invoice';
$invoiceId    = isset($_GET['invoiceId']) ? trim($_GET['invoiceId']) : '';
$accessToken  = isset($_GET['accessToken']) ? trim($_GET['accessToken']) : '';

if (empty($invoiceId) || empty($accessToken)) {
    echo "<div style='font-family: sans-serif; padding: 20px; max-width: 600px; margin: 20px auto;'>";
    echo "<h3>Parameter Belum Lengkap</h3>";
    echo "<p>Halaman <code>cek.php</code> membutuhkan parameter <code>invoiceId</code> dan <code>accessToken</code> di URL.</p>";
    echo "<p>Contoh URL:<br/><code>cek.php?invoiceId=lG11jHM37rMqBrEwVzWX&accessToken=20lbVDH3hVuL2YiU0oDXOofNEepR8j5evfDnTzGBQV3JOqgEEI</code></p>";
    echo "<p><a href='respon.php'>Jalankan respon.php untuk membuat Invoice baru</a></p>";
    echo "</div>";
    exit;
}

$URLCekStatus = $host . $pathInvoice . "/" . $invoiceId . "?accessToken=" . urlencode($accessToken);

$chCekInvoice = curl_init($URLCekStatus);
curl_setopt($chCekInvoice, CURLOPT_TIMEOUT, 30);
curl_setopt($chCekInvoice, CURLOPT_RETURNTRANSFER, TRUE);
curl_setopt($chCekInvoice, CURLOPT_SSL_VERIFYPEER, FALSE);
curl_setopt($chCekInvoice, CURLOPT_SSL_VERIFYHOST, 0);
curl_setopt($chCekInvoice, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
$responseCekInvoice = curl_exec($chCekInvoice);
curl_close($chCekInvoice);

$cekInvoice = json_decode($responseCekInvoice);

$isJsonFormat = (isset($_GET['format']) && $_GET['format'] === 'json')
    || (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json'));

if ($cekInvoice && isset($cekInvoice->responseData)) {
    $resData     = $cekInvoice->responseData;
    $status      = isset($resData->invoiceStatus) ? $resData->invoiceStatus : '-';
    $invoiceName = isset($resData->invoiceName) ? $resData->invoiceName : '-';
    $payAmount   = isset($resData->payAmount) ? $resData->payAmount : 0;
    $invoiceURL  = isset($resData->invoiceURL) ? $resData->invoiceURL : '#';

    $isPaid = in_array(strtoupper($status), ['PAID', 'COMPLETED', 'SETTLED', 'SUCCESS']);

    if ($isPaid) {
        // Update database
        include_once __DIR__ . "/db_config.php";
        if ($conn) {
            if ($dbType === 'mysql') {
                $conn->query("UPDATE `transaksi` SET `status` = 'PAID' WHERE `invoiceId` = '" . $conn->real_escape_string($invoiceId) . "'");
                $conn->close();
            } elseif ($dbType === 'sqlite') {
                try {
                    $stmt = $conn->prepare("UPDATE `transaksi` SET `status` = 'PAID' WHERE `invoiceId` = ?");
                    $stmt->execute([$invoiceId]);
                } catch (\Exception $e) {}
            }
        }
    }

    // ── JSON Output ──
    if ($isJsonFormat) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success'       => true,
            'invoiceId'     => $invoiceId,
            'invoiceStatus' => $status,
            'isPaid'        => $isPaid,
            'invoiceName'   => $invoiceName,
            'payAmount'     => (int) $payAmount,
            'invoiceURL'    => $invoiceURL,
        ], JSON_PRETTY_PRINT);
        exit;
    }

    // ── HTML Output ──
    echo "<div style='font-family: sans-serif; padding: 20px; max-width: 600px; margin: 20px auto; border: 1px solid #cbd5e1; border-radius: 12px; background: #ffffff; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1); text-align: center;'>";
    echo "<h2 style='margin-top:0; color:#1e293b;'>Status Tagihan Invoice</h2>";
    echo "<p><strong>Invoice:</strong> " . htmlspecialchars($invoiceName) . "</p>";
    echo "<p><strong>Senilai:</strong> Rp " . number_format((int)$payAmount, 0, ',', '.') . "</p>";
    
    $badgeColor = $isPaid ? '#059669' : '#d97706';
    echo "<p><strong>Status:</strong> <span style='display:inline-block; padding: 4px 12px; border-radius: 9999px; background: {$badgeColor}; color: white; font-weight: bold; font-size: 13px;'>" . htmlspecialchars($status) . "</span></p>";
    
    echo "<hr style='border:0; border-top: 1px solid #e2e8f0; margin: 20px 0;'>";

    if ($isPaid) {
        $redirectDest = function_exists('route') ? route('profile') : 'index.php';
        echo "<div style='background:#ecfdf5; border:1px solid #a7f3d0; color:#065f46; padding:15px; border-radius:10px; font-weight:bold; margin-bottom:15px;'>";
        echo "✅ Pembayaran Berhasil! Mengalihkan Anda kembali ke Website...";
        echo "</div>";
        echo "<a style='display:inline-block; padding: 12px 24px; background: #059669; color: white; text-decoration: none; border-radius: 8px; font-weight: bold;' href='{$redirectDest}'>Kembali ke Website &rarr;</a>";
        echo "<script>setTimeout(function(){ window.location.href = '{$redirectDest}'; }, 1500);</script>";
    } else {
        echo "<a style='display:inline-block; padding: 12px 24px; background: #2563eb; color: white; text-decoration: none; border-radius: 8px; font-weight: bold;' href='" . htmlspecialchars($invoiceURL) . "' target='_blank'>Lanjutkan Pembayaran &rarr;</a>";
    }
    echo "</div>";
} else {
    // ── JSON Error ──
    if ($isJsonFormat) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(400);
        echo json_encode([
            'success'       => false,
            'invoiceId'     => $invoiceId,
            'invoiceStatus' => 'UNKNOWN',
            'isPaid'        => false,
            'message'       => ($cekInvoice && isset($cekInvoice->responseMessage)) ? $cekInvoice->responseMessage : 'Gagal mengambil data invoice dari AiYO',
        ], JSON_PRETTY_PRINT);
        exit;
    }

    // ── HTML Error ──
    echo "<div style='font-family: sans-serif; padding: 20px; max-width: 600px; margin: 20px auto; border: 1px solid #cbd5e1; border-radius: 12px; background: #ffffff; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1); text-align: center;'>";
    echo "<h3 style='color:#dc2626;'>Gagal Mengambil Data Invoice</h3>";
    if ($cekInvoice && isset($cekInvoice->responseMessage)) {
        echo "<p>Message: " . htmlspecialchars($cekInvoice->responseMessage) . "</p>";
    } else {
        echo "<p>Response server: " . htmlspecialchars($responseCekInvoice) . "</p>";
    }
    echo "</div>";
}
?>
