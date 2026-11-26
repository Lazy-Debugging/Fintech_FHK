<?php

$files = [
    'config/mqtt.php',
    'app/Services/MqttKioskService.php',
    'app/Console/Commands/KioskMqttListenCommand.php',
    'app/Http/Controllers/Kiosk/OrderController.php',
    'app/Http/Controllers/Payment/AiyoCallbackController.php',
    'app/Http/Controllers/Iot/Esp32BridgeController.php',
    'app/Http/Controllers/Admin/DashboardController.php',
    'app/Models/KioskPricing.php',
    'resources/views/kiosk/index.blade.php',
    'resources/views/kiosk/dispensing.blade.php',
    'resources/views/admin/dashboard.blade.php',
    'resources/views/admin/wiring.blade.php',
    'resources/views/layouts/admin_layout.blade.php',
    'routes/web.php',
    'routes/api.php',
    'debug_kiosk.php',
];

$payload = [];
foreach ($files as $f) {
    $path = __DIR__ . '/' . $f;
    if (file_exists($path)) {
        $payload[$f] = base64_encode(file_get_contents($path));
        echo "Packed: $f (" . strlen($payload[$f]) . " bytes b64)\n";
    } else {
        echo "NOT FOUND: $f\n";
    }
}

$payloadJson = json_encode($payload);

$template = <<<'CODE'
<?php
/**
 * FHK Live Server IoT & Cloud MQTT One-Click Updater (v9 Complete)
 * Akses: https://app.mesinbayar.com/fhk/update_iot.php
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

$baseDir = file_exists(__DIR__ . '/artisan') ? __DIR__ : (file_exists(dirname(__DIR__) . '/artisan') ? dirname(__DIR__) : __DIR__);

$logs = [];
$successCount = 0;
$filesPayload = json_decode('__PAYLOAD_PLACEHOLDER__', true);

// 1. Ekstraksi File
foreach ($filesPayload as $relPath => $b64Content) {
    $targetPath = $baseDir . '/' . $relPath;
    $targetDir  = dirname($targetPath);
    if (!is_dir($targetDir)) @mkdir($targetDir, 0777, true);
    $decoded = base64_decode($b64Content);
    $written = @file_put_contents($targetPath, $decoded);
    if ($written !== false) {
        $logs[] = ['type' => 'success', 'file' => $relPath, 'msg' => "Berhasil ditulis (" . number_format($written) . " bytes)"];
        $successCount++;
    } else {
        $logs[] = ['type' => 'error', 'file' => $relPath, 'msg' => "Gagal menulis file. Periksa izin folder {$targetDir}"];
    }
}

// 2. Clear Laravel Bootstrap Cache (Sangat penting agar config & route baru aktif)
$cacheFiles = [
    $baseDir . '/bootstrap/cache/config.php',
    $baseDir . '/bootstrap/cache/routes-v7.php',
    $baseDir . '/bootstrap/cache/routes.php',
    $baseDir . '/bootstrap/cache/services.php',
    $baseDir . '/bootstrap/cache/packages.php',
];
$clearedCaches = [];
foreach ($cacheFiles as $cf) {
    if (file_exists($cf)) {
        if (@unlink($cf)) {
            $clearedCaches[] = basename($cf);
        }
    }
}

// Hapus cache view blade yang ter-compile agar route baru langsung terbaca
$viewCacheDir = $baseDir . '/storage/framework/views';
if (is_dir($viewCacheDir)) {
    foreach (glob($viewCacheDir . '/*.php') as $vf) {
        @unlink($vf);
    }
    $clearedCaches[] = 'views (blade compiled)';
}

// 3. Patch File .env untuk broker.emqx.io & DEFAULT_KIOSK_ID
$envFile = $baseDir . '/.env';
$envPatched = false;
if (file_exists($envFile)) {
    $envContent = file_get_contents($envFile);
    if (!str_contains($envContent, 'MQTT_HOST')) {
        $mqttConfig = "\n# MQTT Cloud Broker Configuration for Live Kiosk\n"
                    . "MQTT_HOST=broker.emqx.io\n"
                    . "MQTT_PORT=1883\n"
                    . "MQTT_CLIENT_ID=fhk_live_server\n"
                    . "MQTT_AUTH_USERNAME=\n"
                    . "MQTT_AUTH_PASSWORD=\n"
                    . "MQTT_DEFAULT_KIOSK_ID=FHK-JAKARTA-01\n";
        @file_put_contents($envFile, $envContent . $mqttConfig);
        $envPatched = true;
    } else {
        $envContent = preg_replace('/MQTT_HOST=.*/', 'MQTT_HOST=broker.emqx.io', $envContent);
        @file_put_contents($envFile, $envContent);
        $envPatched = true;
    }
}

// 4. Update Database Pricing untuk Rp 1 (Air Normal 250 mL)
$dbStatus = 'Tabel database dilewati (menggunakan matriks fallback Rp 1)';
try {
    $pdo = null;
    $sqlitePath = $baseDir . '/database/database.sqlite';
    if (file_exists($sqlitePath)) {
        $pdo = new PDO('sqlite:' . $sqlitePath, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 3
        ]);
        $dbStatus = "Database terkoneksi (SQLite: {$sqlitePath})";
    } elseif (file_exists($baseDir . '/.env')) {
        $envContent = file_get_contents($envFile);
        preg_match('/^DB_HOST=(.*)$/m', $envContent, $mHost);
        preg_match('/^DB_PORT=(.*)$/m', $envContent, $mPort);
        preg_match('/^DB_DATABASE=(.*)$/m', $envContent, $mDb);
        preg_match('/^DB_USERNAME=(.*)$/m', $envContent, $mUser);
        preg_match('/^DB_PASSWORD=(.*)$/m', $envContent, $mPass);

        $dbHost = trim($mHost[1] ?? '127.0.0.1');
        $dbPort = trim($mPort[1] ?? '3306');
        $dbName = trim($mDb[1] ?? '');
        $dbUser = trim($mUser[1] ?? '');
        $dbPass = trim($mPass[1] ?? '');
        
        if ($dbName && $dbUser) {
            $pdo = new PDO("mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4", $dbUser, $dbPass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 3
            ]);
            $dbStatus = "Database terkoneksi (MySQL: {$dbName})";
        }
    }

    if ($pdo) {
        try {
            $stmt = $pdo->prepare("UPDATE kiosk_pricing SET price = 1 WHERE water_type = 'NORMAL' AND volume_ml = 250");
            $stmt->execute();
            if ($stmt->rowCount() > 0) {
                $dbStatus .= " | Berhasil mengupdate kiosk_pricing (Normal 250 mL = Rp 1)";
            } else {
                $dbStatus .= " | Harga Normal 250 mL sudah Rp 1";
            }
        } catch (\Throwable $eP) {}

        // Diagnosa 5 Transaksi Terakhir
        $stmtTx = $pdo->query("SELECT invoiceId, kiosk_id, status, volume_ml, payAmount, dispense_started_at, dispense_completed_at, created_at FROM transaksi ORDER BY created_at DESC LIMIT 5");
        $recentOrders = $stmtTx->fetchAll(PDO::FETCH_ASSOC);
        $dbStatus .= "<br><br><b>5 Transaksi Terakhir di Database:</b><pre style='background:#0f172a;padding:10px;border-radius:8px;font-size:12px;color:#38bdf8;overflow-x:auto;'>" . htmlspecialchars(json_encode($recentOrders, JSON_PRETTY_PRINT)) . "</pre>";
    }
} catch (\Throwable $exDb) {
    $dbStatus = "DB Info: " . $exDb->getMessage();
}

// 5. Test Live Outbound Socket ke broker.emqx.io:1883 & Kirim Test Trigger ke ESP32
$mqttTestResult = false;
$mqttError = '';

function testMqttPublish(string $host, int $port, string $topic, string $payload, &$errStrOut): bool {
    $sock = @fsockopen($host, $port, $errno, $errstr, 4);
    if (!$sock) {
        $errStrOut = "Gagal koneksi socket TCP ke {$host}:{$port} - {$errstr} ({$errno})";
        return false;
    }
    stream_set_timeout($sock, 3);

    // CONNECT
    $clientId = 'fhk_tester_' . rand(100, 999);
    $varHeader = chr(0) . chr(4) . 'MQTT' . chr(4) . chr(2) . chr(0) . chr(60);
    $clientPayload = chr(0) . chr(strlen($clientId)) . $clientId;
    $remainingLength = strlen($varHeader) + strlen($clientPayload);
    @fwrite($sock, chr(0x10) . chr($remainingLength) . $varHeader . $clientPayload);

    // CONNACK
    $connack = @fread($sock, 4);
    if (strlen($connack) < 4 || ord($connack[0]) !== 0x20 || ord($connack[3]) !== 0x00) {
        $errStrOut = "CONNACK ditolak atau timeout oleh broker {$host}";
        @fclose($sock);
        return false;
    }

    // PUBLISH (QoS 0)
    $topicLen = strlen($topic);
    $pubVar = chr($topicLen >> 8) . chr($topicLen & 0xFF) . $topic;
    $pubPayloadLen = strlen($pubVar) + strlen($payload);

    $remBytes = '';
    $x = $pubPayloadLen;
    do {
        $b = $x % 128;
        $x = intdiv($x, 128);
        if ($x > 0) $b |= 128;
        $remBytes .= chr($b);
    } while ($x > 0);

    @fwrite($sock, chr(0x30) . $remBytes . $pubVar . $payload);
    @fwrite($sock, chr(0xE0) . chr(0x00));
    @fclose($sock);
    return true;
}

$testPayload = json_encode([
    'command'   => 'TEST_PING',
    'message'   => 'FHK Updater Live Ping',
    'timestamp' => date('Y-m-d H:i:s')
]);
$mqttTestResult = testMqttPublish('broker.emqx.io', 1883, 'fhk/kiosk/FHK-JAKARTA-01/command', $testPayload, $mqttError);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FHK Live Server - IoT & MQTT Updater Complete</title>
    <style>
        body { background: #0b1120; color: #f8fafc; font-family: ui-sans-serif, system-ui, sans-serif; padding: 24px; margin: 0; }
        .card { max-width: 820px; margin: 0 auto; background: #1e293b; border-radius: 20px; padding: 28px; border: 1px solid #334155; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5); }
        h1 { color: #38bdf8; font-size: 24px; margin: 0 0 8px 0; font-weight: 800; display: flex; align-items: center; justify-content: space-between; }
        .item { display: flex; justify-content: space-between; align-items: center; padding: 10px 14px; border-bottom: 1px solid #334155; font-size: 13px; font-family: ui-monospace, monospace; }
        .ok { color: #22c55e; font-weight: bold; }
        .err { color: #ef4444; font-weight: bold; }
        .box { background: #0f172a; border-radius: 12px; margin: 16px 0; overflow: hidden; border: 1px solid #1e293b; }
        .tag { background: rgba(34, 197, 94, 0.2); color: #22c55e; padding: 4px 12px; border-radius: 9999px; font-size: 12px; font-weight: bold; }
        .tag-err { background: rgba(239, 68, 68, 0.2); color: #ef4444; padding: 4px 12px; border-radius: 9999px; font-size: 12px; font-weight: bold; }
        .banner { background: rgba(56, 189, 248, 0.12); border: 1px solid rgba(56, 189, 248, 0.35); color: #e2e8f0; padding: 16px; border-radius: 12px; margin: 16px 0; font-size: 14px; line-height: 1.6; }
        .status-pill { display: inline-block; padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: bold; text-transform: uppercase; }
        .btn { display: inline-block; background: linear-gradient(135deg, #06b6d4, #3b82f6); color: #0b1120; font-weight: 800; padding: 12px 24px; border-radius: 12px; text-decoration: none; margin-top: 10px; transition: opacity 0.2s; }
        .btn:hover { opacity: 0.9; }
    </style>
</head>
<body>
<div class="card">
    <h1>
        <span>⚡ FHK Live Server - IoT Complete</span>
        <span class="<?php echo $mqttTestResult ? 'tag' : 'tag-err'; ?>">
            <?php echo $mqttTestResult ? 'MQTT OK' : 'MQTT CHECK'; ?>
        </span>
    </h1>
    <p style="color: #94a3b8; font-size: 14px; margin-bottom: 16px;">
        Pembaruan otomatis 10 file utama (Auto-Dispense, MQTT Pure-Socket, Dynamic Receipt Path, & Flush Cache).
    </p>

    <!-- Outbound MQTT Test Status -->
    <div style="background: <?php echo $mqttTestResult ? 'rgba(34, 197, 94, 0.12)' : 'rgba(239, 68, 68, 0.12)'; ?>; border: 1px solid <?php echo $mqttTestResult ? 'rgba(34, 197, 94, 0.35)' : 'rgba(239, 68, 68, 0.35)'; ?>; padding: 14px 16px; border-radius: 12px; margin: 16px 0;">
        <div style="font-weight: bold; color: <?php echo $mqttTestResult ? '#4ade80' : '#f87171'; ?>; font-size: 14px;">
            <?php if ($mqttTestResult): ?>
                ✓ Tes Koneksi MQTT Live: BERHASIL TERHUBUNG ke broker.emqx.io:1883!
            <?php else: ?>
                ✗ Tes Koneksi MQTT Live: GAGAL (<?php echo htmlspecialchars($mqttError); ?>)
            <?php endif; ?>
        </div>
        <div style="font-size: 12px; color: #cbd5e1; margin-top: 4px;">
            Target Topik: <code>fhk/kiosk/FHK-JAKARTA-01/command</code> | Paket: Clean Session v3.1.1
        </div>
    </div>

    <!-- Bootstrap Cache Clear Status -->
    <?php if (!empty($clearedCaches)): ?>
    <div style="background: rgba(234, 179, 8, 0.12); border: 1px solid rgba(234, 179, 8, 0.35); padding: 12px 16px; border-radius: 12px; font-size: 13px; color: #fde047; margin: 16px 0;">
        ✓ Cache Laravel Dibersihkan: <strong><?php echo implode(', ', $clearedCaches); ?></strong> (Konfigurasi & Routing Baru Sekarang Aktif 100%)
    </div>
    <?php endif; ?>

    <!-- File Ekstraksi List -->
    <div class="box">
        <?php foreach ($logs as $log): ?>
            <div class="item">
                <span>📁 <?php echo htmlspecialchars($log['file']); ?></span>
                <span class="<?php echo $log['type'] === 'success' ? 'ok' : 'err'; ?>">
                    <?php echo $log['type'] === 'success' ? '✓ ' : '✗ '; ?><?php echo htmlspecialchars($log['msg']); ?>
                </span>
            </div>
        <?php endforeach; ?>
    </div>

    <div style="font-size: 13px; color: #94a3b8; line-height: 1.8;">
        <div>🏷️ <strong>Status Database:</strong> <?php echo htmlspecialchars($dbStatus); ?></div>
        <div>🌐 <strong>Prefix Deteksi:</strong> Routing otomatis mendeteksi <code>/fhk/</code> sehingga struk receipt tidak akan 404 lagi.</div>
    </div>

    <div style="margin-top: 24px; text-align: center;">
        <a href="./kiosk" class="btn">🚀 Buka Halaman Kiosk & Test Transaksi Rp 1</a>
    </div>
</div>
</body>
</html>
CODE;

$finalContent = str_replace('__PAYLOAD_PLACEHOLDER__', addcslashes($payloadJson, "'\\"), $template);

file_put_contents(__DIR__ . '/update_iot.php', $finalContent);
file_put_contents(dirname(__DIR__) . '/public/update_iot.php', $finalContent);
echo "Successfully generated update_iot.php (" . strlen($finalContent) . " bytes)\n";
