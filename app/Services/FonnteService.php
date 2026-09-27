<?php

namespace App\Services;

use App\Models\Transaksi;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FonnteService
{
    public static ?string $lastError = null;
    public static ?array $lastResponse = null;

    public static function getEnvValue(string $key, mixed $default = null): mixed
    {
        if (function_exists('config')) {
            try {
                $cVal = config('services.fonnte.' . strtolower(str_replace('FONNTE_', '', $key)));
                if ($cVal !== null) return $cVal;
            } catch (\Throwable $e) {}
        }
        if (function_exists('env')) {
            try {
                $val = env($key);
                if ($val !== null && $val !== '') return $val;
            } catch (\Throwable $e) {}
        }
        if (isset($_ENV[$key]) && $_ENV[$key] !== '') return $_ENV[$key];
        if (isset($_SERVER[$key]) && $_SERVER[$key] !== '') return $_SERVER[$key];
        $val = getenv($key);
        return ($val !== false && $val !== '') ? $val : $default;
    }

    private static function executeFonntePost(string $token, array $postData): array
    {
        self::$lastError = null;
        self::$lastResponse = null;

        if (function_exists('curl_init')) {
            try {
                $ch = curl_init('https://api.fonnte.com/send');
                curl_setopt_array($ch, [
                    CURLOPT_POST           => true,
                    CURLOPT_POSTFIELDS     => http_build_query($postData),
                    CURLOPT_HTTPHEADER     => [
                        'Authorization: ' . $token,
                    ],
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_SSL_VERIFYHOST => 0,
                    CURLOPT_TIMEOUT        => 15,
                    CURLOPT_CONNECTTIMEOUT => 10,
                ]);

                $raw = curl_exec($ch);
                $err = curl_error($ch);
                $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($raw !== false && !empty($raw)) {
                    $json = json_decode($raw, true);
                    self::$lastResponse = $json ?: ['raw' => $raw];

                    $isSuccess = ($code >= 200 && $code < 300) && ($json['status'] ?? false);
                    if (!$isSuccess) {
                        self::$lastError = $json['reason'] ?? $json['detail'] ?? ("HTTP " . $code . ": " . $raw);
                    }

                    return [
                        'status'   => $isSuccess,
                        'response' => $json ?: $raw,
                        'http_code'=> $code,
                    ];
                }

                if (!empty($err)) {
                    self::$lastError = 'cURL Error: ' . $err;
                }
            } catch (\Throwable $eCurl) {
                self::$lastError = 'cURL Exception: ' . $eCurl->getMessage();
            }
        }

        try {
            if (class_exists(\Illuminate\Support\Facades\Http::class)) {
                $response = \Illuminate\Support\Facades\Http::withHeaders([
                    'Authorization' => $token,
                ])->asForm()->post('https://api.fonnte.com/send', $postData);

                $json = $response->json();
                self::$lastResponse = $json;

                $isSuccess = $response->successful() && ($json['status'] ?? false);
                if (!$isSuccess) {
                    self::$lastError = $json['reason'] ?? $json['detail'] ?? ('HTTP ' . $response->status());
                }

                return [
                    'status'   => $isSuccess,
                    'response' => $json,
                ];
            }
        } catch (\Throwable $eHttp) {
            self::$lastError = 'Http Facade Error: ' . $eHttp->getMessage();
        }

        return [
            'status' => false,
            'error'  => self::$lastError ?: 'Gagal menghubungi Fonnte API',
        ];
    }

    public static function sendPaymentNotification(mixed $transaksi): array
    {
        $token = self::getEnvValue('FONNTE_TOKEN', 'Y1vmkxaWWRXVsatHp3aG');
        if (empty($token)) {
            return ['status' => false, 'reason' => 'FONNTE_TOKEN is empty'];
        }

        $userId      = is_array($transaksi) ? ($transaksi['user_id'] ?? null) : ($transaksi->user_id ?? null);
        $userEmail   = is_array($transaksi) ? ($transaksi['userEmail'] ?? null) : ($transaksi->userEmail ?? null);
        $userName    = is_array($transaksi) ? ($transaksi['userName'] ?? null) : ($transaksi->userName ?? null);
        $userPhone   = is_array($transaksi) ? ($transaksi['userPhone'] ?? '') : ($transaksi->userPhone ?? '');
        $invoiceId   = is_array($transaksi) ? ($transaksi['invoiceId'] ?? '-') : ($transaksi->invoiceId ?? '-');
        $referenceId = is_array($transaksi) ? ($transaksi['referenceId'] ?? '-') : ($transaksi->referenceId ?? '-');
        $waterType   = is_array($transaksi) ? ($transaksi['water_type'] ?? 'Air') : ($transaksi->water_type ?? 'Air');
        $volumeMl    = is_array($transaksi) ? ($transaksi['volume_ml'] ?? 0) : ($transaksi->volume_ml ?? 0);
        $payAmount   = is_array($transaksi) ? ($transaksi['payAmount'] ?? 0) : ($transaksi->payAmount ?? 0);
        $kioskId     = is_array($transaksi) ? ($transaksi['kiosk_id'] ?? null) : ($transaksi->kiosk_id ?? null);
        $kioskName   = is_array($transaksi) ? ($transaksi['kiosk_name'] ?? null) : ($transaksi->kiosk->name ?? null);

        $userObj = null;
        if (!empty($userId)) {
            try { 
                if (class_exists(\App\Models\User::class)) {
                    $userObj = \App\Models\User::find($userId); 
                }
            } catch (\Throwable $e) {}
        }
        if (!$userObj && !empty($userEmail)) {
            try { 
                if (class_exists(\App\Models\User::class)) {
                    $userObj = \App\Models\User::where('email', $userEmail)->first(); 
                }
            } catch (\Throwable $e) {}
        }
        if (!$userObj) {
            try {
                if (function_exists('app') && app()->bound('auth') && function_exists('auth') && auth()->check()) {
                    $userObj = auth()->user();
                }
            } catch (\Throwable $e) {}
        }

        if (empty($userName) || str_starts_with((string)$userName, 'Pengunjung Tamu') || $userName === 'Pengunjung Kios') {
            $userName = $userObj?->name ?? ($userName ?: 'Pelanggan');
        }

        if (empty($userPhone) || in_array($userPhone, ['0812000000', '08123456789', '081234567890', '-'])) {
            $userPhone = $userObj?->phone ?? $userPhone;
        }

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

        $targets = [];
        $cleanTxPhone = preg_replace('/[^0-9]/', '', (string) $userPhone);
        if (!empty($cleanTxPhone) && !in_array($cleanTxPhone, ['0812000000', '08123456789', '081234567890', '081200000000', '0'])) {
            $targets[] = $cleanTxPhone;
        }

        if ($userObj && !empty($userObj->phone)) {
            $cleanUserPhone = preg_replace('/[^0-9]/', '', (string) $userObj->phone);
            if (!empty($cleanUserPhone) && !in_array($cleanUserPhone, ['0812000000', '08123456789', '081234567890', '081200000000', '0'])) {
                $targets[] = $cleanUserPhone;
            }
        }

        $adminPhone = self::getEnvValue('FONNTE_TARGET', '');
        if (!empty($adminPhone)) {
            $adminClean = preg_replace('/[^0-9]/', '', (string) $adminPhone);
            if (!empty($adminClean)) {
                $targets[] = $adminClean;
            }
        }

        $targets = array_unique(array_filter($targets));

        if (empty($targets)) {
            return ['status' => false, 'reason' => 'No target phone number'];
        }

        $targetStr = implode(',', $targets);

        $res = self::executeFonntePost($token, [
            'target'      => $targetStr,
            'message'     => $message,
            'countryCode' => '62',
        ]);

        return $res;
    }

    public static function sendMessage(string $target, string $message): array
    {
        $token = self::getEnvValue('FONNTE_TOKEN', 'Y1vmkxaWWRXVsatHp3aG');
        if (empty($token)) {
            return ['status' => false, 'reason' => 'FONNTE_TOKEN is empty'];
        }

        $cleanTarget = preg_replace('/[^0-9]/', '', $target);
        if (empty($cleanTarget)) {
            return ['status' => false, 'reason' => 'Target number is empty'];
        }

        return self::executeFonntePost($token, [
            'target'      => $cleanTarget,
            'message'     => $message,
            'countryCode' => '62',
        ]);
    }
}