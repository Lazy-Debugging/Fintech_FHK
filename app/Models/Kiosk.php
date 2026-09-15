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
}
