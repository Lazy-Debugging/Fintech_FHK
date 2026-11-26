<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Mencoba koneksi ke Mosquitto Broker...\n";

try {
    $host = config('mqtt.host', 'broker.emqx.io');
    $mqtt = new \PhpMqtt\Client\MqttClient($host, 1883, 'test_check_' . rand(100, 999));
    $mqtt->connect();
    
    $payload = [
        'command'    => 'DISPENSE',
        'job_id'     => 'TEST-' . date('His'),
        'water_type' => 'NORMAL',
        'volume_ml'  => 500,
        'message'    => 'Tes penuangan air 500 mL dari Laravel!',
        'time'       => date('Y-m-d H:i:s')
    ];
    
    $topic = 'fhk/kiosk/FHK-JAKARTA-01/command';
    $mqtt->publish($topic, json_encode($payload), 0);
    $mqtt->disconnect();
    
    echo "========================================\n";
    echo "STATUS: BERHASIL TERHUBUNG KE MOSQUITTO!\n";
    echo "Topik: {$topic}\n";
    echo "Payload: " . json_encode($payload, JSON_PRETTY_PRINT) . "\n";
    echo "========================================\n";
} catch (\Throwable $e) {
    echo "GAGAL: " . $e->getMessage() . "\n";
}

