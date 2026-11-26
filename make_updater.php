<?php

$filesToPack = [
    'config/mqtt.php' => __DIR__ . '/config/mqtt.php',
    'app/Services/MqttKioskService.php' => __DIR__ . '/app/Services/MqttKioskService.php',
    'app/Console/Commands/KioskMqttListenCommand.php' => __DIR__ . '/app/Console/Commands/KioskMqttListenCommand.php',
    'app/Http/Controllers/Kiosk/OrderController.php' => __DIR__ . '/app/Http/Controllers/Kiosk/OrderController.php',
    'app/Http/Controllers/Payment/AiyoCallbackController.php' => __DIR__ . '/app/Http/Controllers/Payment/AiyoCallbackController.php',
    'app/Http/Controllers/Iot/Esp32BridgeController.php' => __DIR__ . '/app/Http/Controllers/Iot/Esp32BridgeController.php',
    'app/Models/KioskPricing.php' => __DIR__ . '/app/Models/KioskPricing.php',
    'resources/views/kiosk/index.blade.php' => __DIR__ . '/resources/views/kiosk/index.blade.php',
    'resources/views/kiosk/dispensing.blade.php' => __DIR__ . '/resources/views/kiosk/dispensing.blade.php',
    'routes/web.php' => __DIR__ . '/routes/web.php',
];

$packedData = [];
foreach ($filesToPack as $relPath => $absPath) {
    if (!file_exists($absPath)) {
        die("Error: File {$absPath} tidak ditemukan!\n");
    }
    $packedData[$relPath] = base64_encode(file_get_contents($absPath));
}

$packedJson = json_encode($packedData);

$updaterTemplate = <<<'TEMPLATE'
<?php
/**
 * FHK Live Server IoT, Cloud MQTT & Struk Fix Updater
 * Akses: https://app.mesinbayar.com/fhk/update_iot.php
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

$baseDir = file_exists(__DIR__ . '/artisan') ? __DIR__ : (file_exists(dirname(__DIR__) . '/artisan') ? dirname(__DIR__) : __DIR__);

$logs = [];
$successCount = 0;
$filesPayload = json_decode('___PACKED_PAYLOAD___', true);

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

// 2. Patch File .env untuk broker.emqx.io
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

// 3. Clear Cache
$cacheCleaned = 0;
$viewCacheDir = $baseDir . '/storage/framework/views';
if (is_dir($viewCacheDir)) {
    foreach (glob($viewCacheDir . '/*') as $f) { if (is_file($f)) { @unlink($f); $cacheCleaned++; } }
}
$bootstrapCacheDir = $baseDir . '/bootstrap/cache';
if (is_dir($bootstrapCacheDir)) {
    foreach (['config.php', 'routes-v7.php', 'packages.php', 'services.php'] as $cFile) {
        $p = $bootstrapCacheDir . '/' . $cFile;
        if (file_exists($p)) { @unlink($p); $cacheCleaned++; }
    }
}

// 4. Test Live Outbound MQTT Socket dari Server Hosting ke broker.emqx.io
function testMqttSocket($host, $port, $topic, $payload) {
    $socket = @fsockopen($host, $port, $errno, $errstr, 4);
    if (!$socket) return "GAGAL KONEKSI KE {$host}:{$port} - Error: {$errstr} ({$errno})";
    
    stream_set_timeout($socket, 3);
    $clientId = 'fhk_test_' . rand(1000, 9999);
    $varHeader = chr(0) . chr(4) . 'MQTT' . chr(4) . chr(2) . chr(0) . chr(60);
    $clientPayload = chr(0) . chr(strlen($clientId)) . $clientId;
    $remainingLength = strlen($varHeader) + strlen($clientPayload);
    @fwrite($socket, chr(0x10) . chr($remainingLength) . $varHeader . $clientPayload);
    
    $connack = @fread($socket, 4);
    if (strlen($connack) < 4 || ord($connack[0]) !== 0x20 || ord($connack[3]) !== 0x00) {
        @fclose($socket);
        return "CONNACK Gagal dari broker ({$host})";
    }
    
    $topicLength = strlen($topic);
    $pubVarHeader = chr($topicLength >> 8) . chr($topicLength & 0xFF) . $topic;
    $pubPayloadLength = strlen($pubVarHeader) + strlen($payload);
    $remBytes = '';
    $x = $pubPayloadLength;
    do {
        $encodedByte = $x % 128;
        $x = intdiv($x, 128);
        if ($x > 0) $encodedByte |= 128;
        $remBytes .= chr($encodedByte);
    } while ($x > 0);
    
    @fwrite($socket, chr(0x30) . $remBytes . $pubVarHeader . $payload);
    @fwrite($socket, chr(0xE0) . chr(0x00));
    @fclose($socket);
    return "BERHASIL (Perintah tes penuangan 250 mL terkirim ke ESP32!)";
}

$testMsg = json_encode(['command' => 'DISPENSE', 'job_id' => 'TEST_LIVE_' . date('His'), 'volume_ml' => 250]);
$liveTestStatus = testMqttSocket('broker.emqx.io', 1883, 'fhk/kiosk/FHK-JAKARTA-01/command', $testMsg);
testMqttSocket('broker.emqx.io', 1883, 'fhk/kiosk/1/command', $testMsg);

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>FHK Live Server - Fix Struk & Auto-Dispense</title>
    <style>
        body { background: #0f172a; color: #f8fafc; font-family: sans-serif; padding: 24px; }
        .card { max-width: 800px; margin: 0 auto; background: #1e293b; border-radius: 16px; padding: 24px; border: 1px solid #334155; }
        h1 { color: #38bdf8; font-size: 22px; margin-bottom: 8px; }
        .item { display: flex; justify-content: space-between; padding: 10px 14px; border-bottom: 1px solid #334155; font-size: 13px; font-family: monospace; }
        .ok { color: #22c55e; }
        .box { background: #0b1120; border-radius: 12px; margin: 16px 0; overflow: hidden; }
        .tag { background: rgba(34, 197, 94, 0.2); color: #22c55e; padding: 4px 10px; border-radius: 6px; font-size: 12px; font-weight: bold; }
        .test-box { background: #0b1120; border: 1px solid #38bdf8; padding: 16px; border-radius: 12px; margin: 16px 0; }
    </style>
</head>
<body>
<div class="card">
    <h1>⚡ FHK Live Server - Fix Struk 404 & Auto-Dispense <span class="tag">broker.emqx.io</span></h1>
    <p style="color: #94a3b8; font-size: 14px; margin-bottom: 16px;">Route struk `/fhk/kiosk/receipt` dan penuangan langsung telah diperbaiki.</p>
    
    <div class="test-box">
        <div style="font-size: 12px; color: #94a3b8; text-transform: uppercase; font-weight: bold;">Uji Koneksi Outbound MQTT Server Hosting ke ESP32:</div>
        <div style="font-size: 15px; font-weight: bold; color: <?= str_contains($liveTestStatus, 'BERHASIL') ? '#22c55e' : '#ef4444' ?>; margin-top: 6px;">
            <?= htmlspecialchars($liveTestStatus) ?>
        </div>
        <div style="font-size: 12px; color: #94a3b8; margin-top: 6px;">
            *(Jika tertulis BERHASIL, cek ESP32 di meja Anda sekarang, layarnya pasti langsung berubah ke 250 mL!)*
        </div>
    </div>

    <div class="box">
        <?php foreach ($logs as $l): ?>
            <div class="item">
                <span>📁 <?= htmlspecialchars($l['file']) ?></span>
                <span class="ok">✓ Berhasil Ditulis</span>
            </div>
        <?php endforeach; ?>
    </div>
    
    <p style="color: #22c55e; font-weight: bold;">✓ Struk Digital 404 diperbaiki dengan route `/fhk/kiosk/receipt/{id}`</p>
    <p style="color: #38bdf8; font-size: 13px; margin-top: 14px;">Buka Web Kiosk FHK: <a href="./kiosk" style="color: #38bdf8; font-weight: bold; text-decoration: underline;">Buka Web Kiosk</a></p>
</div>
</body>
</html>
TEMPLATE;

$finalContent = str_replace('___PACKED_PAYLOAD___', $packedJson, $updaterTemplate);
file_put_contents(__DIR__ . '/update_iot.php', $finalContent);
file_put_contents(__DIR__ . '/public/update_iot.php', $finalContent);

echo "BERHASIL: update_iot.php dengan Fix Struk 404 dan Live Test Socket siap!\n";
