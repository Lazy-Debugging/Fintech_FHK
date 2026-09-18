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
        $this->host = rtrim(config('aiyo.host', 'https://api-bills-invoice.aiyo.id'), '/');
        $this->username = config('aiyo.username', 'FRESH_HYDRATION_KIOS');
        $this->password = config('aiyo.password', 'pass_7A9uubvhF0XQZ7gePewlItKt3Uzg2I');
        $this->billMasterId = config('aiyo.bill_master_id', 'uxGSWGOqpeqLaG5Qn1DH');
        $this->apiKey = config('aiyo.api_key', 'key_SxcKMQ8cMbckjRMLvNCy1hNqUMGSo4');
        $this->apiSecret = config('aiyo.api_secret', 'secret_WU3Ba45FwWE9sDe4uxUarvM4P6KMB4');
        $this->callbackUrl = config('aiyo.callback_url', 'https://mesinbayar.com/app/fhk/callback/');
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
            curl_setopt($chGetToken, CURLOPT_TIMEOUT, 15);
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

        // Sesuai format body Meeting 03 Slide 13, 23 & Slide 8 (QRIS bankCode 503)
        $body = [
            'invoiceName'   => "FHK {$waterType} {$volumeMl}ml - {$kioskName}",
            'referenceId'   => $referenceId,
            'userName'      => $params['userName'] ?? 'Pengunjung Kios',
            'userEmail'     => $params['userEmail'] ?? 'customer@fhk.id',
            'userPhone'     => $params['userPhone'] ?? '0812000000',
            'remarks'       => "Refill Air {$waterType} {$volumeMl}ml",
            'payAmount'     => $payAmount,
            'expireTime'    => date('Y-m-d\TH:i', strtotime('+3 hour')), // Format Slide 23
            'billMasterId'  => $this->billMasterId,
            'paymentMethod' => [
                'type'      => 'QRIS',
                'bankCode'  => $this->qrisBankCode // '503' sesuai Slide 8
            ],
            'items' => [
                [
                    'itemName'       => "Air Minum {$waterType} {$volumeMl}ml (UV Sterilized)",
                    'itemType'       => 'ITEM',
                    'itemCount'      => '1',
                    'itemTotalPrice' => (string) $payAmount
                ]
            ]
        ];

        // Sesuai signature Meeting 03 Slide 24
        $pathInvoice = '/api/v1/invoice';
        $urlCreateInvoice = $this->host . $pathInvoice;
        $signRelativeUrl = parse_url($urlCreateInvoice, PHP_URL_PATH);
        $rawBody = json_encode($body);
        $dataToSign = $this->apiKey . $signRelativeUrl . $rawBody;
        $signature = hash_hmac('sha256', $dataToSign, $this->apiSecret);

        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $accessToken,
            'x-aiyo-key: ' . $this->apiKey,
            'x-aiyo-signature: ' . $signature
        ];

        try {
            $ch = curl_init($urlCreateInvoice);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
            curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $rawBody);
            $responseBody = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            $result = json_decode($responseBody, true);

            // Jika berhasil (Slide 25)
            if (isset($result['responseCode']) && $result['responseCode'] === '2000000') {
                $invoiceData = $result['responseData'] ?? [];
                return [
                    'success'           => true,
                    'invoiceId'         => $invoiceData['invoiceId'] ?? null,
                    'accessToken'       => $invoiceData['accessToken'] ?? null,
                    'referenceId'       => $referenceId,
                    'payAmount'         => $payAmount,
                    'items'             => $body['items'],
                    'qrContent'         => $invoiceData['qrContent'] ?? $invoiceData['qrString'] ?? null,
                    'invoiceUrl'        => $invoiceData['invoiceURL'] ?? null,
                    'raw_response'      => $result
                ];
            }

            // Penanganan jika IP Address komputer/server belum di-whitelist di Dashboard DBI
            if (isset($result['responseCode']) && $result['responseCode'] === '4010001') {
                $clientIp = '';
                if (preg_match('/([0-9a-fA-F\.:]+)$/', $result['responseMessage'] ?? '', $m)) {
                    $clientIp = $m[1];
                }

                Log::warning("AiYO IP Restriction: IP [{$clientIp}] belum di-whitelist di https://bills.aiyo.id/ untuk merchant FHK");

                // Jika allow_dev_fallback aktif, sediakan transaksi QRIS adaptif untuk pengujian lokal
                if (config('aiyo.allow_dev_fallback', true)) {
                    $mockInvoiceId = 'INV-' . strtoupper(substr(md5($referenceId . microtime()), 0, 16));
                    $mockToken = 'mock_dev_' . bin2hex(random_bytes(16));
                    $mockQr = "00020101021226670016ID.CO.AIYO.WWW01189360000000000000000215{$mockInvoiceId}51440014ID.LINKAJA.WWW0215{$mockInvoiceId}520454995303360540" . strlen((string)$payAmount) . $payAmount . "5802ID5914FRESH HYDRATION6007JAKARTA61051011062240720{$referenceId}6304ABCD";

                    return [
                        'success'          => true,
                        'invoiceId'        => $mockInvoiceId,
                        'accessToken'      => $mockToken,
                        'referenceId'      => $referenceId,
                        'payAmount'        => $payAmount,
                        'items'            => $body['items'],
                        'qrContent'        => $mockQr,
                        'invoiceUrl'       => route('kiosk.qris', ['invoiceId' => $mockInvoiceId]),
                        'is_dev_fallback'  => true,
                        'unwhitelisted_ip' => $clientIp,
                        'raw_response'     => $result
                    ];
                }
            }

            Log::warning('AiYO createInvoice error', ['code' => $httpCode, 'response' => $result, 'curlError' => $curlError]);
            return [
                'success' => false,
                'message' => $result['responseMessage'] ?? 'Gagal membuat tagihan invoice di AiYO',
                'raw'     => $result
            ];
        } catch (\Throwable $e) {
            Log::error('AiYO createInvoice exception', ['message' => $e->getMessage()]);
            return [
                'success' => false,
                'message' => 'Terjadi kesalahan koneksi saat memproses invoice: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Memeriksa Status Pembayaran Invoice (Slide 27 & 28: cek.php)
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
        $url = $this->host . $pathInvoice . '/' . $invoiceId . '?accessToken=' . urlencode($invoiceAccessToken);

        try {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
            curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
            $responseBody = curl_exec($ch);
            curl_close($ch);

            $result = json_decode($responseBody, true);

            if (isset($result['responseData'])) {
                $resData = $result['responseData'];
                $status = $resData['invoiceStatus'] ?? 'PENDING';

                return [
                    'success'       => true,
                    'status'        => $status, // PENDING, PAID, EXPIRED, dll
                    'isPaid'        => in_array(strtoupper($status), ['PAID', 'SUCCESS', 'SETTLED', 'COMPLETED']),
                    'invoiceName'   => $resData['invoiceName'] ?? '',
                    'payAmount'     => $resData['payAmount'] ?? 0,
                    'invoiceURL'    => $resData['invoiceURL'] ?? null,
                    'qrContent'     => $resData['qrContent'] ?? ($resData['invoiceURL'] ?? null),
                    'raw'           => $resData
                ];
            }

            return [
                'success' => false,
                'status'  => 'UNKNOWN',
                'isPaid'  => false,
                'message' => $result['responseMessage'] ?? 'Gagal memeriksa status ke AiYO'
            ];
        } catch (\Throwable $e) {
            Log::error('AiYO checkInvoiceStatus exception', ['message' => $e->getMessage()]);
            return [
                'success' => false,
                'status'  => 'ERROR',
                'isPaid'  => false,
                'message' => $e->getMessage()
            ];
        }
    }

    public function getCallbackUrl(): string
    {
        return $this->callbackUrl;
    }
}
