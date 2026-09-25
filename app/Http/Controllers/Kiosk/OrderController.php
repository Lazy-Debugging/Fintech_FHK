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
    public function createOrder(Request $request)
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
            if (!$request->expectsJson() && !$request->ajax()) {
                return redirect()->back()->with('error', 'Kios tidak ditemukan atau sedang offline.');
            }
            return response()->json([
                'success' => false,
                'message' => 'Kios tidak ditemukan atau sedang offline.'
            ], 404);
        }

        if ($kiosk->current_water_level_pct <= 5.0) {
            if (!$request->expectsJson() && !$request->ajax()) {
                return redirect()->back()->with('error', 'Stok air di tangki kios habis. Mohon tunggu proses isi ulang maintenance.');
            }
            return response()->json([
                'success' => false,
                'message' => 'Stok air di tangki kios habis. Mohon tunggu proses isi ulang maintenance.'
            ], 400);
        }

        $waterTypeKey = strtoupper($validated['water_type']);
        $volumeMlVal  = (int) $validated['volume_ml'];

        // Harga dari database dengan fallback ke hardcoded (mencegah crash jika tabel belum migrasi)
        try {
            if (class_exists(\App\Models\KioskPricing::class)) {
                $originalAmount = \App\Models\KioskPricing::getPrice($waterTypeKey, $volumeMlVal, $kiosk->id);
            } else {
                $priceMatrix = [
                    'COLD'   => [ 250 => 2000, 500 => 3500, 1000 => 6000 ],
                    'NORMAL' => [ 250 => 1500, 500 => 2500, 1000 => 4500 ],
                ];
                $originalAmount = $priceMatrix[$waterTypeKey][$volumeMlVal] ?? 3500;
            }
        } catch (\Throwable $e) {
            Log::warning('OrderController: KioskPricing fallback', ['error' => $e->getMessage()]);
            $priceMatrix = [
                'COLD'   => [ 250 => 2000, 500 => 3500, 1000 => 6000 ],
                'NORMAL' => [ 250 => 1500, 500 => 2500, 1000 => 4500 ],
            ];
            $originalAmount = $priceMatrix[$waterTypeKey][$volumeMlVal] ?? 3500;
        }

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

        // Penentuan Identitas Pelanggan (User vs Guest) — dengan fallback auth() yang lebih agresif
        $user = $request->user() ?? (function_exists('auth') ? auth()->user() : null);
        $guestToken = $request->hasSession() ? $request->session()->get('guest_order_id') : null;

        $inputEmail = trim((string) $request->input('user_email', ''));
        $inputName  = trim((string) $request->input('user_name', ''));

        if ($user) {
            $customerName  = $user->name;
            $customerEmail = $user->email;
            $customerPhone = $user->phone ?? '';
        } elseif (!empty($inputEmail) && filter_var($inputEmail, FILTER_VALIDATE_EMAIL)) {
            $matchedUser = \App\Models\User::where('email', $inputEmail)->first();
            $user = $matchedUser;
            $customerName  = !empty($inputName) ? $inputName : ($matchedUser?->name ?? 'Pengguna FHK');
            $customerEmail = $inputEmail;
            $customerPhone = $matchedUser?->phone ?? '';
        } else {
            $guestId = substr($guestToken ?? md5(microtime()), 0, 6);
            $customerName  = "Pengunjung Tamu (#{$guestId})";
            $customerEmail = "tamu.{$guestId}@fhk.id";
            $customerPhone = "";
        }

        Log::info('OrderController createOrder identity', [
            'user_id' => $user?->id,
            'user_name' => $customerName,
            'guest_token' => $guestToken,
            'is_authenticated' => !is_null($user),
            'payAmount' => $payAmount,
            'originalAmount' => $originalAmount,
        ]);

        $referenceId = 'FHK' . date('ymdHis') . rand(10, 99);

        // Panggil AiYO Service untuk create invoice
        $invoiceResult = $this->aiyoService->createInvoice([
            'referenceId' => $referenceId,
            'kioskName'   => $kiosk->name,
            'waterType'   => $validated['water_type'],
            'volumeMl'    => $validated['volume_ml'],
            'payAmount'   => $payAmount,
            'userName'    => $customerName,
            'userEmail'   => $customerEmail,
            'userPhone'   => $customerPhone,
        ]);

        if (!$invoiceResult['success']) {
            if (!$request->expectsJson() && !$request->ajax()) {
                return redirect()->back()->with('error', $invoiceResult['message'] ?? 'Gagal membuat tagihan di AiYO.');
            }
            return response()->json(['success' => false, 'message' => $invoiceResult['message'] ?? 'Gagal membuat tagihan di AiYO.'], 400);
        }

        $invoiceId = $invoiceResult['invoiceId'];
        $aiyoAccessToken = $invoiceResult['accessToken'];
        $actualPayAmount = (int) ($invoiceResult['payAmount'] ?? $payAmount);

        // Simpan data transaksi ke database
        $transaksi = Transaksi::create([
            'invoiceId'          => $invoiceId,
            'referenceId'        => $referenceId,
            'kiosk_id'           => $kiosk->id,
            'user_id'            => $user?->id,
            'guest_token'        => $guestToken,
            'voucher_id'         => $voucher?->id,
            'userName'           => $customerName,
            'userEmail'          => $customerEmail,
            'userPhone'          => $customerPhone,
            'water_type'         => $validated['water_type'],
            'volume_ml'          => $validated['volume_ml'],
            'payAmount'          => $actualPayAmount,
            'original_amount'    => $originalAmount,
            'discount_amount'    => $originalAmount - $payAmount,
            'aiyo_access_token'  => $aiyoAccessToken,
            'status'             => 'PENDING',
            'remarks'            => "Refill Air {$validated['water_type']} {$validated['volume_ml']}ml",
            'items'              => json_encode($invoiceResult['items'] ?? []),
            'qr_content'         => $invoiceResult['qrContent'] ?? $invoiceResult['invoiceUrl'],
            'invoice_url'        => $invoiceResult['invoiceUrl'],
        ]);
        if ($voucher && $user) { VoucherRedemption::create(['voucher_id' => $voucher->id, 'user_id' => $user->id, 'invoice_id' => $invoiceId]); $voucher->increment('usage_count'); }

        // Gunakan Route Internal Halaman QRIS Website FHK (In-App QRIS Payment)
        $redirectUrl = route('kiosk.qris', ['invoiceId' => $invoiceId]);

        if (!$request->expectsJson() && !$request->ajax()) {
            return redirect()->to($redirectUrl);
        }

        return response()->json([
            'success'     => true,
            'invoiceId'   => $invoiceId,
            'invoiceUrl'  => $redirectUrl,
            'redirectUrl' => $redirectUrl
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

        // Cek status ke API AiYO — selalu coba, bahkan jika token kosong.
        // Service sudah menangani fallback via OAuth Bearer token dan upstream server.
        $invoiceToken = $transaksi->aiyo_access_token ?? '';
        if (!str_starts_with($invoiceToken, 'mock_dev_')) {
            $statusRes = $this->aiyoService->checkInvoiceStatus($transaksi->invoiceId, $invoiceToken);
            Log::info('checkPaymentStatus poll', [
                'invoiceId' => $transaksi->invoiceId,
                'token_empty' => empty($invoiceToken),
                'result' => $statusRes
            ]);
            if ($statusRes['success'] && $statusRes['isPaid']) {
                $transaksi->update(['status' => 'PAID']); $this->preparePickup($transaksi);
                return response()->json([
                    'success'       => true,
                    'status'        => 'PAID',
                    'isPaid'        => true,
                    'nextActionUrl' => route('orders.collect', ['invoiceId' => $invoiceId])
                ]);
            }
            // Perbarui status jika AiYO mengembalikan EXPIRED / CANCELLED
            if ($statusRes['success'] && in_array(strtoupper($statusRes['status'] ?? ''), ['EXPIRED', 'CANCELLED', 'FAILED'])) {
                $transaksi->update(['status' => strtoupper($statusRes['status'])]);
                return response()->json([
                    'success' => true,
                    'status'  => strtoupper($statusRes['status']),
                    'isPaid'  => false
                ]);
            }
        }

        // Batas waktu pembayaran 3 menit: jika lewat 3 menit belum lunas, otomatis EXPIRED
        if ($transaksi->created_at && $transaksi->created_at->diffInSeconds(now()) >= 180 && in_array($transaksi->status, ['PENDING', 'NEW', 'UNPAID'])) {
            $transaksi->update([
                'status'  => 'EXPIRED',
                'remarks' => trim(($transaksi->remarks ?? '') . ' | Batas waktu 3 menit pembayaran berakhir')
            ]);
            return response()->json([
                'success' => true,
                'status'  => 'EXPIRED',
                'isPaid'  => false,
                'message' => 'Waktu pembayaran telah berakhir (3 menit).'
            ]);
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

    /**
     * Membatalkan transaksi PENDING
     */
    public function cancelOrder(Request $request, string $invoiceId)
    {
        $transaksi = Transaksi::findOrFail($invoiceId);
        
        $user = $request->user();
        $guestToken = $request->session()->get('guest_order_id');
        $isOwner = ($user && $transaksi->user_id === $user->id) || (!$user && $transaksi->guest_token === $guestToken);
        
        if (!$isOwner) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Anda tidak memiliki akses ke transaksi ini.'], 403);
            }
            return redirect()->back()->with('error', 'Anda tidak memiliki akses ke transaksi ini.');
        }

        if ($transaksi->status !== 'PENDING') {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Transaksi tidak dapat dibatalkan.'], 400);
            }
            return redirect()->back()->with('error', 'Transaksi tidak dapat dibatalkan.');
        }

        $transaksi->update(['status' => 'CANCELLED']);

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => 'Transaksi berhasil dibatalkan.']);
        }

        return redirect()->back()->with('success', 'Transaksi berhasil dibatalkan.');
    }

    /**
     * Memproses scan QR Kios dari Kamera HP pelanggan untuk memicu penuangan air
     */
    public function redeemScan(Request $request, string $invoiceId): JsonResponse
    {
        $request->validate([
            'kiosk_qr' => 'required|string',
        ]);

        $transaksi = Transaksi::findOrFail($invoiceId);

        if (!in_array($transaksi->status, ['PAID', 'AWAITING_KIOSK_SCAN'])) {
            return response()->json([
                'success' => false,
                'message' => 'Transaksi belum dibayar atau sudah pernah digunakan.'
            ], 400);
        }

        $scannedQr = trim($request->string('kiosk_qr'));
        $kioskId = $scannedQr;
        if (str_contains($scannedQr, ':')) {
            $parts = explode(':', $scannedQr);
            $kioskId = $parts[1] ?? $scannedQr;
        }

        $kiosk = Kiosk::find($kioskId) ?? Kiosk::first();

        if (!$kiosk) {
            return response()->json([
                'success' => false,
                'message' => 'Kios tidak ditemukan di sistem.'
            ], 404);
        }

        $transaksi->update([
            'status'   => 'DISPENSING',
            'kiosk_id' => $kiosk->id,
            'remarks'  => trim(($transaksi->remarks ?? '') . " | Redeemed via HP QR Scan pada Kios {$kiosk->id} " . date('H:i:s'))
        ]);

        return response()->json([
            'success'       => true,
            'message'       => 'Penuangan air berhasil dipicu di Kios ' . $kiosk->name . '!',
            'kiosk_name'    => $kiosk->name,
            'nextActionUrl' => route('kiosk.dispensing', ['invoiceId' => $invoiceId])
        ]);
    }

    /**
     * Polling oleh Layar Kios untuk mengecek apakah ada transaksi redeem baru dari HP
     */
    public function pollDispense(string $kioskId): JsonResponse
    {
        $transaksi = Transaksi::where('kiosk_id', $kioskId)
            ->whereIn('status', ['DISPENSING', 'QUEUED'])
            ->latest('updated_at')
            ->first();

        if ($transaksi) {
            return response()->json([
                'dispensing'  => true,
                'invoiceId'   => $transaksi->invoiceId,
                'redirectUrl' => route('kiosk.dispensing', ['invoiceId' => $transaksi->invoiceId])
            ]);
        }

        return response()->json([
            'dispensing' => false
        ]);
    }

    private function preparePickup(Transaksi $transaksi): void
    {
        if ($transaksi->redemption_token_hash) return;
        $token = Str::random(48);
        $transaksi->update([
            'status'                    => 'AWAITING_KIOSK_SCAN',
            'redemption_token_hash'     => hash('sha256', $token),
            'redemption_token_encrypted'=> Crypt::encryptString($token),
            'redemption_expires_at'     => now()->addMinutes(15)
        ]);

        // Trigger notifikasi Email dan WhatsApp langsung
        try {
            \App\Services\EmailNotificationService::sendPaymentEmail($transaksi);
            \App\Services\FonnteService::sendPaymentNotification($transaksi);
        } catch (\Throwable $e) {
            Log::error('Notification error in preparePickup: ' . $e->getMessage());
        }
    }
}
