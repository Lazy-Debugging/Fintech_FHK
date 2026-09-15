<?php
include "aiyo_config.php";

$invoiceId   = isset($_GET['invoiceId']) ? trim($_GET['invoiceId']) : '';
$accessToken = isset($_GET['accessToken']) ? trim($_GET['accessToken']) : '';

if (empty($invoiceId) || empty($accessToken)) {
    echo "<h3>Parameter Belum Lengkap</h3>";
    echo "<p>Halaman <code>cek.php</code> membutuhkan parameter <code>invoiceId</code> dan <code>accessToken</code> di URL.</p>";
    echo "<p>Contoh URL:<br/><code>cek.php?invoiceId=lG11jHM37rMqBrEwVzWX&accessToken=20lbVDH3hVuL2YiU0oDXOofNEepR8j5evfDnTzGBQV3JOqgEEI</code></p>";
    echo "<p><a href='respon.php'>Jalankan respon.php untuk membuat Invoice baru</a></p>";
    exit;
}

$pathInvoice  = '/api/v1/invoice';
$URLCekStatus = $host . $pathInvoice . "/" . $invoiceId . "?accessToken=" . $accessToken;

$chCekInvoice = curl_init($URLCekStatus);
curl_setopt($chCekInvoice, CURLOPT_TIMEOUT, 30);
curl_setopt($chCekInvoice, CURLOPT_RETURNTRANSFER, TRUE);
$responseCekInvoice = curl_exec($chCekInvoice);
curl_close($chCekInvoice);

$cekInvoice = json_decode($responseCekInvoice);

if ($cekInvoice && isset($cekInvoice->responseData)) {
    $resData     = $cekInvoice->responseData;
    $status      = isset($resData->invoiceStatus) ? $resData->invoiceStatus : '-';
    $invoiceName = isset($resData->invoiceName) ? $resData->invoiceName : '-';
    $payAmount   = isset($resData->payAmount) ? $resData->payAmount : '-';
    $invoiceURL  = isset($resData->invoiceURL) ? $resData->invoiceURL : '#';

    echo "Invoice: " . htmlspecialchars($invoiceName);
    echo "<br/>Senilai: " . htmlspecialchars($payAmount);
    echo "<br/>Status: " . htmlspecialchars($status);
    echo "<br/><br/><a href='" . htmlspecialchars($invoiceURL) . "' target='_blank'>Lanjutkan pembayaran</a>";
} else {
    echo "<h3>Gagal Mengambil Data Invoice</h3>";
    if ($cekInvoice && isset($cekInvoice->responseMessage)) {
        echo "<p>Message: " . htmlspecialchars($cekInvoice->responseMessage) . "</p>";
    } else {
        echo "<p>Response server: " . htmlspecialchars($responseCekInvoice) . "</p>";
    }
}
?>
