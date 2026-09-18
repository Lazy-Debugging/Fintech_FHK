<?php
// Set timezone ke Asia/Jakarta (WIB) agar perhitungan expireTime akurat dan tidak expired
date_default_timezone_set('Asia/Jakarta');

/**
 * Konfigurasi koneksi ke API AiYO Bills Invoice (DBI - Dunia Bayar Indonesia)
 * Sesuai dengan Meeting 03 Slide 20 & 21
 */

// Host resmi AiYO Bills Invoice API (Slide 9 & 21)
$host = getenv('AIYO_HOST') ?: "https://api-bills-invoice.aiyo.id";

// Kredensial akun merchant FHK dari dashboard DBI (Slide 16 & 21)
$username     = getenv('AIYO_USERNAME') ?: "FRESH_HYDRATION_KIOS";
$password     = getenv('AIYO_PASSWORD') ?: "pass_7A9uubvhF0XQZ7gePewlItKt3Uzg2I";
$billMasterId = getenv('AIYO_BILL_MASTER_ID') ?: "uxGSWGOqpeqLaG5Qn1DH";
$api_key      = getenv('AIYO_API_KEY') ?: "key_SxcKMQ8cMbckjRMLvNCy1hNqUMGSo4";
$api_secret   = getenv('AIYO_API_SECRET') ?: "secret_WU3Ba45FwWE9sDe4uxUarvM4P6KMB4";

// URL Callback resmi yang didaftarkan untuk FHK (Slide 20)
$callbackUrl  = "https://mesinbayar.com/app/fhk/callback/";
?>
