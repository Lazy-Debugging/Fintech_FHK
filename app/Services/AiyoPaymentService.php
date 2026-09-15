<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiyoPaymentService
{
    protected string $host;
    protected string $username;
    protected string $password;
    protected string $billMasterId;
    protected string $apiKey;
    protected string $apiSecret;

    public function __construct()
    {
        $this->host = rtrim(config('aiyo.host', 'https://api-bills-invoice.aiyo.id'), '/');
        $this->username = config('aiyo.username');
        $this->password = config('aiyo.password');
        $this->billMasterId = config('aiyo.bill_master_id');
        $this->apiKey = config('aiyo.api_key');
        $this->apiSecret = config('aiyo.api_secret');
    }

    /**
     * Mendapatkan OAuth Access Token dari AiYO (dengan caching 50 menit)
     */
    public function getAccessToken(bool $forceRefresh = false): ?string
    {
        $cacheKey = 'aiyo_oauth_access_token';

        if (!$forceRefresh && Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $url = $this->host . '/api/oauth/token';

        try {
            $response = Http::timeout(15)
                ->withHeaders([
                    'Authorization' => 'Basic ' . base64_encode($this->username . ':' . $this->password)
                ])
                ->post($url);

            if ($response->successful()) {
                $data = $response->json();
                $token = $data['responseData']['accessToken'] ?? null;

                if ($token) {
                    Cache::put($cacheKey, $token, now()->addMinutes(50));
                    return $token;
                }
            }

            Log::error('AiYO getAccessToken failed', ['response' => $response->body()]);
        } catch (\Throwable $e) {
            Log::error('AiYO getAccessToken exception', ['message' => $e->getMessage()]);
        }

        return null;
    }

    /**
     * Membuat Invoice / Tagihan QRIS Dinamis
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
        $payAmount = (int) ($params['payAmount'] ?? 2000);
        $waterType = $params['waterType'] ?? 'COLD';
        $volumeMl = (int) ($params['volumeMl'] ?? 500);
        $kioskName = $params['kioskName'] ?? 'Fresh Hydration Kios';

        $body = [
            'invoiceName' => "FHK {$waterType} {$volumeMl}ml - {$kioskName}",
            'referenceId' => $referenceId,
            'userName'    => $params['userName'] ?? 'Pengunjung Kios',
            'userEmail'   => $params['userEmail'] ?? 'customer@fhk.id',
            'userPhone'   => $params['userPhone'] ?? '0812000000',
            'remarks'     => "Refill Air {$waterType} {$volumeMl}ml",
            'payAmount'   => $payAmount,
            'expireTime'  => date('Y-m-d\TH:i', strtotime('+15 minutes')),
            'billMasterId' => $this->billMasterId,
            'paymentMethod' => [
                'type'     => 'VA_CLOSED',
                'bankCode' => '022'
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

        $pathInvoice = '/api/v1/invoice';
        $url = $this->host . $pathInvoice;
        $signRelativeUrl = parse_url($url, PHP_URL_PATH);
        $rawBody = json_encode($body);
        $dataToSign = $this->apiKey . $signRelativeUrl . $rawBody;
        $signature = hash_hmac('sha256', $dataToSign, $this->apiSecret);

        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'Content-Type'      => 'application/json',
                    'Authorization'     => 'Bearer ' . $accessToken,
                    'x-aiyo-key'        => $this->apiKey,
                    'x-aiyo-signature'  => $signature
                ])
                ->withBody($rawBody, 'application/json')
                ->post($url);

            $result = $response->json();

            if (isset($result['responseCode']) && $result['responseCode'] === '2000000') {
                $invoiceData = $result['responseData'] ?? [];
                return [
                    'success'           => true,
                    'invoiceId'         => $invoiceData['invoiceId'] ?? null,
                    'accessToken'       => $invoiceData['accessToken'] ?? null,
                    'referenceId'       => $referenceId,
                    'payAmount'         => $payAmount,
                    'items'             => $body['items'],
                    'raw_response'      => $result
                ];
            }

            Log::warning('AiYO createInvoice returned error code', ['response' => $result]);
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
     * Memeriksa Status Pembayaran Invoice
     */
    public function checkInvoiceStatus(string $invoiceId, string $invoiceAccessToken): array
    {
        $pathInvoice = '/api/v1/invoice';
        $url = $this->host . $pathInvoice . '/' . $invoiceId . '?accessToken=' . urlencode($invoiceAccessToken);

        try {
            $response = Http::timeout(15)->get($url);
            $result = $response->json();

            if ($response->successful() && isset($result['responseData'])) {
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
}
