<?php
// Set timezone ke Asia/Jakarta (WIB) agar perhitungan expireTime tidak expired
date_default_timezone_set('Asia/Jakarta');

// Konfigurasi koneksi ke API AiYO Bills Invoice (DBI)
// Nilai xxx harus diganti sesuai kredensial yang didapat dari dashboard DBI

$host = "https://api-bills-invoice.aiyo.id";
$username = "FRESH_HYDRATION_KIOS"; // username DEV dari dashboard DBI
$password = "pass_7A9uubvhF0XQZ7gePewlItKt3Uzg2I"; // password DEV dari dashboard DBI
$billMasterId = "uxGSWGOqpeqLaG5Qn1DH"; // lihat di dashboard DBI
$api_key = "key_SxcKMQ8cMbckjRMLvNCy1hNqUMGSo4";
$api_secret = "secret_WU3Ba45FwWE9sDe4uxUarvM4P6KMB4";
?>