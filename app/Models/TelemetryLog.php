<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TelemetryLog extends Model
{
    protected $table = 'telemetry_logs';

    protected $fillable = [
        'kiosk_id',
        'water_level_cm',
        'water_level_pct',
        'temperature_celsius',
        'uv_lamp_active',
        'event_type',
        'notes',
        'recorded_at',
    ];

    protected $casts = [
        'water_level_cm' => 'float',
        'water_level_pct' => 'float',
        'temperature_celsius' => 'float',
        'uv_lamp_active' => 'boolean',
        'recorded_at' => 'datetime',
    ];

    public function kiosk(): BelongsTo
    {
        return $this->belongsTo(Kiosk::class, 'kiosk_id', 'id');
    }
}
