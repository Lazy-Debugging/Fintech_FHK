<?php

namespace App\Services;

use App\Models\Transaksi;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FonnteService
{
    /**
     * Kirim notifikasi WhatsApp via Fonnte API untuk transaksi yang berhasil dibayar
     */
    public static function sendPaymentNotification(mixed $transaksi): array
    {
        $token = config('services.fonnte.token', env('FONNTE_TOKEN', 'uyb4wurTrdytqeCvg7tu'));
        if (empty($token)) {
            Log::warning('Fonnte Notification skipped: FONNTE_TOKEN is empty');
            return ['status' => false, 'reason' => 'FONNTE_TOKEN is empty'];
        }

        // Ekstraksi data transaksi (mendukung objek Transaksi Eloquent, Array, atau stdClass)
        $userId      = is_array($transaksi) ? ($transaksi['user_id'] ?? null) : ($transaksi->user_id ?? null);
        $userObj     = !empty($userId) ? \App\Models\User::find($userId) : (function_exists('auth') && auth()->check() ? auth()->user() : null);

        $invoiceId   = is_array($transaksi) ? ($transaksi['invoiceId'] ?? '-') : ($transaksi->invoiceId ?? '-');
        $referenceId = is_array($transaksi) ? ($transaksi['referenceId'] ?? '-') : ($transaksi->referenceId ?? '-');
        
        $userName    = is_array($transaksi) ? ($transaksi['userName'] ?? null) : ($transaksi->userName ?? null);
        if (empty($userName) || str_starts_with((string)$userName, 'Pengunjung Tamu') || $userName === 'Pengunjung Kios') {
            $userName = $userObj?->name ?? ($userName ?: 'Pelanggan');
        }

        $userPhone   = is_array($transaksi) ? ($transaksi['userPhone'] ?? '') : ($transaksi->userPhone ?? '');
        if (empty($userPhone) || in_array($userPhone, ['0812000000', '08123456789', '081234567890', '-'])) {
            $userPhone = $userObj?->phone ?? $userPhone;
        }

        $waterType   = is_array($transaksi) ? ($transaksi['water_type'] ?? 'Air') : ($transaksi->water_type ?? 'Air');
        $volumeMl    = is_array($transaksi) ? ($transaksi['volume_ml'] ?? 0) : ($transaksi->volume_ml ?? 0);
        $payAmount   = is_array($transaksi) ? ($transaksi['payAmount'] ?? 0) : ($transaksi->payAmount ?? 0);
        $kioskId     = is_array($transaksi) ? ($transaksi['kiosk_id'] ?? null) : ($transaksi->kiosk_id ?? null);
        $kioskName   = is_array($transaksi) ? ($transaksi['kiosk_name'] ?? null) : ($transaksi->kiosk->name ?? null);

        $waterLabel = match (strtoupper((string) $waterType)) {
            'COLD', 'DINGIN' => 'Air Dingin ❄️',
            'HOT', 'PANAS'   => 'Air Panas ☕',
            default          => 'Air Normal 💧',
        };

        $kioskLabel = $kioskName ? "{$kioskName} ({$kioskId})" : ($kioskId ? "Kios {$kioskId}" : 'Fresh Hydration Kiosk');
        $amountFormatted = 'Rp ' . number_format((float) $payAmount, 0, ',', '.');
        $timeFormatted = date('d/m/Y H:i') . ' WIB';

        $message = "💧 *PEMBAYARAN BERHASIL - FRESH HYDRATION KIOSK* 💧\n\n"
            . "Halo *{$userName}*,\n"
            . "Pembayaran pesanan air minum Anda telah kami terima dan diverifikasi.\n\n"
            . "📋 *Detail Transaksi:*\n"
            . "• *No. Invoice* : `{$invoiceId}`\n"
            . "• *Ref ID*      : `{$referenceId}`\n"
            . "• *Menu Air*    : {$waterLabel}\n"
            . "• *Volume*      : {$volumeMl} ml\n"
            . "• *Total Bayar* : *{$amountFormatted}*\n"
            . "• *Lokasi Kios* : {$kioskLabel}\n"
            . "• *Waktu*       : {$timeFormatted}\n"
            . "• *Status*      : *LUNAS (PAID)* ✅\n\n"
            . "Silakan ambil air Anda pada dispenser kios.\n"
            . "Terima kasih telah menggunakan Fresh Hydration Kiosk! 🌿";

        // Susun target nomor WhatsApp penerima
        $targets = [];

        // 1. Nomor pelanggan dari transaksi jika valid
        $cleanPhone = preg_replace('/[^0-9]/', '', (string) $userPhone);
        if (!empty($cleanPhone) && !in_array($cleanPhone, ['0812000000', '08123456789', '081234567890', '081200000000', '0'])) {
            $targets[] = $cleanPhone;
        }

        // 2. Nomor admin / target default dari konfigurasi .env jika diisi
        $adminPhone = config('services.fonnte.target', env('FONNTE_TARGET', ''));
        if (!empty($adminPhone)) {
            $adminClean = preg_replace('/[^0-9]/', '', (string) $adminPhone);
            if (!empty($adminClean)) {
                $targets[] = $adminClean;
            }
        }

        $targets = array_unique(array_filter($targets));

        // Jika tidak ada nomor tujuan sama sekali, log dan lewati
        if (empty($targets)) {
            Log::info('Fonnte WhatsApp Notification skipped: No recipient target phone found', [
                'invoiceId' => $invoiceId,
            ]);
            return ['status' => false, 'reason' => 'No target phone number'];
        }

        $targetStr = implode(',', $targets);

        try {
            $response = Http::withHeaders([
                'Authorization' => $token,
            ])->asForm()->post('https://api.fonnte.com/send', [
                'target'      => $targetStr,
                'message'     => $message,
                'countryCode' => '62',
            ]);

            $json = $response->json();
            Log::info('Fonnte WhatsApp Notification response', [
                'invoiceId' => $invoiceId,
                'target'    => $targetStr,
                'result'    => $json,
            ]);

            return [
                'status'   => $response->successful(),
                'response' => $json,
            ];
        } catch (\Throwable $e) {
            Log::error('Fonnte WhatsApp Notification exception: ' . $e->getMessage(), [
                'invoiceId' => $invoiceId,
                'target'    => $targetStr,
            ]);

            return [
                'status' => false,
                'error'  => $e->getMessage(),
            ];
        }
    }
}
