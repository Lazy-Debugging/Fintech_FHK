<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Kiosk extends Model
{
    protected $table = 'kiosks';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'name',
        'location',
        'ip_address',
        'api_secret_token',
        'status',
        'tank_capacity_liters',
        'current_water_level_pct',
        'current_temp_celsius',
        'last_heartbeat',
    ];

    protected $casts = [
        'tank_capacity_liters' => 'float',
        'current_water_level_pct' => 'float',
        'current_temp_celsius' => 'float',
        'last_heartbeat' => 'datetime',
    ];

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaksi::class, 'kiosk_id', 'id');
    }

    public function filters(): HasMany
    {
        return $this->hasMany(FilterMaintenance::class, 'kiosk_id', 'id');
    }

    public function telemetries(): HasMany
    {
        return $this->hasMany(TelemetryLog::class, 'kiosk_id', 'id');
    }

    public function uvSchedules(): HasMany
    {
        return $this->hasMany(UvSchedule::class, 'kiosk_id', 'id');
    }

    /**
     * Kurangi kapasitas stok air tangki berdasarkan volume penjualan (mL)
     */
    public function deductWater(int $volumeMl): float
    {
        $litersUsed = $volumeMl / 1000.0;
        $tankCapLiters = max(1.0, (float) ($this->tank_capacity_liters ?? 20.0));
        $newLevelPct = max(0.0, (float) $this->current_water_level_pct - (($litersUsed / $tankCapLiters) * 100.0));
        $newLevelPct = round($newLevelPct, 1);
        
        $this->update([
            'current_water_level_pct' => $newLevelPct,
            'status' => ($newLevelPct <= 5.0) ? 'OUT_OF_WATER' : $this->status,
            'last_heartbeat' => now(),
        ]);

        return $newLevelPct;
    }
}
