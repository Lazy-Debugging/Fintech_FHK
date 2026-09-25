<?php

namespace App\Http\Controllers;

use App\Models\Transaksi;
use App\Services\AiyoPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentCallbackController extends Controller
{
    public function __construct(private readonly AiyoPaymentService $aiyoService)
    {
    }

    /**
     * Endpoint publik untuk notifikasi pembayaran dari AiYO.
     * Daftarkan URL ini pada dashboard/provider: https://DOMAIN/api/aiyo/callback
     */
    public function handle(Request $request): JsonResponse
    {
        $payload = $request->json()->all() ?: $request->all();
        $data = $payload['responseData'] ?? $payload['data'] ?? $payload;
        $invoiceId = $data['invoiceId'] ?? $payload['invoiceId'] ?? null;
        $referenceId = $data['referenceId'] ?? $payload['referenceId'] ?? null;
        $status = strtoupper((string) ($data['invoiceStatus'] ?? $data['status'] ?? $payload['invoiceStatus'] ?? $payload['status'] ?? ''));

        $transaction = $invoiceId ? Transaksi::find($invoiceId) : null;
        $transaction ??= $referenceId ? Transaksi::where('referenceId', $referenceId)->first() : null;

        if (!$transaction) {
            Log::warning('AiYO callback for unknown transaction', ['payload' => $payload]);
            return response()->json(['received' => true]);
        }

        // Payload callback tidak dipercaya sebagai bukti pembayaran. Verifikasi ulang
        // ke AiYO memakai access token yang tersimpan pada transaksi.
        $verifiedStatus = null;
        if ($transaction->aiyo_access_token && !str_starts_with($transaction->aiyo_access_token, 'mock_')) {
            $verification = $this->aiyoService->checkInvoiceStatus(
                $transaction->invoiceId,
                $transaction->aiyo_access_token
            );
            $verifiedStatus = strtoupper((string) ($verification['status'] ?? ''));
        }

        if (in_array($verifiedStatus, ['PAID', 'SUCCESS', 'SETTLED', 'COMPLETED'], true)) {
            $transaction->update(['status' => 'PAID']);
        } elseif (in_array($verifiedStatus, ['EXPIRED', 'FAILED', 'CANCELLED'], true)) {
            $transaction->update(['status' => $verifiedStatus]);
        }

        Log::info('AiYO callback received', [
            'invoice_id' => $transaction->invoiceId,
            'callback_status' => $status,
            'verified_status' => $verifiedStatus,
        ]);

        return response()->json(['received' => true]);
    }
}
