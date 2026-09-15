<?php
// Konfigurasi koneksi database MySQL
// File ini tidak ada di slide, tapi di-include oleh respon.php
// Sesuaikan nilai di bawah dengan database Anda

$db_host = "localhost";
$db_user = "root";
$db_pass = "";
$db_name = "fintech_aiyo";

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>