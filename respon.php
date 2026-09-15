<?php
include "token.php";

// ==== Susun body Create Invoice ====
$bodyCreateInvoice = array(
    "invoiceName" => "Nama Komunitas",
    "referenceId" => "YPD" . date("mdHis"),
    "userName"    => "Ridwan Sanjaya",
    "userEmail"   => "ridwan@unika.ac.id",
    "userPhone"   => "0818000000",
    "remarks"     => "-",
    "payAmount"   => 50000,
    "expireTime"  => date('Y-m-d\TH:i', strtotime('+3 hour')),
    "billMasterId" => $billMasterId,
    "paymentMethod" => array(
        "type"     => "VA_CLOSED",
        "bankCode" => "022"
    ),
    "items" => array(
        array(
            "itemName"      => "Barang 1",
            "itemType"      => "ITEM",
            "itemCount"     => "1",
            "itemTotalPrice" => "10000",
        ),
        array(
            "itemName"      => "Barang 2",
            "itemType"      => "ITEM",
            "itemCount"     => "2",
            "itemTotalPrice" => "20000",
        ),
    )
);

// ==== Hitung signature ====
$pathInvoice = '/api/v1/invoice';
$urlCreateInvoice = $host . $pathInvoice;
$signRelativeURLCreateInvoice = parse_url($urlCreateInvoice, PHP_URL_PATH);
$rawBodyCreateInvoice = json_encode($bodyCreateInvoice);
$dataToSignCreateInvoice = $api_key . $signRelativeURLCreateInvoice . $rawBodyCreateInvoice;
$signatureCreateInvoice = hash_hmac('sha256', $dataToSignCreateInvoice, $api_secret);

// ==== Panggil API Create Invoice ====
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
curl_setopt($chCreateInvoice, CURLOPT_HTTPHEADER, $headersCreateInvoice);
curl_setopt($chCreateInvoice, CURLOPT_POSTFIELDS, $rawBodyCreateInvoice);
$sentHeaders = curl_getinfo($chCreateInvoice, CURLINFO_HEADER_OUT);
$responseCreateInvoice = curl_exec($chCreateInvoice);
curl_close($chCreateInvoice);

// ==== Proses hasil & simpan ke database ====
$invoice = json_decode($responseCreateInvoice);

if ($invoice->responseCode == '2000000') {
    $invoiceId   = $invoice->responseData->invoiceId;
    $accessToken = $invoice->responseData->accessToken;

    include "db_config.php";

    $itemsJson = json_encode($bodyCreateInvoice['items']);
    $remarksClean = str_replace(array('\'', '"', ',', ';', '<', '>', '/'), ' ', $bodyCreateInvoice['remarks']);

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

    if ($conn->query($sql) === TRUE) {
        // echo "Data inserted successfully";
    } else {
        echo "Error: " . $sql . "<br>" . $conn->error;
    }

    $conn->close();
}

if (!empty($invoiceId) && !empty($accessToken)) {
    echo "Invoice ID: " . htmlspecialchars($invoiceId) . "<br/>";
    echo "Access Token: " . htmlspecialchars($accessToken) . "<br/><br/>";
    echo "<a href='cek.php?invoiceId=" . urlencode($invoiceId) . "&accessToken=" . urlencode($accessToken) . "'>Cek Status Invoice</a>";
} else {
    echo "Gagal membuat invoice. Response: " . htmlspecialchars($responseCreateInvoice);
}
?>
