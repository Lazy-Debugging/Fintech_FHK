<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FilterMaintenance extends Model
{
    protected $table = 'filter_maintenance';

    protected $fillable = [
        'kiosk_id',
        'filter_type',
        'filter_name',
        'capacity_liters_limit',
        'used_liters',
        'operating_hours_limit',
        'operating_hours_used',
        'last_replaced_at',
        'status',
    ];

    protected $casts = [
        'capacity_liters_limit' => 'float',
        'used_liters' => 'float',
        'operating_hours_limit' => 'integer',
        'operating_hours_used' => 'integer',
        'last_replaced_at' => 'date',
    ];

    public function kiosk(): BelongsTo
    {
        return $this->belongsTo(Kiosk::class, 'kiosk_id', 'id');
    }

    /**
     * Hitung sisa masa pakai dalam persen
     */
    public function getLifespanPercentageAttribute(): float
    {
        if ($this->filter_type === 'UV_LAMP' && $this->operating_hours_limit > 0) {
            $remaining = max(0, $this->operating_hours_limit - $this->operating_hours_used);
            return round(($remaining / $this->operating_hours_limit) * 100, 1);
        }

        if ($this->capacity_liters_limit > 0) {
            $remaining = max(0, $this->capacity_liters_limit - $this->used_liters);
            return round(($remaining / $this->capacity_liters_limit) * 100, 1);
        }

        return 100.0;
    }
}
