<?php

namespace App\Http\Controllers;

use App\Models\Transaksi;
use App\Models\Voucher;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $guestToken = $request->session()->get('guest_order_id');

        // Auto-sync status transaksi PENDING ke AiYO Gateway saat profil dibuka
        $pendingTxs = Transaksi::where('status', 'PENDING')
            ->when($user, fn ($query) => $query->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                  ->orWhere('userEmail', $user->email);
            }))
            ->when(! $user, fn ($query) => $query->whereNull('user_id')->where('guest_token', $guestToken))
            ->limit(5)
            ->get();

        if ($pendingTxs->isNotEmpty()) {
            $aiyoService = app(\App\Services\AiyoPaymentService::class);
            foreach ($pendingTxs as $tx) {
                $token = $tx->aiyo_access_token ?? '';
                if (!str_starts_with($token, 'mock_')) {
                    $res = $aiyoService->checkInvoiceStatus($tx->invoiceId, $token);
                    if ($res['success'] && $res['isPaid']) {
                        $tx->update(['status' => 'PAID']);
                    } elseif ($res['success'] && in_array(strtoupper($res['status'] ?? ''), ['EXPIRED', 'CANCELLED', 'FAILED'])) {
                        $tx->update(['status' => strtoupper($res['status'])]);
                    }
                }
            }
        }

        $transactions = Transaksi::with('kiosk')
            ->when($user, fn ($query) => $query->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                  ->orWhere('userEmail', $user->email);
            }))
            ->when(! $user, fn ($query) => $query->whereNull('user_id')->where('guest_token', $guestToken))
            ->latest('created_at')
            ->paginate(10);

        $vouchers = $user
            ? Voucher::query()
                ->where('is_active', true)
                ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                ->where(fn ($query) => $query->whereNull('usage_limit')->orWhereColumn('usage_count', '<', 'usage_limit'))
                ->orderByDesc('created_at')
                ->get()
            : collect();

        return view('profile.index', compact('transactions', 'vouchers', 'user'));
    }
}
