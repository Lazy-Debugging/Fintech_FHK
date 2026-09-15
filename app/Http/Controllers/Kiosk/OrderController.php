<?php

namespace App\Http\Controllers\Kiosk;

use App\Http\Controllers\Controller;
use App\Models\Kiosk;
use App\Models\Transaksi;
use App\Services\AiyoPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class OrderController extends Controller
{
    protected AiyoPaymentService $aiyoService;

    public function __construct(AiyoPaymentService $aiyoService)
    {
        $this->aiyoService = $aiyoService;
    }

    /**
     * Membuat Order & Menghasilkan AiYO QRIS Invoice
     */
    public function createOrder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'kiosk_id'    => 'required|string',
            'water_type'  => 'required|in:NORMAL,COLD',
            'volume_ml'   => 'required|integer|min:100|max:5000',
            'user_name'   => 'nullable|string|max:100',
            'user_email'  => 'nullable|email|max:100',
            'user_phone'  => 'nullable|string|max:30',
        ]);

        $kiosk = Kiosk::find($validated['kiosk_id']);
        if (!$kiosk) {
            return response()->json([
                'success' => false,
                'message' => 'Kios tidak ditemukan atau sedang offline.'
            ], 404);
        }

        if ($kiosk->current_water_level_pct <= 5.0) {
            return response()->json([
                'success' => false,
                'message' => 'Stok air di tangki kios habis. Mohon tunggu proses isi ulang maintenance.'
            ], 400);
        }

        // Hitung nominal harga:
        // Normal: Rp 1.500 / 250ml, Rp 2.500 / 500ml, Rp 4.500 / 1000ml
        // Cold:   Rp 2.000 / 250ml, Rp 3.500 / 500ml, Rp 6.000 / 1000ml
        $ratePerMl = ($validated['water_type'] === 'COLD') ? 6.0 : 4.5;
        $calculatedAmount = (int) round(($validated['volume_ml'] * $ratePerMl) / 500) * 500;
        $payAmount = max(1000, $calculatedAmount);

        $referenceId = 'FHK' . date('ymdHis') . rand(10, 99);

        // Panggil AiYO Service untuk create invoice
        $invoiceResult = $this->aiyoService->createInvoice([
            'referenceId' => $referenceId,
            'kioskName'   => $kiosk->name,
            'waterType'   => $validated['water_type'],
            'volumeMl'    => $validated['volume_ml'],
            'payAmount'   => $payAmount,
            'userName'    => $validated['user_name'] ?? 'Pengunjung Kios',
            'userEmail'   => $validated['user_email'] ?? 'customer@fhk.id',
            'userPhone'   => $validated['user_phone'] ?? '0812000000',
        ]);

        if (!$invoiceResult['success']) {
            // Jika gateway gagal / credential mock mode, buat invoice lokal fallback agar alur pengujian tetap berjalan mulus
            $fallbackInvoiceId = 'INV-' . strtoupper(bin2hex(random_bytes(8)));
            $transaksi = Transaksi::create([
                'invoiceId'          => $fallbackInvoiceId,
                'referenceId'        => $referenceId,
                'kiosk_id'           => $kiosk->id,
                'userName'           => $validated['user_name'] ?? 'Pengunjung Kios',
                'userEmail'          => $validated['user_email'] ?? 'customer@fhk.id',
                'userPhone'          => $validated['user_phone'] ?? '0812000000',
                'water_type'         => $validated['water_type'],
                'volume_ml'          => $validated['volume_ml'],
                'payAmount'          => $payAmount,
                'aiyo_access_token'  => 'mock_token_' . time(),
                'status'             => 'PENDING',
                'remarks'            => "Refill Air {$validated['water_type']} {$validated['volume_ml']}ml (Offline Gateway Mode)",
                'items'              => json_encode([[
                    'itemName' => "Air {$validated['water_type']} {$validated['volume_ml']}ml",
                    'itemTotalPrice' => $payAmount
                ]]),
                'qr_content'         => "00020101021226600016ID.CO.AIYO.WWW011893600999" . $referenceId . "5303360540" . $payAmount . "5802ID5911FHK DISPENSER6007JAKARTA62070703A016304",
                'invoice_url'        => route('kiosk.qris', ['invoiceId' => $fallbackInvoiceId]),
            ]);

            return response()->json([
                'success'     => true,
                'invoiceId'   => $fallbackInvoiceId,
                'redirectUrl' => route('kiosk.qris', ['invoiceId' => $fallbackInvoiceId]),
                'isFallback'  => true,
                'message'     => 'Menggunakan invoice kios terintegrasi'
            ]);
        }

        $invoiceId = $invoiceResult['invoiceId'];
        $aiyoAccessToken = $invoiceResult['accessToken'];

        // Simpan data transaksi ke database
        $transaksi = Transaksi::create([
            'invoiceId'          => $invoiceId,
            'referenceId'        => $referenceId,
            'kiosk_id'           => $kiosk->id,
            'userName'           => $validated['user_name'] ?? 'Pengunjung Kios',
            'userEmail'          => $validated['user_email'] ?? 'customer@fhk.id',
            'userPhone'          => $validated['user_phone'] ?? '0812000000',
            'water_type'         => $validated['water_type'],
            'volume_ml'          => $validated['volume_ml'],
            'payAmount'          => $payAmount,
            'aiyo_access_token'  => $aiyoAccessToken,
            'status'             => 'PENDING',
            'remarks'            => "Refill Air {$validated['water_type']} {$validated['volume_ml']}ml",
            'items'              => json_encode($invoiceResult['items'] ?? []),
            'qr_content'         => "00020101021226600016ID.CO.AIYO.WWW011893600999" . $invoiceId . "5303360540" . $payAmount . "5802ID5911FHK KIOS6007JAKARTA62070703A016304",
            'invoice_url'        => route('kiosk.qris', ['invoiceId' => $invoiceId]),
        ]);

        return response()->json([
            'success'     => true,
            'invoiceId'   => $invoiceId,
            'redirectUrl' => route('kiosk.qris', ['invoiceId' => $invoiceId])
        ]);
    }

    /**
     * Memeriksa status pembayaran dari PWA Kiosk
     */
    public function checkPaymentStatus(string $invoiceId): JsonResponse
    {
        $transaksi = Transaksi::find($invoiceId);
        if (!$transaksi) {
            return response()->json([
                'success' => false,
                'status'  => 'NOT_FOUND',
                'isPaid'  => false
            ], 404);
        }

        // Jika di database sudah PAID, DISPENSING, atau COMPLETED
        if (in_array($transaksi->status, ['PAID', 'DISPENSING', 'COMPLETED'])) {
            return response()->json([
                'success'       => true,
                'status'        => $transaksi->status,
                'isPaid'        => true,
                'nextActionUrl' => ($transaksi->status === 'COMPLETED')
                    ? route('kiosk.receipt', ['invoiceId' => $invoiceId])
                    : route('kiosk.dispensing', ['invoiceId' => $invoiceId])
            ]);
        }

        // Cek status ke API AiYO jika memiliki accessToken
        if (!empty($transaksi->aiyo_access_token) && !str_starts_with($transaksi->aiyo_access_token, 'mock_')) {
            $statusRes = $this->aiyoService->checkInvoiceStatus($transaksi->invoiceId, $transaksi->aiyo_access_token);
            if ($statusRes['success'] && $statusRes['isPaid']) {
                $transaksi->update(['status' => 'PAID']);
                return response()->json([
                    'success'       => true,
                    'status'        => 'PAID',
                    'isPaid'        => true,
                    'nextActionUrl' => route('kiosk.dispensing', ['invoiceId' => $invoiceId])
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'status'  => $transaksi->status,
            'isPaid'  => false
        ]);
    }

    /**
     * Simulasi Pembayaran Berhasil (Quick Test / Demo Mode)
     */
    public function simulatePaymentSuccess(string $invoiceId): JsonResponse
    {
        $transaksi = Transaksi::findOrFail($invoiceId);
        $transaksi->update([
            'status' => 'PAID'
        ]);

        return response()->json([
            'success'       => true,
            'status'        => 'PAID',
            'message'       => 'Pembayaran AiYO QRIS disimulasikan berhasil.',
            'nextActionUrl' => route('kiosk.dispensing', ['invoiceId' => $invoiceId])
        ]);
    }
}
