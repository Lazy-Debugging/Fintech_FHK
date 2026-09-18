<?php
/**
 * Memeriksa Status Pembayaran Invoice
 * Sesuai dengan Meeting 03 Slide 27 & 28 (cek.php)
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
curl_setopt($chCekInvoice, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
$responseCekInvoice = curl_exec($chCekInvoice);
curl_close($chCekInvoice);

$cekInvoice = json_decode($responseCekInvoice);

echo "<div style='font-family: sans-serif; padding: 20px; max-width: 600px; margin: 20px auto; border: 1px solid #cbd5e1; border-radius: 12px; background: #ffffff; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1);'>";

if ($cekInvoice && isset($cekInvoice->responseData)) {
    $resData     = $cekInvoice->responseData;
    $status      = isset($resData->invoiceStatus) ? $resData->invoiceStatus : '-';
    $invoiceName = isset($resData->invoiceName) ? $resData->invoiceName : '-';
    $payAmount   = isset($resData->payAmount) ? $resData->payAmount : '-';
    $invoiceURL  = isset($resData->invoiceURL) ? $resData->invoiceURL : '#';

    echo "<h2 style='margin-top:0; color:#1e293b;'>Status Tagihan Invoice</h2>";
    echo "<p><strong>Invoice:</strong> " . htmlspecialchars($invoiceName) . "</p>";
    echo "<p><strong>Senilai:</strong> Rp " . number_format((int)$payAmount, 0, ',', '.') . "</p>";
    
    $badgeColor = ($status === 'PAID' || $status === 'COMPLETED') ? '#059669' : '#d97706';
    echo "<p><strong>Status:</strong> <span style='display:inline-block; padding: 3px 10px; border-radius: 9999px; background: {$badgeColor}; color: white; font-weight: bold; font-size: 12px;'>" . htmlspecialchars($status) . "</span></p>";
    
    echo "<hr style='border:0; border-top: 1px solid #e2e8f0; margin: 15px 0;'>";
    echo "<a style='display:inline-block; padding: 10px 18px; background: #2563eb; color: white; text-decoration: none; border-radius: 6px; font-weight: bold;' href='" . htmlspecialchars($invoiceURL) . "' target='_blank'>Lanjutkan pembayaran &rarr;</a>";
} else {
    echo "<h3 style='color:#dc2626;'>Gagal Mengambil Data Invoice</h3>";
    if ($cekInvoice && isset($cekInvoice->responseMessage)) {
        echo "<p>Message: " . htmlspecialchars($cekInvoice->responseMessage) . "</p>";
    } else {
        echo "<p>Response server: " . htmlspecialchars($responseCekInvoice) . "</p>";
    }
}

echo "</div>";
?>
