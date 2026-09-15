<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UvSchedule extends Model
{
    protected $table = 'uv_schedules';

    protected $fillable = [
        'kiosk_id',
        'cycle_time',
        'duration_seconds',
        'is_active',
        'last_executed_at',
    ];

    protected $casts = [
        'duration_seconds' => 'integer',
        'is_active' => 'boolean',
        'last_executed_at' => 'datetime',
    ];

    public function kiosk(): BelongsTo
    {
        return $this->belongsTo(Kiosk::class, 'kiosk_id', 'id');
    }
}
