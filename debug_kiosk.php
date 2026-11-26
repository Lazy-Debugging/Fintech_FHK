<?php
/**
 * FHK Live Debug & Transaction Monitor
 * Akses: https://app.mesinbayar.com/fhk/debug_kiosk.php
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

// Cari direktori root Laravel
$possibleDirs = [
    __DIR__,
    dirname(__DIR__),
    dirname(dirname(__DIR__)),
    '/home/' . get_current_user() . '/public_html/fhk',
    '/home/' . get_current_user() . '/domains/app.mesinbayar.com/public_html/fhk',
];

$baseDir = __DIR__;
$envFile = null;
foreach ($possibleDirs as $d) {
    if (file_exists($d . '/.env')) {
        $baseDir = $d;
        $envFile = $d . '/.env';
        break;
    }
}

// Jika belum ketemu, cari rekursif ke atas
if (!$envFile) {
    $cur = __DIR__;
    for ($i = 0; $i < 4; $i++) {
        if (file_exists($cur . '/.env')) {
            $baseDir = $cur;
            $envFile = $cur . '/.env';
            break;
        }
        $cur = dirname($cur);
    }
}

$dbConfig = [];
$dbType = 'sqlite';
if ($envFile && file_exists($envFile)) {
    $envContent = file_get_contents($envFile);
    foreach (explode("\n", $envContent) as $line) {
        $line = trim($line);
        if (strpos($line, '#') === 0 || !str_contains($line, '=')) continue;
        list($k, $v) = explode('=', $line, 2);
        $k = trim($k);
        $v = trim($v, " \t\n\r\0\x0B\"'");
        $dbConfig[$k] = $v;
    }
}

$pdo = null;
$dbError = null;
$dbConnectedInfo = '';

// Prioritas 1: SQLite
$sqliteCandidatePaths = [
    $baseDir . '/database/database.sqlite',
    dirname(__DIR__) . '/database/database.sqlite',
    __DIR__ . '/database/database.sqlite',
];
if (!empty($dbConfig['DB_DATABASE']) && file_exists($dbConfig['DB_DATABASE'])) {
    array_unshift($sqliteCandidatePaths, $dbConfig['DB_DATABASE']);
}

$connType = $dbConfig['DB_CONNECTION'] ?? 'sqlite';

if ($connType === 'sqlite' || empty($dbConfig['DB_HOST'])) {
    foreach ($sqliteCandidatePaths as $sPath) {
        if (file_exists($sPath)) {
            try {
                $pdo = new PDO('sqlite:' . $sPath, null, null, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_TIMEOUT => 3
                ]);
                $dbConnectedInfo = "SQLite: " . $sPath;
                $dbType = 'sqlite';
                break;
            } catch (\Throwable $e) {
                $dbError = "SQLite error: " . $e->getMessage();
            }
        }
    }
}

// Prioritas 2: MySQL jika SQLite tidak ditemukan atau DB_CONNECTION=mysql
if (!$pdo && (!empty($dbConfig['DB_DATABASE']) || !empty($dbConfig['DB_HOST']))) {
    try {
        $dsn = "mysql:host=" . ($dbConfig['DB_HOST'] ?? '127.0.0.1') . ";port=" . ($dbConfig['DB_PORT'] ?? '3306') . ";dbname=" . ($dbConfig['DB_DATABASE'] ?? 'laravel') . ";charset=utf8mb4";
        $pdo = new PDO($dsn, $dbConfig['DB_USERNAME'] ?? '', $dbConfig['DB_PASSWORD'] ?? '', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT => 3
        ]);
        $dbConnectedInfo = "MySQL: " . ($dbConfig['DB_HOST'] ?? '127.0.0.1') . " / " . ($dbConfig['DB_DATABASE'] ?? '');
        $dbType = 'mysql';
    } catch (\Throwable $e) {
        $dbError = ($dbError ? $dbError . " | " : "") . "MySQL error: " . $e->getMessage();
    }
}

$actionMsg = null;
// Handle Action Trigger Manual
if (isset($_POST['action']) && $pdo) {
    try {
        $act = $_POST['action'];
        $txId = $_POST['invoice_id'] ?? '';
        $now = date('Y-m-d H:i:s');
        if ($act === 'set_dispensing' && $txId) {
            $stmt = $pdo->prepare("UPDATE transaksi SET status = 'DISPENSING', dispense_started_at = ?, dispense_completed_at = NULL WHERE invoiceId = ?");
            $stmt->execute([$now, $txId]);
            $actionMsg = "Status invoice <b>{$txId}</b> berhasil diubah ke <b>DISPENSING</b>. ESP32 sekarang akan langsung mengambil perintah ini!";
        } elseif ($act === 'reset_all_stuck') {
            $stmt = $pdo->prepare("UPDATE transaksi SET dispense_completed_at = ? WHERE status = 'DISPENSING' AND dispense_completed_at IS NULL");
            $stmt->execute([$now]);
            $actionMsg = "Semua transaksi macet berhasil ditandai selesai.";
        } elseif ($act === 'create_test_order') {
            $newInv = 'TEST-' . date('His');
            $refId = 'REF-' . rand(100000, 999999);
            $stmt = $pdo->prepare("INSERT INTO transaksi (invoiceId, referenceId, kiosk_id, water_type, volume_ml, payAmount, status, dispense_started_at, created_at, updated_at) VALUES (?, ?, 'FHK-JAKARTA-01', 'NORMAL', 250, 1, 'DISPENSING', ?, ?, ?)");
            $stmt->execute([$newInv, $refId, $now, $now, $now]);
            $actionMsg = "Order uji coba baru (Normal 250 mL) <b>{$newInv}</b> berhasil dibuat dengan status <b>DISPENSING</b>! ESP32 akan langsung berbunyi.";
        }
    } catch (\Throwable $e) {
        $actionMsg = "<span style='color:#ef4444;'>Gagal mengeksekusi aksi: " . htmlspecialchars($e->getMessage()) . "</span>";
    }
}

// Ambil 10 transaksi terakhir
$transactions = [];
if ($pdo) {
    try {
        $stmt = $pdo->query("SELECT invoiceId, referenceId, kiosk_id, water_type, volume_ml, payAmount, status, dispense_started_at, dispense_completed_at, created_at FROM transaksi ORDER BY created_at DESC LIMIT 10");
        $transactions = $stmt->fetchAll();
    } catch (\Throwable $e) {
        $dbError = "Gagal query transaksi: " . $e->getMessage();
    }
}

// Cek output command endpoint saat ini
$hostName = $_SERVER['HTTP_HOST'] ?? 'app.mesinbayar.com';
$reqUri = $_SERVER['REQUEST_URI'] ?? '/fhk/debug_kiosk.php';
$scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https://' : 'http://';
$commandUrl = $scheme . $hostName . (str_contains($reqUri, '/fhk/') ? '/fhk' : '') . '/api/iot/kiosk/FHK-JAKARTA-01/command';
$ch = curl_init($commandUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 3);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$commandOutput = curl_exec($ch);
$commandHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FHK IoT Live Debugger</title>
    <style>
        body { background: #090d16; color: #f1f5f9; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; padding: 20px; margin: 0; }
        .container { max-width: 1000px; margin: 0 auto; }
        .card { background: #131d2e; border: 1px solid #1e293b; border-radius: 14px; padding: 20px; margin-bottom: 20px; box-shadow: 0 10px 25px rgba(0,0,0,0.3); }
        h1 { color: #38bdf8; font-size: 22px; margin: 0 0 10px 0; }
        h2 { font-size: 16px; margin: 0 0 12px 0; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px; }
        .badge { display: inline-block; padding: 3px 8px; border-radius: 6px; font-size: 11px; font-weight: bold; }
        .badge-pending { background: #854d0e; color: #fef08a; }
        .badge-paid { background: #166534; color: #86efac; }
        .badge-dispensing { background: #1e40af; color: #93c5fd; animation: pulse 1.5s infinite; }
        .badge-completed { background: #065f46; color: #6ee7b7; }
        .alert { background: rgba(34, 197, 94, 0.15); border: 1px solid #22c55e; color: #86efac; padding: 12px 16px; border-radius: 10px; margin-bottom: 20px; }
        .alert-err { background: rgba(239, 68, 68, 0.15); border: 1px solid #ef4444; color: #fca5a5; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; font-family: ui-monospace, monospace; }
        th, td { padding: 10px 12px; text-align: left; border-bottom: 1px solid #1e293b; }
        th { background: #0d1524; color: #94a3b8; }
        tr:hover { background: #18253a; }
        .btn { background: #0284c7; color: white; border: none; padding: 6px 12px; border-radius: 6px; font-size: 12px; font-weight: bold; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn:hover { background: #0369a1; }
        .btn-green { background: #16a34a; }
        .btn-green:hover { background: #15803d; }
        .btn-red { background: #dc2626; }
        .btn-red:hover { background: #b91c1c; }
        pre { background: #080c14; padding: 12px; border-radius: 8px; font-size: 12px; color: #38bdf8; overflow-x: auto; margin: 0; }
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.6; } }
    </style>
</head>
<body>
<div class="container">
    <div class="card">
        <h1>🔍 FHK Live System & IoT Debugger</h1>
        <p style="color: #94a3b8; font-size: 13px; margin: 4px 0 0 0;">
            Pantau status transaksi QRIS real-time dan perintah yang sedang dibaca oleh ESP32.
        </p>
        <div style="margin-top: 10px; font-size: 12px;">
            <span style="color: #94a3b8;">Database Aktif:</span>
            <?php if ($pdo): ?>
                <span class="badge badge-paid">✓ Terhubung (<?php echo htmlspecialchars($dbConnectedInfo); ?>)</span>
            <?php else: ?>
                <span class="badge alert-err">✗ Terputus (<?php echo htmlspecialchars($dbError ?: 'Tidak dapat mendeteksi file .env atau database.sqlite'); ?>)</span>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($actionMsg): ?>
        <div class="alert"><?php echo $actionMsg; ?></div>
    <?php endif; ?>

    <?php if ($dbError): ?>
        <div class="alert alert-err"><b>Database Error:</b> <?php echo htmlspecialchars($dbError); ?></div>
    <?php endif; ?>

    <!-- 1. Realtime Endpoint Response yang dilihat ESP32 -->
    <div class="card">
        <h2>📡 Respon yang Diterima ESP32 Saat Ini (Port 80 /command)</h2>
        <div style="margin-bottom: 8px; font-size: 12px; color: #94a3b8;">
            URL: <code><?php echo htmlspecialchars($commandUrl); ?></code> (HTTP <?php echo $commandHttpCode; ?>)
        </div>
        <pre><?php echo htmlspecialchars($commandOutput ?: 'Tidak ada respon dari server'); ?></pre>
    </div>

    <!-- 2. Aksi Cepat / Testing Tanpa Bayar -->
    <div class="card" style="display: flex; gap: 10px; align-items: center; justify-content: space-between; flex-wrap: wrap;">
        <div>
            <b>Aksi Cepat IoT:</b>
        </div>
        <div style="display: flex; gap: 8px;">
            <form method="POST" style="margin: 0;">
                <input type="hidden" name="action" value="create_test_order">
                <button type="submit" class="btn btn-green">⚡ Buat Pesanan Uji Coba 250 mL (Langsung DISPENSING)</button>
            </form>
            <form method="POST" style="margin: 0;">
                <input type="hidden" name="action" value="reset_all_stuck">
                <button type="submit" class="btn btn-red" onclick="return confirm('Bersihkan antrean macet?')">Bersihkan Antrean Macet</button>
            </form>
            <a href="" class="btn">🔄 Refresh Data</a>
        </div>
    </div>

    <!-- 3. Daftar 10 Transaksi Terakhir di Database -->
    <div class="card">
        <h2>📋 10 Transaksi Terakhir di Database Live</h2>
        <?php if (empty($transactions)): ?>
            <p style="color: #94a3b8; font-size: 13px;">Tidak ada transaksi ditemukan atau database belum terhubung.</p>
        <?php else: ?>
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Waktu (Created)</th>
                            <th>Invoice ID</th>
                            <th>Tipe Air</th>
                            <th>Volume</th>
                            <th>Harga</th>
                            <th>Status Saat Ini</th>
                            <th>Dispense Started</th>
                            <th>Dispense Done</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $tx): ?>
                            <?php 
                                $st = strtoupper($tx['status']);
                                $bClass = 'badge-pending';
                                if ($st === 'PAID') $bClass = 'badge-paid';
                                elseif ($st === 'DISPENSING' || $st === 'QUEUED') $bClass = 'badge-dispensing';
                                elseif ($st === 'COMPLETED') $bClass = 'badge-completed';
                            ?>
                            <tr>
                                <td><?php echo date('H:i:s d/m', strtotime($tx['created_at'])); ?></td>
                                <td><b><?php echo htmlspecialchars($tx['invoiceId']); ?></b></td>
                                <td><?php echo htmlspecialchars($tx['water_type'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($tx['volume_ml'] ?? 0); ?> mL</td>
                                <td>Rp <?php echo number_format($tx['payAmount'] ?? 0); ?></td>
                                <td><span class="badge <?php echo $bClass; ?>"><?php echo htmlspecialchars($st); ?></span></td>
                                <td><?php echo $tx['dispense_started_at'] ? date('H:i:s', strtotime($tx['dispense_started_at'])) : '-'; ?></td>
                                <td><?php echo $tx['dispense_completed_at'] ? date('H:i:s', strtotime($tx['dispense_completed_at'])) : '-'; ?></td>
                                <td>
                                    <?php if ($st !== 'DISPENSING' || !empty($tx['dispense_completed_at'])): ?>
                                        <form method="POST" style="margin: 0; display: inline;">
                                            <input type="hidden" name="action" value="set_dispensing">
                                            <input type="hidden" name="invoice_id" value="<?php echo htmlspecialchars($tx['invoiceId']); ?>">
                                            <button type="submit" class="btn" style="padding: 3px 8px; font-size: 11px;">
                                                Tuang via IoT
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span style="color: #38bdf8; font-size: 11px;">Sedang Diproses IoT...</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
