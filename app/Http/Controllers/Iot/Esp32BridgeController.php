<?php

namespace App\Http\Controllers\Iot;

use App\Http\Controllers\Controller;
use App\Models\FilterMaintenance;
use App\Models\Kiosk;
use App\Models\Transaksi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class Esp32BridgeController extends Controller
{
    /**
     * Endpoint Polling bagi ESP32 untuk mengambil perintah penuangan air (Dispense Job)
     */
    public function getDispenseCommand(string $kioskId, Request $request): JsonResponse
    {
        $kiosk = Kiosk::find($kioskId);
        if (!$kiosk) {
            return response()->json(['status' => 'ERROR', 'message' => 'Kiosk ID tidak terdaftar'], 404);
        }

        // Verifikasi token keamanan hardware jika dikirim
        $token = $request->header('X-Kiosk-Secret') ?? $request->query('token');
        if ($token && $token !== $kiosk->api_secret_token && $token !== config('aiyo.kiosk_api_secret')) {
            return response()->json(['status' => 'UNAUTHORIZED', 'message' => 'Token hardware tidak valid'], 401);
        }

        // Update heartbeat kiosk
        $kiosk->update(['last_heartbeat' => now()]);

        // 0. Cek jika ada perintah cloud jarak jauh dari Dashboard Admin (misal: SET_WIFI)
        $pendingCmd = \Illuminate\Support\Facades\Cache::pull("kiosk_pending_cmd_{$kioskId}");
        if ($pendingCmd) {
            return response()->json($pendingCmd);
        }

        // Cari transaksi yang berstatus DISPENSING, QUEUED, atau PAID yang belum tuntas (dalam 2 jam terakhir)
        $pendingOrder = Transaksi::where(function($q) use ($kioskId) {
                $q->where('kiosk_id', $kioskId)->orWhereNull('kiosk_id')->orWhere('kiosk_id', '');
            })
            ->whereIn('status', ['DISPENSING', 'QUEUED', 'PAID'])
            ->whereNull('dispense_completed_at')
            ->where('created_at', '>=', now()->subHours(2))
            ->orderBy('created_at', 'asc')
            ->first();

        if ($pendingOrder) {
            // Pastikan status adalah DISPENSING dan waktu mulai tercatat
            if ($pendingOrder->status !== 'DISPENSING' || !$pendingOrder->dispense_started_at) {
                $pendingOrder->update([
                    'status' => 'DISPENSING',
                    'dispense_started_at' => now(),
                ]);
            }

            $kiosk->update(['status' => 'DISPENSING']);

            return response()->json([
                'status'                  => 'DISPENSE',
                'command'                 => 'DISPENSE',
                'job_id'                  => $pendingOrder->invoiceId,
                'reference_id'            => $pendingOrder->referenceId,
                'water_type'              => strtoupper($pendingOrder->water_type ?? 'NORMAL'),
                'volume_ml'               => (int) ($pendingOrder->volume_ml ?? 250),
                'max_cup_distance_cm'     => 22.0,
                'min_cup_distance_cm'     => 12.0,
                'timeout_sec'             => 60,
                'flow_pulse_calibration'  => 5880
            ]);
        }

        // Jika tidak ada job, laporkan IDLE
        if ($kiosk->status === 'DISPENSING') {
            $kiosk->update(['status' => 'ONLINE']);
        }

        return response()->json([
            'status'     => 'IDLE',
            'message'    => 'Tidak ada antrean penuangan air',
            'server_time'=> now()->toIso8601String()
        ]);
    }

    public function redeemPickup(string $kioskId, Request $request): JsonResponse
    {
        $kiosk = Kiosk::findOrFail($kioskId);

        // Verifikasi token keamanan jika dikirim
        $tokenSecret = $request->header('X-Kiosk-Secret') ?? $request->query('token_secret');
        if ($tokenSecret && $tokenSecret !== $kiosk->api_secret_token && $tokenSecret !== config('aiyo.kiosk_api_secret')) {
            return response()->json(['status' => 'UNAUTHORIZED', 'message' => 'Token hardware tidak valid'], 401);
        }

        $token = trim((string) $request->input('token'));
        if (empty($token)) {
            return response()->json(['status' => 'INVALID', 'message' => 'Token QR tidak boleh kosong.'], 400);
        }

        // Cek stok air kios
        if ($kiosk->current_water_level_pct <= 5.0 || $kiosk->status === 'OUT_OF_WATER') {
            return response()->json([
                'status'  => 'OUT_OF_WATER',
                'message' => 'Stok air di tangki kios habis. Hubungi teknisi untuk isi ulang.'
            ], 422);
        }

        $tokenHash = hash('sha256', $token);

        // Cari transaksi: mendukung token hash, invoiceId (INV-...), maupun referenceId
        $transaction = Transaksi::where(function($q) use ($kioskId) {
                $q->where('kiosk_id', $kioskId)->orWhereNull('kiosk_id')->orWhere('kiosk_id', '');
            })
            ->where(function($q) use ($token, $tokenHash) {
                $q->where('redemption_token_hash', $tokenHash)
                  ->orWhere('invoiceId', $token)
                  ->orWhere('referenceId', $token);
            })
            ->whereIn('status', ['PAID', 'AWAITING_KIOSK_SCAN', 'QUEUED'])
            ->whereNull('dispense_completed_at')
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$transaction) {
            return response()->json([
                'status'  => 'INVALID',
                'message' => 'QR Code tidak valid, belum lunas, atau sudah pernah digunakan.'
            ], 422);
        }

        // Aktifkan penuangan air untuk transaksi ini
        $transaction->update([
            'status'               => 'DISPENSING',
            'kiosk_id'             => $kioskId,
            'redeemed_at'          => now(),
            'dispense_started_at'  => now(),
            'remarks'              => trim(($transaction->remarks ?? '') . " | Redeemed via Kamera QR ESP32 " . date('H:i:s'))
        ]);

        $kiosk->update([
            'status'         => 'DISPENSING',
            'last_heartbeat' => now()
        ]);

        // Dispatch MQTT jika terhubung
        try {
            app(\App\Services\MqttKioskService::class)->dispatchDispenseJob($transaction);
        } catch (\Throwable $e) {}

        return response()->json([
            'status'                  => 'DISPENSE',
            'command'                 => 'DISPENSE',
            'job_id'                  => $transaction->invoiceId,
            'reference_id'            => $transaction->referenceId,
            'water_type'              => strtoupper($transaction->water_type ?? 'NORMAL'),
            'volume_ml'               => (int) ($transaction->volume_ml ?? 250),
            'max_cup_distance_cm'     => 22.0,
            'min_cup_distance_cm'     => 12.0,
            'timeout_sec'             => 60,
            'flow_pulse_calibration'  => 5880,
            'message'                 => "QR Valid! Menuangkan air {$transaction->volume_ml} mL..."
        ]);
    }

    /**
     * Laporan ESP32 bahwa penuangan air & siklus UV telah tuntas
     */
    public function reportDispenseComplete(string $kioskId, Request $request): JsonResponse
    {
        $kiosk = Kiosk::findOrFail($kioskId);

        $jobId = $request->input('job_id');
        $transaksi = Transaksi::where('invoiceId', $jobId)->first();

        if (!$transaksi) {
            return response()->json(['status' => 'ERROR', 'message' => 'Job ID tidak ditemukan'], 404);
        }

        $dispensedMl = (int) ($request->input('dispensed_ml') ?? $transaksi->volume_ml);
        $uvStatus = $request->input('uv_status', 'SUCCESS');

        // Update status transaksi ke COMPLETED
        $transaksi->update([
            'status' => 'COMPLETED',
            'dispense_completed_at' => now(),
        ]);

        // Kurangi estimasi kapasitas tanki air presisi berdasarkan output penjualan (mL)
        $litersUsed = $dispensedMl / 1000.0;
        $tankCapLiters = max(1.0, (float) ($kiosk->tank_capacity_liters ?? 20.0));
        $newLevelPct = max(0.0, (float) $kiosk->current_water_level_pct - (($litersUsed / $tankCapLiters) * 100.0));
        $newLevelPct = round($newLevelPct, 1);
        $remainingMl = (int) round(($tankCapLiters * 1000.0) * ($newLevelPct / 100.0));

        $newKioskStatus = ($newLevelPct <= 5.0) ? 'OUT_OF_WATER' : 'ONLINE';

        $kiosk->update([
            'status' => $newKioskStatus,
            'current_water_level_pct' => $newLevelPct,
            'last_heartbeat' => now()
        ]);

        // Kirim notifikasi otomatis jika stok air menipis (<20%) atau habis (<=5%)
        try {
            if ($newLevelPct <= 5.0) {
                if (class_exists(\App\Services\FonnteService::class)) {
                    \App\Services\FonnteService::sendWaterAlert($kiosk, $remainingMl, $newLevelPct, true);
                }
                if (class_exists(\App\Services\EmailNotificationService::class)) {
                    \App\Services\EmailNotificationService::sendWaterAlert($kiosk, $remainingMl, $newLevelPct, true);
                }
            } elseif ($newLevelPct <= 20.0) {
                if (class_exists(\App\Services\FonnteService::class)) {
                    \App\Services\FonnteService::sendWaterAlert($kiosk, $remainingMl, $newLevelPct, false);
                }
                if (class_exists(\App\Services\EmailNotificationService::class)) {
                    \App\Services\EmailNotificationService::sendWaterAlert($kiosk, $remainingMl, $newLevelPct, false);
                }
            }
        } catch (\Throwable $eNotify) {}

        // Update akumulasi pemakaian filter dan lampu UV
        $filters = FilterMaintenance::where('kiosk_id', $kioskId)->get();
        foreach ($filters as $filter) {
            $filter->increment('used_liters', $litersUsed);
            if ($filter->filter_type === 'UV_LAMP') {
                $filter->increment('operating_hours_used', 1);
            }

            // Periksa apakah sudah mencapai warning threshold (>90%)
            if ($filter->capacity_liters_limit > 0 && ($filter->used_liters / $filter->capacity_liters_limit) >= 0.90) {
                $filter->update(['status' => 'WARNING']);
            }
        }

        return response()->json([
            'status'           => 'SUCCESS',
            'message'          => 'Laporan penuangan air berhasil disimpan',
            'remaining_tank_pct'=> $kiosk->current_water_level_pct
        ]);
    }

    /**
     * Endpoint untuk Frontend PWA mengecek apakah ESP32 sudah selesai menuang air
     */
    public function getDispenseStatus(string $invoiceId): JsonResponse
    {
        $transaksi = Transaksi::find($invoiceId);
        if (!$transaksi) {
            return response()->json(['status' => 'NOT_FOUND'], 404);
        }

        $progress = \Illuminate\Support\Facades\Cache::get("kiosk_dispense_progress:{$invoiceId}");

        return response()->json([
            'status'          => $transaksi->status, // DISPENSING, COMPLETED
            'isCompleted'     => ($transaksi->status === 'COMPLETED'),
            'dispenseStarted' => $transaksi->dispense_started_at?->toIso8601String(),
            'dispenseFinished'=> $transaksi->dispense_completed_at?->toIso8601String(),
            'progress'        => $progress,
            'receiptUrl'      => route('kiosk.receipt', ['invoiceId' => $invoiceId])
        ]);
    }

    /**
     * Simulasi Hardware Selesai (Demo / Testing Mode untuk penguji)
     */
    public function simulateDispenseComplete(string $invoiceId): JsonResponse
    {
        $transaksi = Transaksi::findOrFail($invoiceId);
        $kioskId = $transaksi->kiosk_id ?? config('aiyo.default_kiosk_id', 'FHK-JAKARTA-01');

        $fakeRequest = new Request([
            'job_id'       => $invoiceId,
            'dispensed_ml' => $transaksi->volume_ml,
            'uv_status'    => 'SUCCESS'
        ]);

        $this->reportDispenseComplete($kioskId, $fakeRequest);

        return response()->json([
            'success'    => true,
            'status'     => 'COMPLETED',
            'receiptUrl' => route('kiosk.receipt', ['invoiceId' => $invoiceId])
        ]);
    }
}
