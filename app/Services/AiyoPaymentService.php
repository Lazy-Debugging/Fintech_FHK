<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Service AiYO Bills Invoice Gateway
 * Diadaptasi dari Meeting 03 PHP Dunia Bayar Indonesia
 * (Slide 8, 9, 20, 21, 22, 23, 24, 25, 27)
 */
class AiyoPaymentService
{
    protected string $host;
    protected string $username;
    protected string $password;
    protected string $billMasterId;
    protected string $apiKey;
    protected string $apiSecret;
    protected string $callbackUrl;
    protected string $qrisBankCode;

    public function __construct()
    {
        $configFile = base_path('aiyo_config.php');
        if (file_exists($configFile)) {
            include $configFile;
            $this->host = isset($host) ? rtrim($host, '/') : "https://api-bills-invoice.aiyo.id";
            $this->username = $username ?? "FRESH_HYDRATION_KIOS";
            $this->password = $password ?? "pass_7A9uubvhF0XQZ7gePewlItKt3Uzg2I";
            $this->billMasterId = $billMasterId ?? "uxGSWGOqpeqLaG5Qn1DH";
            $this->apiKey = $api_key ?? "key_SxcKMQ8cMbckjRMLvNCy1hNqUMGSo4";
            $this->apiSecret = $api_secret ?? "secret_WU3Ba45FwWE9sDe4uxUarvM4P6KMB4";
            $this->callbackUrl = $callbackUrl ?? "https://mesinbayar.com/app/fhk/callback/";
        } else {
            $this->host = rtrim(config('aiyo.host', 'https://api-bills-invoice.aiyo.id'), '/');
            $this->username = config('aiyo.username', 'FRESH_HYDRATION_KIOS');
            $this->password = config('aiyo.password', 'pass_7A9uubvhF0XQZ7gePewlItKt3Uzg2I');
            $this->billMasterId = config('aiyo.bill_master_id', 'uxGSWGOqpeqLaG5Qn1DH');
            $this->apiKey = config('aiyo.api_key', 'key_SxcKMQ8cMbckjRMLvNCy1hNqUMGSo4');
            $this->apiSecret = config('aiyo.api_secret', 'secret_WU3Ba45FwWE9sDe4uxUarvM4P6KMB4');
            $this->callbackUrl = config('aiyo.callback_url', 'https://mesinbayar.com/app/fhk/callback/');
        }
        $this->qrisBankCode = config('aiyo.qris_bank_code', '503');
    }

    /**
     * Mendapatkan OAuth Access Token dari AiYO (Slide 10 & 22: token.php)
     */
    public function getAccessToken(bool $forceRefresh = false): ?string
    {
        $cacheKey = 'aiyo_oauth_access_token';

        if (!$forceRefresh && Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $pathToken = '/api/oauth/token';
        $urlGetToken = $this->host . $pathToken;

        try {
            $chGetToken = curl_init($urlGetToken);
            curl_setopt($chGetToken, CURLOPT_RETURNTRANSFER, TRUE);
            curl_setopt($chGetToken, CURLOPT_ENCODING, '');
            curl_setopt($chGetToken, CURLOPT_MAXREDIRS, 10);
            curl_setopt($chGetToken, CURLOPT_TIMEOUT, 10);
            curl_setopt($chGetToken, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($chGetToken, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($chGetToken, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
            curl_setopt($chGetToken, CURLOPT_FOLLOWLOCATION, TRUE);
            curl_setopt($chGetToken, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
            curl_setopt($chGetToken, CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt($chGetToken, CURLOPT_HTTPHEADER, [
                'Authorization: Basic ' . base64_encode($this->username . ':' . $this->password)
            ]);

            $result = curl_exec($chGetToken);
            $err = curl_error($chGetToken);
            curl_close($chGetToken);

            if ($result) {
                $nilai = json_decode($result, true);
                $token = $nilai['responseData']['accessToken'] ?? null;

                if ($token) {
                    Cache::put($cacheKey, $token, now()->addMinutes(50));
                    return $token;
                }
            }

            Log::error('AiYO getAccessToken failed', ['result' => $result, 'error' => $err]);
        } catch (\Throwable $e) {
            Log::error('AiYO getAccessToken exception', ['message' => $e->getMessage()]);
        }

        return null;
    }

    /**
     * Membuat Tagihan Invoice QRIS Dinamis
     * Diadaptasi dari Meeting 03 Slide 8, 13, 23, 24, 25 (respon.php)
     */
    public function createInvoice(array $params): array
    {
        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            return [
                'success' => false,
                'message' => 'Gagal mendapatkan OAuth Access Token dari AiYO Gateway'
            ];
        }

        $referenceId = $params['referenceId'] ?? ('FHK' . date('ymdHis') . rand(10, 99));
        $payAmount   = (int) ($params['payAmount'] ?? 2000);
        $waterType   = $params['waterType'] ?? 'COLD';
        $volumeMl    = (int) ($params['volumeMl'] ?? 500);
        $kioskName   = $params['kioskName'] ?? 'Fresh Hydration Kios';

        // Opsi metode pembayaran ke AiYO:
        // Opsi 1: null (General Invoice resmi AiYO sesuai Slide 12-14 DBI).
        //          Akun merchant FRESH_HYDRATION_KIOS belum di-whitelist untuk direct bankCode 503.
        //          General Invoice terbukti 100% sukses di AiYO dengan responseCode 2000000 tanpa error "Payment Bank Not Allowed".
        // Opsi 2: ['type' => 'QRIS', 'bankCode' => '503'] jika kelak bank 503 di-whitelist oleh DBI.
        $paymentOptions = [
            null,
            ['type' => 'QRIS', 'bankCode' => $this->qrisBankCode],
        ];

        $pathInvoice = '/api/v1/invoice';
        $urlCreateInvoice = $this->host . $pathInvoice;
        $signRelativeUrl = parse_url($urlCreateInvoice, PHP_URL_PATH);

        $result = null;
        $items = [
            [
                'itemName'       => "Air Minum {$waterType} {$volumeMl}ml (UV Sterilized)",
                'itemType'       => 'ITEM',
                'itemCount'      => '1',
                'itemTotalPrice' => (string) $payAmount
            ]
        ];

        foreach ($paymentOptions as $opt) {
            $body = [
                'invoiceName'   => "FHK {$waterType} {$volumeMl}ml",
                'referenceId'   => $referenceId,
                'userName'      => $params['userName'] ?? 'Pengunjung Kios',
                'userEmail'     => $params['userEmail'] ?? 'customer@fhk.id',
                'userPhone'     => $params['userPhone'] ?? '0812000000',
                'remarks'       => $params['remarks'] ?? "Refill Air {$waterType} {$volumeMl}ml",
                'payAmount'     => $payAmount,
                'expireTime'    => date('Y-m-d\TH:i', strtotime('+3 hour')),
                'billMasterId'  => $this->billMasterId,
                'items'         => $items
            ];

            if ($opt !== null) {
                $body['paymentMethod'] = $opt;
            }

            $rawBody = json_encode($body);
            $dataToSign = $this->apiKey . $signRelativeUrl . $rawBody;
            $signature = hash_hmac('sha256', $dataToSign, $this->apiSecret);

            $headers = [
                "Content-Type: application/json",
                "Authorization: Bearer " . $accessToken,
                "x-aiyo-key: " . $this->apiKey,
                "x-aiyo-signature: " . $signature
            ];

            try {
                $ch = curl_init($urlCreateInvoice);
                curl_setopt($ch, CURLOPT_TIMEOUT, 12);
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
                curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $rawBody);
                $responseBody = curl_exec($ch);
                curl_close($ch);

                $result = json_decode($responseBody, true);

                // Jika berhasil mendapatkan respon sukses dari AiYO Gateway
                if (isset($result['responseCode']) && $result['responseCode'] === '2000000') {
                    $invoiceData = $result['responseData'] ?? [];
                    Log::info("AiYO createInvoice: Sukses membuat invoice resmi AiYO!", ['invoiceId' => $invoiceData['invoiceId'] ?? null]);
                    return [
                        'success'           => true,
                        'invoiceId'         => $invoiceData['invoiceId'] ?? null,
                        'accessToken'       => $invoiceData['accessToken'] ?? null,
                        'referenceId'       => $referenceId,
                        'payAmount'         => $payAmount,
                        'items'             => $items,
                        'qrContent'         => $invoiceData['qrContent'] ?? $invoiceData['qrString'] ?? $invoiceData['invoiceURL'] ?? null,
                        'invoiceUrl'        => $invoiceData['invoiceURL'] ?? null,
                        'raw_response'      => $result
                    ];
                }
            } catch (\Throwable $e) {
                Log::error('AiYO createInvoice cURL exception', ['message' => $e->getMessage()]);
            }
        }

        // Fallback: Gunakan jembatan upstream respon.php jika IP lokal/pengembang belum di-whitelist
        $upstreamUrls = [
            'https://app.mesinbayar.com/fhk/respon.php?format=json',
            'https://mesinbayar.com/app/fhk/respon.php?format=json',
        ];

        foreach ($upstreamUrls as $upUrl) {
            try {
                $chUp = curl_init($upUrl);
                curl_setopt($chUp, CURLOPT_TIMEOUT, 8);
                curl_setopt($chUp, CURLOPT_POST, 1);
                curl_setopt($chUp, CURLOPT_RETURNTRANSFER, TRUE);
                curl_setopt($chUp, CURLOPT_SSL_VERIFYPEER, FALSE);
                curl_setopt($chUp, CURLOPT_SSL_VERIFYHOST, 0);
                curl_setopt($chUp, CURLOPT_POSTFIELDS, http_build_query([
                    'water_type'   => $waterType,
                    'volume_ml'    => $volumeMl,
                    'payAmount'    => $payAmount,
                    'userName'     => $params['userName'] ?? 'Pengunjung Kios',
                    'userEmail'    => $params['userEmail'] ?? 'customer@fhk.id',
                    'userPhone'    => $params['userPhone'] ?? '0812000000',
                    'referenceId'  => $referenceId,
                    'format'       => 'json'
                ]));
                $resUp = curl_exec($chUp);
                curl_close($chUp);

            $jsonUp = json_decode($resUp, true);
            if ($jsonUp && !empty($jsonUp['success']) && !empty($jsonUp['invoiceId'])) {
                Log::info("AiYO createInvoice: Berhasil melalui upstream respon.php!", ['invoiceId' => $jsonUp['invoiceId']]);
                return [
                    'success'           => true,
                    'invoiceId'         => $jsonUp['invoiceId'],
                    'accessToken'       => $jsonUp['accessToken'] ?? null,
                    'referenceId'       => $referenceId,
                    'payAmount'         => $payAmount,
                    'items'             => $items,
                    'qrContent'         => $jsonUp['qrContent'] ?? $jsonUp['invoiceUrl'],
                    'invoiceUrl'        => $jsonUp['invoiceUrl'] ?? null,
                    'is_upstream_live'  => true,
                    'raw_response'      => $jsonUp
                ];
            }
        } catch (\Throwable $e) {
            Log::warning('Upstream respon.php fallback failed: ' . $e->getMessage());
        }
    }

        return [
            'success' => false,
            'message' => $result['responseMessage'] ?? 'Gagal membuat tagihan invoice di AiYO',
            'raw'     => $result
        ];
    }

    /**
     * Memeriksa Status Pembayaran Invoice (Slide 27 & 28: cek.php)
     * Mendukung invoice accessToken maupun OAuth Bearer Token sebagai fallback.
     */
    public function checkInvoiceStatus(string $invoiceId, string $invoiceAccessToken): array
    {
        if (str_starts_with($invoiceAccessToken, 'mock_dev_')) {
            return [
                'success'     => true,
                'status'      => 'PENDING',
                'isPaid'      => false,
                'invoiceName' => 'Fresh Hydration Kios Refill',
                'payAmount'   => 0,
                'invoiceURL'  => null,
                'qrContent'   => null,
            ];
        }

        $pathInvoice = '/api/v1/invoice';

        // Coba 1: Invoice access token via query param (metode utama)
        if (!empty($invoiceAccessToken)) {
            $url = $this->host . $pathInvoice . '/' . $invoiceId . '?accessToken=' . urlencode($invoiceAccessToken);
            $result = $this->_doGetInvoice($url);
            if ($result !== null) {
                return $result;
            }
        }

        // Coba 2: OAuth Bearer token sebagai fallback (ketika invoice access token kosong/tidak valid)
        $oauthToken = $this->getAccessToken();
        if ($oauthToken) {
            $url = $this->host . $pathInvoice . '/' . $invoiceId;
            $result = $this->_doGetInvoice($url, $oauthToken);
            if ($result !== null) {
                return $result;
            }
        }

        // Coba 3: Upstream cek.php di live server sebagai terakhir
        $upstreamResult = $this->checkInvoiceStatusViaUpstream($invoiceId, $invoiceAccessToken);
        if ($upstreamResult !== null) {
            return $upstreamResult;
        }

        return [
            'success' => false,
            'status'  => 'UNKNOWN',
            'isPaid'  => false,
            'message' => 'Gagal memeriksa status invoice dari semua sumber'
        ];
    }

    /**
     * Helper internal: GET invoice dan parse hasilnya.
     * Mengembalikan null jika gagal/tidak ada data valid.
     */
    private function _doGetInvoice(string $url, ?string $bearerToken = null): ?array
    {
        try {
            $headers = ['Accept: application/json'];
            if ($bearerToken) {
                $headers[] = 'Authorization: Bearer ' . $bearerToken;
            }

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_TIMEOUT, 12);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            $responseBody = curl_exec($ch);
            curl_close($ch);

            $result = json_decode($responseBody, true);

            if (isset($result['responseData']) && is_array($result['responseData'])) {
                $resData     = $result['responseData'];
                $status      = $resData['invoiceStatus'] ?? $resData['status'] ?? $resData['paymentStatus'] ?? 'PENDING';
                $paidAmount  = (int) ($resData['paidAmount'] ?? $resData['payAmount'] ?? 0);
                $isPaidFlag  = !empty($resData['isPaid']);
                $statusUpper = strtoupper($status);

                $isPaid = in_array($statusUpper, ['PAID', 'SUCCESS', 'SETTLED', 'COMPLETED', 'PAYMENT_SUCCESS', 'SUCCEEDED', 'PAID_SETTLED'])
                        || $isPaidFlag
                        || ($paidAmount > 0 && !in_array($statusUpper, ['EXPIRED', 'CANCELLED', 'FAILED']));

                return [
                    'success'       => true,
                    'status'        => $isPaid ? 'PAID' : $statusUpper,
                    'isPaid'        => $isPaid,
                    'invoiceName'   => $resData['invoiceName'] ?? '',
                    'payAmount'     => $resData['payAmount'] ?? 0,
                    'invoiceURL'    => $resData['invoiceURL'] ?? null,
                    'qrContent'     => $resData['qrContent'] ?? ($resData['invoiceURL'] ?? null),
                    'raw'           => $resData
                ];
            }
            return null;
        } catch (\Throwable $e) {
            Log::warning('AiYO _doGetInvoice failed', ['url' => $url, 'error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Fallback: Cek status via upstream live server (cek.php) jika IP lokal tidak di-whitelist.
     */
    private function checkInvoiceStatusViaUpstream(string $invoiceId, string $invoiceAccessToken): ?array
    {
        $upstreamUrls = [
            'https://app.mesinbayar.com/fhk/cek.php',
            'https://mesinbayar.com/app/fhk/cek.php',
        ];
        foreach ($upstreamUrls as $upUrl) {
            try {
                $url = $upUrl . '?invoiceId=' . urlencode($invoiceId) . '&accessToken=' . urlencode($invoiceAccessToken) . '&format=json';
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_TIMEOUT, 8);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
                curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
                $res = curl_exec($ch);
                curl_close($ch);
                $json = json_decode($res, true);
                if ($json && (isset($json['invoiceStatus']) || isset($json['isPaid']))) {
                    $status = $json['invoiceStatus'] ?? $json['status'] ?? 'PENDING';
                    $statusUpper = strtoupper($status);
                    $isPaid = !empty($json['isPaid']) || in_array($statusUpper, ['PAID', 'SUCCESS', 'SETTLED', 'COMPLETED', 'PAYMENT_SUCCESS', 'SUCCEEDED', 'PAID_SETTLED']);
                    return [
                        'success'  => true,
                        'status'   => $isPaid ? 'PAID' : $statusUpper,
                        'isPaid'   => $isPaid,
                        'payAmount'=> $json['payAmount'] ?? 0,
                        'raw'      => $json
                    ];
                }
            } catch (\Throwable $e) {
                Log::warning('AiYO upstream cek.php failed: ' . $e->getMessage());
            }
        }
        return null;
    }

    public function getCallbackUrl(): string
    {
        return $this->callbackUrl;
    }
}
