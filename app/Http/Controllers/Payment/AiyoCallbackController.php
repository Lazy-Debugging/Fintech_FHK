<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use App\Models\Transaksi;
use App\Services\AiyoPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AiyoCallbackController extends Controller
{
    protected AiyoPaymentService $aiyoService;

    public function __construct(AiyoPaymentService $aiyoService)
    {
        $this->aiyoService = $aiyoService;
    }

    /**
     * Menangani Webhook Callback Pembayaran dari AiYO Bills Invoice Gateway
     * Target URL: https://mesinbayar.com/app/fhk/callback/
     */
    public function handleCallback(Request $request): JsonResponse
    {
        // 1. Health check jika diakses via GET (pengecekan konektivitas endpoint)
        if ($request->isMethod('get')) {
            return response()->json([
                'status'    => 'OK',
                'service'   => 'AiYO Bills Invoice Callback Webhook Service (FHK)',
                'message'   => 'Endpoint aktif dan siap menerima notifikasi HTTP POST dari AiYO Gateway.',
                'timestamp' => now()->toIso8601String()
            ], 200);
        }

        $rawBody = $request->getContent();

        // 2. Logging Callback untuk audit & debugging
        Log::channel('daily')->info('AiYO Webhook Callback Received', [
            'ip'      => $request->ip(),
            'headers' => [
                'x-aiyo-key'       => $request->header('x-aiyo-key'),
                'x-aiyo-signature' => $request->header('x-aiyo-signature'),
                'content-type'     => $request->header('content-type'),
            ],
            'body'    => $rawBody,
        ]);

        // Catat ke file log terpisah aiyo_callback.log
        $logFile = storage_path('logs/aiyo_callback.log');
        $logEntry = '[' . date('Y-m-d H:i:s') . '] IP: ' . $request->ip() . ' | Payload: ' . $rawBody . PHP_EOL;
        @file_put_contents($logFile, $logEntry, FILE_APPEND);

        // 3. Parsing Data Payload
        $payload = $request->json()->all();
        if (empty($payload)) {
            $payload = $request->all();
        }

        // Ekstraksi invoiceId dan referenceId (Mendukung responseData bersarang maupun flat)
        $invoiceId = $payload['responseData']['invoiceId'] 
            ?? $payload['invoiceId'] 
            ?? $request->input('invoiceId');

        $referenceId = $payload['responseData']['referenceId'] 
            ?? $payload['referenceId'] 
            ?? $request->input('referenceId');

        $status = $payload['responseData']['invoiceStatus'] 
            ?? $payload['responseData']['status'] 
            ?? $payload['invoiceStatus'] 
            ?? $payload['status'] 
            ?? null;

        $paidAmount = $payload['responseData']['paidAmount'] 
            ?? $payload['responseData']['payAmount'] 
            ?? $payload['paidAmount'] 
            ?? $payload['payAmount'] 
            ?? null;

        if (empty($invoiceId) && empty($referenceId)) {
            Log::warning('AiYO Callback: Missing invoiceId and referenceId in payload', ['payload' => $payload]);
            return response()->json([
                'responseCode'    => '4000000',
                'responseMessage' => 'Invalid Payload: invoiceId or referenceId is required'
            ], 400);
        }

        // 4. Cari Transaksi di Database
        $transaksi = null;
        if (!empty($invoiceId)) {
            $transaksi = Transaksi::find($invoiceId);
        }
        if (!$transaksi && !empty($referenceId)) {
            $transaksi = Transaksi::where('referenceId', $referenceId)->first();
        }

        if (!$transaksi) {
            Log::error('AiYO Callback: Transaksi tidak ditemukan di database', [
                'invoiceId'   => $invoiceId,
                'referenceId' => $referenceId
            ]);

            return response()->json([
                'responseCode'    => '4040000',
                'responseMessage' => 'Transaction not found in FHK system'
            ], 404);
        }

        // 5. Cek Idempotensi (Jika transaksi sudah PAID, DISPENSING, atau COMPLETED)
        if (in_array($transaksi->status, ['PAID', 'DISPENSING', 'COMPLETED'])) {
            Log::info('AiYO Callback: Transaksi sudah diproses sebelumnya (Idempotent)', [
                'invoiceId' => $transaksi->invoiceId,
                'status'    => $transaksi->status
            ]);

            return response()->json([
                'responseCode'    => '2000000',
                'responseMessage' => 'Transaction already processed (Idempotent)',
                'responseData'    => [
                    'invoiceId' => $transaksi->invoiceId,
                    'status'    => $transaksi->status
                ]
            ], 200);
        }

        // 6. Validasi Status Pembayaran
        // Status sukses dari AiYO umumnya: PAID, SETTLED, SUCCESS, atau COMPLETED
        $isPaid = false;
        if ($status && in_array(strtoupper($status), ['PAID', 'SUCCESS', 'SETTLED', 'COMPLETED'])) {
            $isPaid = true;
        } else {
            // Jika status tidak tertera eksplisit, verifikasi ke API AiYO
            if (!empty($transaksi->aiyo_access_token) && !str_starts_with($transaksi->aiyo_access_token, 'mock_')) {
                $statusCheck = $this->aiyoService->checkInvoiceStatus($transaksi->invoiceId, $transaksi->aiyo_access_token);
                if ($statusCheck['success'] && $statusCheck['isPaid']) {
                    $isPaid = true;
                }
            } else {
                $isPaid = true;
            }
        }

        if ($isPaid) {
            // 7. Update Status Transaksi Menjadi PAID
            $transaksi->update([
                'status' => 'PAID',
                'remarks' => trim(($transaksi->remarks ?? '') . ' | Callback AiYO: ' . date('Y-m-d H:i:s'))
            ]);

            Log::info('AiYO Callback SUCCESS: Pembayaran Berhasil Diproses', [
                'invoiceId'   => $transaksi->invoiceId,
                'referenceId' => $transaksi->referenceId,
                'kiosk_id'    => $transaksi->kiosk_id,
                'payAmount'   => $transaksi->payAmount,
                'new_status'  => 'PAID'
            ]);

            return response()->json([
                'responseCode'    => '2000000',
                'responseMessage' => 'Payment successfully verified and processed',
                'responseData'    => [
                    'invoiceId'   => $transaksi->invoiceId,
                    'referenceId' => $transaksi->referenceId,
                    'status'      => 'PAID'
                ]
            ], 200);
        }

        // Jika status invoice EXPIRED atau CANCELLED
        if ($status && in_array(strtoupper($status), ['EXPIRED', 'CANCELLED', 'FAILED'])) {
            $transaksi->update([
                'status' => strtoupper($status)
            ]);

            Log::info('AiYO Callback: Status transaksi diperbarui ke ' . $status, [
                'invoiceId' => $transaksi->invoiceId
            ]);

            return response()->json([
                'responseCode'    => '2000000',
                'responseMessage' => 'Status updated to ' . $status,
                'responseData'    => [
                    'invoiceId' => $transaksi->invoiceId,
                    'status'    => $status
                ]
            ], 200);
        }

        return response()->json([
            'responseCode'    => '2000000',
            'responseMessage' => 'Callback received, no status change required',
            'responseData'    => [
                'invoiceId' => $transaksi->invoiceId,
                'status'    => $transaksi->status
            ]
        ], 200);
    }
}
