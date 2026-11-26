<?php

/**
 * Script Test Manual Trigger Dispense (500 mL Normal)
 * Mengirim instruksi penuangan air langsung ke ESP32 melalui broker.emqx.io
 */

$host  = 'broker.emqx.io';
$port  = 1883;
$topic = 'fhk/kiosk/FHK-JAKARTA-01/command';

$payload = [
    'command'             => 'DISPENSE',
    'job_id'              => 'TEST-MANUAL-' . date('His'),
    'reference_id'        => 'REF-' . rand(100000, 999999),
    'water_type'          => 'NORMAL',
    'volume_ml'           => 500,
    'max_cup_distance_cm' => 15.0,
    'min_cup_distance_cm' => 9.0,
    'stop_distance_cm'    => 5.0,
    'timeout_sec'         => 60,
    'timestamp'           => date('c')
];

$jsonPayload = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

echo "====================================================\n";
echo " ⚡ FHK MANUAL TEST TRIGGER - DISPENSE 500 mL\n";
echo "====================================================\n";
echo "Broker : {$host}:{$port}\n";
echo "Topik  : {$topic}\n";
echo "Payload:\n{$jsonPayload}\n\n";
echo "Mengirim instruksi ke broker.emqx.io...\n";

function publishPureSocket(string $host, int $port, string $topic, string $payload): bool {
    $socket = @fsockopen($host, $port, $errno, $errstr, 5);
    if (!$socket) {
        echo "❌ Gagal koneksi socket TCP: {$errstr} ({$errno})\n";
        return false;
    }
    stream_set_timeout($socket, 3);

    // 1. MQTT CONNECT Packet
    $clientId = 'fhk_test_pub_' . rand(1000, 9999);
    $varHeader = chr(0) . chr(4) . 'MQTT' . chr(4) . chr(2) . chr(0) . chr(60);
    $clientPayload = chr(0) . chr(strlen($clientId)) . $clientId;
    $remainingLength = strlen($varHeader) + strlen($clientPayload);
    $connectPacket = chr(0x10) . chr($remainingLength) . $varHeader . $clientPayload;
    @fwrite($socket, $connectPacket);

    // Read CONNACK
    $connack = @fread($socket, 4);
    if (strlen($connack) < 4 || ord($connack[0]) !== 0x20 || ord($connack[3]) !== 0x00) {
        @fclose($socket);
        echo "❌ Broker menolak koneksi (CONNACK failed)\n";
        return false;
    }

    // 2. MQTT PUBLISH Packet
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

    $pubPacket = chr(0x30) . $remBytes . $pubVarHeader . $payload;
    @fwrite($socket, $pubPacket);

    // 3. MQTT DISCONNECT
    @fwrite($socket, chr(0xE0) . chr(0x00));
    @fclose($socket);

    return true;
}

$success = publishPureSocket($host, $port, $topic, json_encode($payload));

if ($success) {
    echo "====================================================\n";
    echo "✅ INSTRUKSI BERHASIL DIKIRIM KE BROKER!\n";
    echo "====================================================\n";
    echo "Respon di Hardware ESP32:\n";
    echo "1. Buzzer akan berbunyi 'bip' 1x.\n";
    echo "2. LCD akan menampilkan:\n";
    echo "   [Baris 1]: Order: 250mL\n";
    echo "   [Baris 2]: Taruh Gelas...\n";
    echo "3. Silakan letakkan gelas/wadah di bawah sensor (jarak 9-15 cm).\n";
    echo "4. Pompa akan menyala dan menuang hingga flow sensor mencapai 250 mL.\n";
    echo "====================================================\n";
} else {
    echo "❌ Pengiriman instruksi gagal. Pastikan koneksi internet aktif.\n";
}
