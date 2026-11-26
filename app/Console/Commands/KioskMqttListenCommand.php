<?php

namespace App\Console\Commands;

use App\Models\FilterMaintenance;
use App\Models\Kiosk;
use App\Models\TelemetryLog;
use App\Models\Transaksi;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\MqttClient;

class KioskMqttListenCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'kiosk:mqtt-listen {--kiosk= : Filter kiosk ID tertentu}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Worker MQTT Subscriber untuk mendengarkan status, laporan selesai, dan telemetri dari ESP32 Kiosk';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $host = config('mqtt.host', '127.0.0.1');
        $port = (int) config('mqtt.port', 1883);
        $clientId = 'fhk_worker_' . getmypid() . '_' . substr(md5(uniqid()), 0, 4);
        $kioskFilter = $this->option('kiosk') ?: '+';

        $this->info("Menghubungkan ke MQTT Broker di {$host}:{$port}...");

        try {
            $mqtt = new MqttClient($host, $port, $clientId);

            $settings = (new ConnectionSettings())
                ->setKeepAliveInterval(config('mqtt.keep_alive', 60))
                ->setConnectTimeout(config('mqtt.timeout', 5));

            if (!empty(config('mqtt.username'))) {
                $settings->setUsername(config('mqtt.username'));
            }
            if (!empty(config('mqtt.password'))) {
                $settings->setPassword(config('mqtt.password'));
            }

            $mqtt->connect($settings, true);
            $this->info("Berhasil terhubung ke MQTT Broker! Mendengarkan topik kios: fhk/kiosk/{$kioskFilter}/#");

            // 1. Subscribe ke Topik Laporan Penuangan Selesai (fhk/kiosk/+/complete)
            $mqtt->subscribe("fhk/kiosk/{$kioskFilter}/complete", function (string $topic, string $message) {
                $this->handleCompleteMessage($topic, $message);
            }, 1);

            // 2. Subscribe ke Topik Status & Progres Live (fhk/kiosk/+/status)
            $mqtt->subscribe("fhk/kiosk/{$kioskFilter}/status", function (string $topic, string $message) {
                $this->handleStatusMessage($topic, $message);
            }, 0);

            // 3. Subscribe ke Topik Telemetri (fhk/kiosk/+/telemetry)
            $mqtt->subscribe("fhk/kiosk/{$kioskFilter}/telemetry", function (string $topic, string $message) {
                $this->handleTelemetryMessage($topic, $message);
            }, 0);

            // Jalankan loop listener FreeRTOS/Socket loop
            $mqtt->loop(true);

        } catch (\Throwable $e) {
            $this->error("Koneksi MQTT terputus atau gagal: " . $e->getMessage());
            Log::error('KioskMqttListenCommand exception: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return 1;
        }

        return 0;
    }

    /**
     * Memproses laporan penuangan air selesai dari ESP32
     */
    protected function handleCompleteMessage(string $topic, string $message): void
    {
        $data = json_decode($message, true);
        if (!$data || empty($data['job_id'])) {
            $this->warn("Complete payload tidak valid: {$message}");
            return;
        }

        $jobId = $data['job_id'];
        $status = strtoupper($data['status'] ?? 'SUCCESS');
        $dispensedMl = (int) ($data['dispensed_ml'] ?? 0);
        $faultCode = $data['fault_code'] ?? null;

        $this->line("<fg=green>[COMPLETE]</> Job: {$jobId} | Status: {$status} | Volume: {$dispensedMl} mL | Fault: {$faultCode}");

        $transaksi = Transaksi::find($jobId);
        if (!$transaksi) {
            $this->error("Transaksi {$jobId} tidak ditemukan di database.");
            return;
        }

        if ($status === 'SUCCESS' || $dispensedMl > 0) {
            $transaksi->update([
                'status'                => 'COMPLETED',
                'dispense_completed_at' => now(),
                'remarks'               => trim(($transaksi->remarks ?? '') . " | MQTT Selesai: {$dispensedMl}mL dituang " . ($faultCode ? "(Fault: {$faultCode})" : ""))
            ]);

            // Potong kapasitas tangki Kiosk
            $kioskId = $transaksi->kiosk_id ?: config('mqtt.default_kiosk_id', 'FHK-JAKARTA-01');
            $kiosk = Kiosk::find($kioskId);
            if ($kiosk) {
                $litersUsed = $dispensedMl / 1000.0;
                $newLevelPct = max(0, $kiosk->current_water_level_pct - (($litersUsed / max(1, $kiosk->tank_capacity_liters)) * 100));
                $kiosk->update([
                    'status'                  => 'ONLINE',
                    'current_water_level_pct' => round($newLevelPct, 1),
                    'last_heartbeat'          => now(),
                ]);

                // Tambahkan akumulasi pada filter maintenance
                $filters = FilterMaintenance::where('kiosk_id', $kioskId)->get();
                foreach ($filters as $filter) {
                    $filter->increment('used_liters', $litersUsed);
                }
            }

            // Hapus cache progress
            Cache::forget("kiosk_dispense_progress:{$jobId}");
        } else {
            // Jika gagal total tanpa ada air keluar
            $transaksi->update([
                'remarks' => trim(($transaksi->remarks ?? '') . " | MQTT Gagal: {$faultCode} - " . ($data['message'] ?? ''))
            ]);
        }
    }

    /**
     * Memproses pembaruan progres penuangan live untuk ditampilkan di layar Kios / HP
     */
    protected function handleStatusMessage(string $topic, string $message): void
    {
        $data = json_decode($message, true);
        if (!$data || empty($data['job_id'])) return;

        $jobId = $data['job_id'];

        // Simpan cache progres real-time dengan TTL 60 detik
        Cache::put("kiosk_dispense_progress:{$jobId}", [
            'job_id'       => $jobId,
            'state'        => $data['state'] ?? 'FILLING',
            'dispensed_ml' => (int) ($data['dispensed_ml'] ?? 0),
            'target_ml'    => (int) ($data['target_ml'] ?? 500),
            'progress_pct' => (float) ($data['progress_pct'] ?? 0),
            'flow_lpm'     => (float) ($data['flow_lpm'] ?? 0),
            'pump'         => (bool) ($data['pump'] ?? false),
            'updated_at'   => now()->toIso8601String(),
        ], 60);
    }

    /**
     * Memproses data telemetri berkala dari ESP32
     */
    protected function handleTelemetryMessage(string $topic, string $message): void
    {
        $data = json_decode($message, true);
        if (!$data) return;

        // Ekstraksi kiosk_id dari topik (fhk/kiosk/{id}/telemetry)
        $parts = explode('/', $topic);
        $kioskId = $parts[2] ?? ($data['kiosk_id'] ?? 'FHK-JAKARTA-01');

        $kiosk = Kiosk::find($kioskId);
        if ($kiosk) {
            $kiosk->update([
                'last_heartbeat' => now(),
            ]);
        }
    }
}

