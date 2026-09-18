<?php
/**
 * Konfigurasi koneksi database
 * Mengutamakan SQLite lokal tanpa delay koneksi jaringan
 */

$conn = null;
$dbType = 'sqlite';

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

// Jika ada konfigurasi MySQL di environment dan bukan default root
$dbHost = getenv('DB_HOST');
$dbUser = getenv('DB_USERNAME');
$dbPass = getenv('DB_PASSWORD');
$dbName = getenv('DB_DATABASE');

if (!$conn && $dbHost && $dbUser && $dbUser !== 'root' && extension_loaded('mysqli')) {
    mysqli_report(MYSQLI_REPORT_OFF);
    $mysqli = mysqli_init();
    $mysqli->options(MYSQLI_OPT_CONNECT_TIMEOUT, 2);
    if (@$mysqli->real_connect($dbHost, $dbUser, $dbPass ?: '', $dbName ?: '')) {
        $conn = $mysqli;
        $dbType = 'mysql';
    }
}
?>
