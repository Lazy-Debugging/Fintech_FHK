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
        'user_id',
        'voucher_id',
        'guest_token',
        'userName',
        'userEmail',
        'userPhone',
        'water_type',
        'volume_ml',
        'payAmount',
        'original_amount',
        'discount_amount',
        'aiyo_access_token',
        'status',
        'redemption_token_hash',
        'redemption_token_encrypted',
        'redemption_expires_at',
        'redeemed_at',
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
        'original_amount' => 'integer',
        'discount_amount' => 'integer',
        'volume_ml' => 'integer',
        'items' => 'array',
        'dispense_started_at' => 'datetime',
        'dispense_completed_at' => 'datetime',
        'timestamp' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'redemption_expires_at' => 'datetime',
        'redeemed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updated(function (Transaksi $transaksi) {
            if ($transaksi->wasChanged('status')) {
                $newStatus = strtoupper((string) $transaksi->status);
                $oldStatus = strtoupper((string) $transaksi->getOriginal('status'));

                // Kirim notifikasi WhatsApp & Email saat status berpindah menjadi Lunas (PAID / AWAITING_KIOSK_SCAN)
                if (in_array($newStatus, ['PAID', 'AWAITING_KIOSK_SCAN'], true) 
                    && !in_array($oldStatus, ['PAID', 'AWAITING_KIOSK_SCAN', 'DISPENSING', 'COMPLETED'], true)) {
                    try {
                        if (class_exists(\App\Services\FonnteService::class)) {
                            \App\Services\FonnteService::sendPaymentNotification($transaksi);
                        }
                    } catch (\Throwable $e) {}
                    try {
                        if (class_exists(\App\Services\EmailNotificationService::class)) {
                            \App\Services\EmailNotificationService::sendPaymentEmail($transaksi);
                        }
                    } catch (\Throwable $e) {}
                }

                // Kirim notifikasi Email saat tagihan tidak terbayar dan kedaluwarsa (EXPIRED / CANCELLED)
                if (in_array($newStatus, ['EXPIRED', 'CANCELLED'], true) 
                    && in_array($oldStatus, ['PENDING', 'NEW', 'UNPAID'], true)) {
                    try {
                        if (class_exists(\App\Services\EmailNotificationService::class)) {
                            \App\Services\EmailNotificationService::sendUnpaidExpiredEmail($transaksi);
                        }
                    } catch (\Throwable $e) {}
                }
            }
        });

        static::created(function (Transaksi $transaksi) {
            $status = strtoupper((string) $transaksi->status);
            if (in_array($status, ['PAID', 'AWAITING_KIOSK_SCAN'], true)) {
                try {
                    if (class_exists(\App\Services\FonnteService::class)) {
                        \App\Services\FonnteService::sendPaymentNotification($transaksi);
                    }
                } catch (\Throwable $e) {}
                try {
                    if (class_exists(\App\Services\EmailNotificationService::class)) {
                        \App\Services\EmailNotificationService::sendPaymentEmail($transaksi);
                    }
                } catch (\Throwable $e) {}
            }
        });
    }

    public function kiosk(): BelongsTo
    {
        return $this->belongsTo(Kiosk::class, 'kiosk_id', 'id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
