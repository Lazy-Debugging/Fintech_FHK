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

        // Statistik transaksi
        $totalTransactions = Transaksi::where('kiosk_id', $kioskId)->count();
        $completedTransactions = Transaksi::where('kiosk_id', $kioskId)->where('status', 'COMPLETED')->count();
        $totalRevenue = Transaksi::where('kiosk_id', $kioskId)->where('status', 'COMPLETED')->sum('payAmount');
        $totalLitersDispensed = Transaksi::where('kiosk_id', $kioskId)->where('status', 'COMPLETED')->sum('volume_ml') / 1000.0;
        $totalWaterSoldMl = (int) Transaksi::where('kiosk_id', $kioskId)->where('status', 'COMPLETED')->sum('volume_ml');
        
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
}
