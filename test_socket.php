<?php

function mqttPublish($host, $port, $topic, $payload) {
    $socket = @fsockopen($host, $port, $errno, $errstr, 5);
    if (!$socket) return 'Socket failed: ' . $errstr;

    // Connect
    $clientId = 'fhk_' . rand(1000, 9999);
    $varHeader = chr(0) . chr(4) . 'MQTT' . chr(4) . chr(2) . chr(0) . chr(60);
    $clientPayload = chr(0) . chr(strlen($clientId)) . $clientId;
    $remainingLength = strlen($varHeader) + strlen($clientPayload);
    $connectPacket = chr(0x10) . chr($remainingLength) . $varHeader . $clientPayload;
    fwrite($socket, $connectPacket);

    // Read CONNACK (4 bytes: 0x20, 0x02, 0x00, 0x00)
    $response = fread($socket, 4);
    if (strlen($response) < 4 || ord($response[0]) !== 0x20 || ord($response[3]) !== 0x00) {
        fclose($socket);
        return 'CONNACK failed: ' . bin2hex($response);
    }

    // Publish QoS 0
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
    fwrite($socket, $pubPacket);

    // Disconnect
    fwrite($socket, chr(0xE0) . chr(0x00));
    fclose($socket);
    return 'SUCCESS';
}

$res = mqttPublish('broker.emqx.io', 1883, 'fhk/kiosk/FHK-JAKARTA-01/command', json_encode(['command' => 'DISPENSE', 'volume_ml' => 250]));
echo "RESULT: " . $res . "\n";
