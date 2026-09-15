<?php

namespace App\Http\Controllers\Iot;

use App\Http\Controllers\Controller;
use App\Models\Kiosk;
use App\Models\TelemetryLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TelemetryController extends Controller
{
    /**
     * Menerima telemetri berkala dari ESP32 (Ultrasonic Fluid Level, Suhu, Status UV)
     */
    public function recordTelemetry(string $kioskId, Request $request): JsonResponse
    {
        $kiosk = Kiosk::findOrFail($kioskId);

        $validated = $request->validate([
            'water_level_cm'      => 'required|numeric',
            'water_level_pct'     => 'required|numeric|min:0|max:100',
            'temperature_celsius' => 'nullable|numeric',
            'uv_lamp_active'      => 'nullable|boolean',
            'event_type'          => 'nullable|string',
            'notes'               => 'nullable|string',
        ]);

        $log = TelemetryLog::create([
            'kiosk_id'            => $kioskId,
            'water_level_cm'      => $validated['water_level_cm'],
            'water_level_pct'     => $validated['water_level_pct'],
            'temperature_celsius' => $validated['temperature_celsius'] ?? $kiosk->current_temp_celsius,
            'uv_lamp_active'      => $validated['uv_lamp_active'] ?? false,
            'event_type'          => $validated['event_type'] ?? 'HEARTBEAT',
            'notes'               => $validated['notes'] ?? null,
            'recorded_at'         => now(),
        ]);

        // Perbarui status kiosk saat ini
        $newStatus = $kiosk->status;
        if ($validated['water_level_pct'] <= 5.0) {
            $newStatus = 'OUT_OF_WATER';
        } elseif ($newStatus === 'OUT_OF_WATER' && $validated['water_level_pct'] > 10.0) {
            $newStatus = 'ONLINE';
        }

        $kiosk->update([
            'current_water_level_pct' => $validated['water_level_pct'],
            'current_temp_celsius'    => $validated['temperature_celsius'] ?? $kiosk->current_temp_celsius,
            'status'                  => $newStatus,
            'last_heartbeat'          => now(),
        ]);

        return response()->json([
            'success'          => true,
            'message'          => 'Telemetri berhasil disimpan',
            'current_kiosk_st' => $kiosk->status
        ]);
    }
}
