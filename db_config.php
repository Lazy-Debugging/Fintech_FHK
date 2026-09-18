<?php
/**
 * Konfigurasi koneksi database
 * Mendukung MySQL (Laragon/Production) maupun SQLite (Laravel Default)
 */

$db_host = getenv('DB_HOST') ?: "localhost";
$db_user = getenv('DB_USERNAME') ?: "root";
$db_pass = getenv('DB_PASSWORD') ?: "";
$db_name = getenv('DB_DATABASE') ?: "fintech_aiyo";

$conn = null;
$dbType = 'mysql';

// Coba koneksi MySQL terlebih dahulu jika extension mysqli ada
if (extension_loaded('mysqli')) {
    mysqli_report(MYSQLI_REPORT_OFF);
    $conn = @new mysqli($db_host, $db_user, $db_pass, $db_name);
    if ($conn->connect_error) {
        $conn = null;
    }
}

// Jika MySQL gagal atau belum dibuat, fallback adaptif ke SQLite database Laravel
if (!$conn) {
    $sqlitePath = __DIR__ . '/database/database.sqlite';
    if (file_exists($sqlitePath)) {
        try {
            $conn = new PDO('sqlite:' . $sqlitePath);
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $dbType = 'sqlite';
        } catch (Exception $e) {
            $conn = null;
        }
    }
}
?>
