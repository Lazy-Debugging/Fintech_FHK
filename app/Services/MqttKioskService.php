<?php

namespace App\Services;

use App\Models\Kiosk;
use App\Models\Transaksi;
use Illuminate\Support\Facades\Log;

class MqttKioskService
{
    protected string $host;
    protected int $port;
    protected string $clientId;

    public function __construct()
    {
        $host = config('mqtt.host');
        if (empty($host) || $host === '127.0.0.1' || $host === 'localhost') {
            $host = 'broker.emqx.io';
        }
        $this->host     = $host;
        $this->port     = (int) (config('mqtt.port') ?: 1883);
        $this->clientId = 'fhk_' . substr(md5(uniqid()), 0, 8);
    }

    /**
     * Pure PHP Socket MQTT Publisher (Zero External Dependency)
     * Sangat stabil di server live / shared hosting tanpa butuh composer package eksternal.
     */
    public static function publishPureSocket(string $host, int $port, string $topic, string $payload): bool
    {
        $socket = @fsockopen($host, $port, $errno, $errstr, 4);
        if (!$socket) {
            Log::warning("MqttKioskService: Socket connection failed to {$host}:{$port} ({$errstr})");
            return false;
        }

        stream_set_timeout($socket, 3);

        // 1. MQTT CONNECT Packet (v3.1.1, Clean Session, KeepAlive 60s)
        $clientId = 'fhk_srv_' . rand(1000, 9999);
        $varHeader = chr(0) . chr(4) . 'MQTT' . chr(4) . chr(2) . chr(0) . chr(60);
        $clientPayload = chr(0) . chr(strlen($clientId)) . $clientId;
        $remainingLength = strlen($varHeader) + strlen($clientPayload);
        $connectPacket = chr(0x10) . chr($remainingLength) . $varHeader . $clientPayload;
        @fwrite($socket, $connectPacket);

        // Baca respons CONNACK (4 bytes: 0x20, 0x02, 0x00, 0x00)
        $connack = @fread($socket, 4);
        if (strlen($connack) < 4 || ord($connack[0]) !== 0x20 || ord($connack[3]) !== 0x00) {
            @fclose($socket);
            Log::warning("MqttKioskService: CONNACK failed from {$host}");
            return false;
        }

        // 2. MQTT PUBLISH Packet (QoS 0)
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

        // 3. MQTT DISCONNECT Packet
        @fwrite($socket, chr(0xE0) . chr(0x00));
        @fclose($socket);

        return true;
    }

    /**
     * Mengirim instruksi penuangan air (Dispense Job) ke ESP32 melalui MQTT
     */
    public function dispatchDispenseJob(Transaksi $transaksi): array
    {
        $kioskId = $transaksi->kiosk_id ?: config('mqtt.default_kiosk_id', 'FHK-JAKARTA-01');

        $topic = "fhk/kiosk/{$kioskId}/command";
        $payload = [
            'command'             => 'DISPENSE',
            'job_id'              => $transaksi->invoiceId,
            'reference_id'        => $transaksi->referenceId,
            'water_type'          => strtoupper($transaksi->water_type ?? 'NORMAL'),
            'volume_ml'           => (int) ($transaksi->volume_ml ?? 250),
            'max_cup_distance_cm' => 15.0,
            'min_cup_distance_cm' => 9.0,
            'stop_distance_cm'    => 5.0,
            'timeout_sec'         => 60,
            'timestamp'           => now()->toIso8601String(),
        ];

        $payloadJson = json_encode($payload);

        // Kirim via Pure Socket (paling cepat dan tidak butuh vendor library di server live)
        $sent = self::publishPureSocket($this->host, $this->port, $topic, $payloadJson);

        // Jika ID kios bukan default, kirimkan juga ke topik default agar ESP32 pasti menerima
        if ($kioskId !== 'FHK-JAKARTA-01') {
            self::publishPureSocket($this->host, $this->port, 'fhk/kiosk/FHK-JAKARTA-01/command', $payloadJson);
        }

        if ($sent) {
            Log::info("MqttKioskService: Dispense job berhasil dikirim ke topik {$topic}", [
                'invoiceId' => $transaksi->invoiceId,
                'volume_ml' => $payload['volume_ml'],
                'host'      => $this->host,
            ]);

            $transaksi->update([
                'status'  => 'DISPENSING',
                'remarks' => trim(($transaksi->remarks ?? '') . ' | MQTT Sent: ' . date('H:i:s'))
            ]);

            $kiosk = Kiosk::find($kioskId);
            if ($kiosk) {
                $kiosk->update(['status' => 'DISPENSING', 'last_heartbeat' => now()]);
            }

            return [
                'success' => true,
                'message' => "Instruksi penuangan air dikirim ke kios {$kioskId}",
                'topic'   => $topic,
                'payload' => $payload,
            ];
        }

        // Fallback jika socket gagal
        Log::warning("MqttKioskService: Gagal kirim ke broker {$this->host}:{$this->port}, fallback ke polling");
        $transaksi->update([
            'status'  => 'DISPENSING',
            'remarks' => trim(($transaksi->remarks ?? '') . ' | Fallback to polling')
        ]);

        return [
            'success' => false,
            'message' => 'Broker MQTT offline, fallback diaktifkan.',
        ];
    }
}
