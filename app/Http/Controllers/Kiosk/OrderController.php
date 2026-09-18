<?php

namespace App\Http\Controllers\Kiosk;

use App\Http\Controllers\Controller;
use App\Models\Kiosk;
use App\Models\Transaksi;
use App\Models\Voucher;
use App\Models\VoucherRedemption;
use App\Services\AiyoPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

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
            'voucher_code' => 'nullable|string|max:40',
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
        $originalAmount = max(1000, $calculatedAmount);
        $payAmount = $originalAmount;
        $voucher = null;
        if ($request->filled('voucher_code')) {
            abort_unless($request->user(), 422, 'Voucher hanya tersedia untuk akun yang masuk.');
            $voucher = Voucher::where('code', Str::upper($request->string('voucher_code')))->first();
            abort_unless($voucher && $voucher->is_active && (!$voucher->starts_at || $voucher->starts_at->isPast()) && (!$voucher->expires_at || $voucher->expires_at->isFuture()) && $originalAmount >= $voucher->minimum_amount && (!$voucher->usage_limit || $voucher->usage_count < $voucher->usage_limit), 422, 'Voucher tidak berlaku.');
            abort_if(VoucherRedemption::where('voucher_id', $voucher->id)->where('user_id', $request->user()->id)->count() >= $voucher->per_user_limit, 422, 'Batas penggunaan voucher telah tercapai.');
            $discount = $voucher->discount_type === 'percent' ? (int) floor($originalAmount * $voucher->discount_value / 100) : $voucher->discount_value;
            $payAmount = max(0, $originalAmount - min($discount, $originalAmount));
        }

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
            return response()->json(['success' => false, 'message' => $invoiceResult['message']], 502);
        }

        $invoiceId = $invoiceResult['invoiceId'];
        $aiyoAccessToken = $invoiceResult['accessToken'];

        // Simpan data transaksi ke database
        $transaksi = Transaksi::create([
            'invoiceId'          => $invoiceId,
            'referenceId'        => $referenceId,
            'kiosk_id'           => $kiosk->id,
            'user_id'            => $request->user()?->id,
            'guest_token'        => $request->user() ? null : ($request->hasSession() ? $request->session()->get('guest_order_id') : null),
            'voucher_id'         => $voucher?->id,
            'userName'           => $validated['user_name'] ?? 'Pengunjung Kios',
            'userEmail'          => $validated['user_email'] ?? 'customer@fhk.id',
            'userPhone'          => $validated['user_phone'] ?? '0812000000',
            'water_type'         => $validated['water_type'],
            'volume_ml'          => $validated['volume_ml'],
            'payAmount'          => $payAmount,
            'original_amount'    => $originalAmount,
            'discount_amount'    => $originalAmount - $payAmount,
            'aiyo_access_token'  => $aiyoAccessToken,
            'status'             => 'PENDING',
            'remarks'            => "Refill Air {$validated['water_type']} {$validated['volume_ml']}ml",
            'items'              => json_encode($invoiceResult['items'] ?? []),
            'qr_content'         => $invoiceResult['qrContent'] ?? $invoiceResult['invoiceUrl'],
            'invoice_url'        => $invoiceResult['invoiceUrl'],
        ]);
        if ($voucher) { VoucherRedemption::create(['voucher_id' => $voucher->id, 'user_id' => $request->user()->id, 'invoice_id' => $invoiceId]); $voucher->increment('usage_count'); }

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
        if ($transaksi->status === 'PAID') { $this->preparePickup($transaksi); }
        if (in_array($transaksi->status, ['AWAITING_KIOSK_SCAN', 'QUEUED', 'DISPENSING', 'COMPLETED'])) {
            return response()->json([
                'success'       => true,
                'status'        => $transaksi->status,
                'isPaid'        => true,
                'nextActionUrl' => ($transaksi->status === 'COMPLETED')
                    ? route('kiosk.receipt', ['invoiceId' => $invoiceId])
                    : route('orders.collect', ['invoiceId' => $invoiceId])
            ]);
        }

        // Cek status ke API AiYO jika memiliki accessToken
        if (!empty($transaksi->aiyo_access_token) && !str_starts_with($transaksi->aiyo_access_token, 'mock_')) {
            $statusRes = $this->aiyoService->checkInvoiceStatus($transaksi->invoiceId, $transaksi->aiyo_access_token);
            if ($statusRes['success'] && $statusRes['isPaid']) {
                $transaksi->update(['status' => 'PAID']); $this->preparePickup($transaksi);
                return response()->json([
                    'success'       => true,
                    'status'        => 'PAID',
                    'isPaid'        => true,
                    'nextActionUrl' => route('orders.collect', ['invoiceId' => $invoiceId])
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
        abort_unless(app()->environment(['local', 'testing']), 404);

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

    private function preparePickup(Transaksi $transaksi): void
    {
        if ($transaksi->redemption_token_hash) return;
        $token = Str::random(48);
        $transaksi->update(['status' => 'AWAITING_KIOSK_SCAN', 'redemption_token_hash' => hash('sha256', $token), 'redemption_token_encrypted' => Crypt::encryptString($token), 'redemption_expires_at' => now()->addMinutes(15)]);
    }
}
