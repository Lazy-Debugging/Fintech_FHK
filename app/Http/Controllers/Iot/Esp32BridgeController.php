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

        // Cari transaksi yang berstatus PAID dan menunggu pengeluaran air
        $pendingOrder = Transaksi::where('kiosk_id', $kioskId)
            ->where('status', 'QUEUED')
            ->orderBy('created_at', 'asc')
            ->first();

        if ($pendingOrder) {
            // Ubah status ke DISPENSING
            $pendingOrder->update([
                'status' => 'DISPENSING',
                'dispense_started_at' => now(),
            ]);

            $kiosk->update(['status' => 'DISPENSING']);

            return response()->json([
                'status'                  => 'DISPENSE',
                'job_id'                  => $pendingOrder->invoiceId,
                'reference_id'            => $pendingOrder->referenceId,
                'water_type'              => $pendingOrder->water_type, // NORMAL atau COLD
                'volume_ml'               => $pendingOrder->volume_ml,
                'uv_sterilize_pre_sec'    => 3, // UV aktif sebelum valve dibuka
                'uv_sterilize_post_sec'   => 2, // UV tetap aktif untuk sterilisasi nozzle
                'flow_pulse_calibration'  => 450 // estimasi pulsa sensor aliran YF-S201 per liter
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
        abort_unless(hash_equals($kiosk->api_secret_token, (string) $request->header('X-Kiosk-Secret')), 401);
        $token = $request->validate(['token' => 'required|string|max:100'])['token'];
        $transaction = Transaksi::where('kiosk_id', $kioskId)->where('redemption_token_hash', hash('sha256', $token))->where('status', 'AWAITING_KIOSK_SCAN')->where('redemption_expires_at', '>', now())->first();
        if (!$transaction) return response()->json(['status' => 'INVALID', 'message' => 'QR tidak valid atau telah digunakan.'], 422);
        $transaction->update(['status' => 'QUEUED', 'redeemed_at' => now()]);
        return response()->json(['status' => 'QUEUED', 'message' => 'Pesanan diterima kiosk.']);
    }

    /**
     * Laporan ESP32 bahwa penuangan air & siklus UV telah tuntas
     */
    public function reportDispenseComplete(string $kioskId, Request $request): JsonResponse
    {
        $kiosk = Kiosk::findOrFail($kioskId);

        $jobId = $request->input('job_id');
        $transaksi = Transaksi::where('kiosk_id', $kioskId)
            ->where('invoiceId', $jobId)
            ->first();

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

        // Kurangi estimasi kapasitas tanki air
        $litersUsed = $dispensedMl / 1000.0;
        $newLevelPct = max(0, $kiosk->current_water_level_pct - (($litersUsed / $kiosk->tank_capacity_liters) * 100));
        $kiosk->update([
            'status' => 'ONLINE',
            'current_water_level_pct' => round($newLevelPct, 1),
            'last_heartbeat' => now()
        ]);

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

        return response()->json([
            'status'          => $transaksi->status, // DISPENSING, COMPLETED
            'isCompleted'     => ($transaksi->status === 'COMPLETED'),
            'dispenseStarted' => $transaksi->dispense_started_at?->toIso8601String(),
            'dispenseFinished'=> $transaksi->dispense_completed_at?->toIso8601String(),
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
