<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaksi extends Model
{
    protected $table = 'transaksi';
    protected $primaryKey = 'invoiceId';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'invoiceId',
        'referenceId',
        'kiosk_id',
        'userName',
        'userEmail',
        'userPhone',
        'water_type',
        'volume_ml',
        'payAmount',
        'aiyo_access_token',
        'status',
        'remarks',
        'items',
        'qr_content',
        'invoice_url',
        'dispense_started_at',
        'dispense_completed_at',
        'timestamp',
    ];

    protected $casts = [
        'payAmount' => 'integer',
        'volume_ml' => 'integer',
        'items' => 'array',
        'dispense_started_at' => 'datetime',
        'dispense_completed_at' => 'datetime',
        'timestamp' => 'datetime',
    ];

    public function kiosk(): BelongsTo
    {
        return $this->belongsTo(Kiosk::class, 'kiosk_id', 'id');
    }
}
