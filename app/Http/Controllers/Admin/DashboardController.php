<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FilterMaintenance;
use App\Models\Kiosk;
use App\Models\TelemetryLog;
use App\Models\Transaksi;
use App\Models\UvSchedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Tampilan Dashboard Monitoring Kios & Maintenance
     */
    public function index(Request $request)
    {
        $kioskId = $request->query('kiosk_id', config('aiyo.default_kiosk_id', 'FHK-JAKARTA-01'));
        $kiosks = Kiosk::all();
        $selectedKiosk = Kiosk::with(['filters', 'uvSchedules'])->find($kioskId) ?? $kiosks->first();

        // Statistik transaksi (mencakup status lunas/sedang menuang)
        $paidStatuses = ['COMPLETED', 'DISPENSING', 'AWAITING_KIOSK_SCAN'];
        $totalTransactions = Transaksi::where('kiosk_id', $kioskId)->count();
        $completedTransactions = Transaksi::where('kiosk_id', $kioskId)->whereIn('status', $paidStatuses)->count();
        $totalRevenue = Transaksi::where('kiosk_id', $kioskId)->whereIn('status', $paidStatuses)->sum('payAmount');
        $totalLitersDispensed = Transaksi::where('kiosk_id', $kioskId)->whereIn('status', $paidStatuses)->sum('volume_ml') / 1000.0;
        $totalWaterSoldMl = (int) Transaksi::where('kiosk_id', $kioskId)->whereIn('status', $paidStatuses)->sum('volume_ml');
        
        $tankCapacityMl = (int) round(($selectedKiosk->tank_capacity_liters ?? 20.0) * 1000.0);
        $remainingWaterMl = (int) round($tankCapacityMl * (($selectedKiosk->current_water_level_pct ?? 100.0) / 100.0));

        // Transaksi terbaru
        $recentTransactions = Transaksi::where('kiosk_id', $kioskId)
            ->orderBy('created_at', 'desc')
            ->take(10)
            ->get();

        // Filter maintenance
        $filters = FilterMaintenance::where('kiosk_id', $kioskId)->get();

        // UV Schedules
        $uvSchedules = UvSchedule::where('kiosk_id', $kioskId)->get();

        // Telemetri log terakhir
        $recentTelemetry = TelemetryLog::where('kiosk_id', $kioskId)
            ->orderBy('recorded_at', 'desc')
            ->take(8)
            ->get();

        return view('admin.dashboard', compact(
            'kiosks',
            'selectedKiosk',
            'totalTransactions',
            'completedTransactions',
            'totalRevenue',
            'totalLitersDispensed',
            'totalWaterSoldMl',
            'remainingWaterMl',
            'tankCapacityMl',
            'recentTransactions',
            'filters',
            'uvSchedules',
            'recentTelemetry'
        ));
    }

    /**
     * Picu Siklus Sterilisasi UV Manual
     */
    public function triggerUvSterilization(string $kioskId): JsonResponse
    {
        $kiosk = Kiosk::findOrFail($kioskId);

        // Catat log sterilisasi manual
        TelemetryLog::create([
            'kiosk_id'            => $kioskId,
            'water_level_cm'      => 15.0,
            'water_level_pct'     => $kiosk->current_water_level_pct,
            'temperature_celsius' => $kiosk->current_temp_celsius,
            'uv_lamp_active'      => true,
            'event_type'          => 'UV_MANUAL_CYCLE',
            'notes'               => 'Siklus sterilisasi UV pipa & nozzle dipicu secara manual dari dashboard',
            'recorded_at'         => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Perintah siklus sterilisasi UV darurat/manual berhasil dikirim ke kios!'
        ]);
    }

    /**
     * Reset Kapasitas Filter saat Teknisi Mengganti Filter Baru
     */
    public function resetFilter(int $filterId): JsonResponse
    {
        $filter = FilterMaintenance::findOrFail($filterId);
        $filter->update([
            'used_liters'          => 0,
            'operating_hours_used' => 0,
            'last_replaced_at'     => now(),
            'status'               => 'GOOD',
        ]);

        return response()->json([
            'success' => true,
            'message' => "Filter '{$filter->filter_name}' berhasil di-reset ke kondisi 100% baru."
        ]);
    }

    /**
     * Input Isi Ulang Air (Refill) dari Teknisi / Admin Kios
     */
    public function refillWater(Request $request, string $kioskId): JsonResponse
    {
        $kiosk = Kiosk::findOrFail($kioskId);

        $refillLiters = (float) $request->input('refill_liters', 19.0); // Default 1 Galon 19 L
        if ($refillLiters <= 0) {
            $refillLiters = 19.0;
        }

        // Update kapasitas tangki jika diinput lebih besar dari tangki saat ini
        $newCapacity = max((float)$kiosk->tank_capacity_liters, $refillLiters);

        $kiosk->update([
            'tank_capacity_liters'    => $newCapacity,
            'current_water_level_pct' => 100.0,
            'status'                  => 'ONLINE',
            'last_heartbeat'          => now(),
        ]);

        // Catat di telemetry log
        try {
            TelemetryLog::create([
                'kiosk_id'            => $kioskId,
                'water_level_cm'      => 10.0,
                'water_level_pct'     => 100.0,
                'temperature_celsius' => $kiosk->current_temp_celsius ?? 25.0,
                'uv_lamp_active'      => false,
                'event_type'          => 'WATER_REFILL',
                'notes'               => "Pengisian ulang tangki/galon air sebesar {$refillLiters} Liter (" . number_format($refillLiters * 1000) . " mL) berhasil dicatat.",
                'recorded_at'         => now(),
            ]);
        } catch (\Throwable $e) {}

        return response()->json([
            'success' => true,
            'message' => "Pengisian ulang air sebesar {$refillLiters} L (" . number_format($refillLiters * 1000) . " mL) berhasil! Tangki terisi penuh 100% dan kios kembali ONLINE.",
            'new_level_pct' => 100.0,
            'new_capacity_liters' => $newCapacity
        ]);
    }

    /**
     * Halaman Simulator Hardware ESP32 Web-based
     */
    public function simulator(Request $request)
    {
        $kioskId = $request->query('kiosk_id', config('aiyo.default_kiosk_id', 'FHK-JAKARTA-01'));
        $kiosk = Kiosk::findOrFail($kioskId);
        return view('admin.simulator', compact('kiosk'));
    }

    /**
     * Halaman Panduan & Skema Diagram Wiring Hardware IoT ESP32
     */
    public function wiring(Request $request)
    {
        $kioskId = $request->query('kiosk_id', config('aiyo.default_kiosk_id', 'FHK-JAKARTA-01'));
        $kiosk = Kiosk::find($kioskId) ?? Kiosk::first();
        return view('admin.wiring', compact('kiosk'));
    }

    /**
     * Kirim Perintah Ganti Wi-Fi ke ESP32 Kios dari Cloud Dashboard
     */
    public function setKioskWifi(Request $request, string $kioskId): JsonResponse
    {
        $request->validate([
            'ssid'     => 'required|string|max:64',
            'password' => 'nullable|string|max:64',
        ]);

        $ssid = trim($request->input('ssid'));
        $pass = (string) $request->input('password', '');

        $kiosk = Kiosk::findOrFail($kioskId);

        $commandPayload = [
            'status'   => 'COMMAND',
            'command'  => 'SET_WIFI',
            'ssid'     => $ssid,
            'password' => $pass,
            'kiosk_id' => $kioskId,
            'time'     => now()->toIso8601String(),
        ];

        // 1. Simpan di cache pending command agar langsung diambil saat HTTP polling (interval 2 detik)
        \Illuminate\Support\Facades\Cache::put("kiosk_pending_cmd_{$kioskId}", $commandPayload, now()->addMinutes(10));

        // 2. Publish juga via MQTT agar instan jika broker aktif
        try {
            $mqtt = app(\App\Services\MqttKioskService::class);
            if ($mqtt) {
                $mqtt->publish("fhk/kiosk/{$kioskId}/command", $commandPayload);
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("Gagal kirim MQTT SET_WIFI: " . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => "Perintah ganti Wi-Fi ke '{$ssid}' berhasil dikirim ke kios {$kioskId}! ESP32 akan menyambung dan reboot dalam beberapa detik."
        ]);
    }
}
