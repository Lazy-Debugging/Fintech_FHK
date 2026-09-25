<?php

namespace App\Http\Controllers;

use App\Models\Transaksi;
use App\Models\Voucher;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user() ?? (function_exists('auth') ? auth()->user() : null);
        $guestToken = $request->session()->get('guest_order_id');

        if ($user) {
            // Auto-tautkan transaksi tanpa user_id yang memiliki userEmail, guest_token, atau userName sama
            Transaksi::whereNull('user_id')
                ->where(function ($q) use ($user, $guestToken) {
                    $q->where('userEmail', $user->email);
                    if ($guestToken) {
                        $q->orWhere('guest_token', $guestToken);
                    }
                    // Juga match berdasarkan userName yang mengandung nama user
                    $q->orWhere('userName', 'like', '%' . $user->name . '%');
                })
                ->update(['user_id' => $user->id]);
        }

        // Auto-sync status transaksi PENDING / NEW / UNPAID ke AiYO Gateway saat profil dibuka
        // Query lebih agresif: cari transaksi milik user ATAU milik guest_token yang sama
        $pendingTxs = Transaksi::whereIn('status', ['PENDING', 'NEW', 'UNPAID'])
            ->where(function ($query) use ($user, $guestToken) {
                if ($user) {
                    $query->where('user_id', $user->id)
                          ->orWhere('userEmail', $user->email);
                }
                if ($guestToken) {
                    $query->orWhere('guest_token', $guestToken);
                }
            })
            ->limit(10)
            ->get();

        if ($pendingTxs->isNotEmpty()) {
            $aiyoService = app(\App\Services\AiyoPaymentService::class);
            foreach ($pendingTxs as $tx) {
                // Auto-link user_id jika belum terisi dan user sudah login
                if ($user && !$tx->user_id) {
                    $tx->update(['user_id' => $user->id]);
                }

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

        $statusFilter = $request->query('status', 'all');
        $sortFilter   = $request->query('sort', 'latest');
        $searchFilter = trim($request->query('search', ''));

        // Query utama — lebih inklusif: gabungkan user_id, userEmail, DAN guest_token
        $transactionsQuery = Transaksi::with('kiosk')
            ->where(function ($query) use ($user, $guestToken) {
                if ($user) {
                    $query->where('user_id', $user->id)
                          ->orWhere('userEmail', $user->email);
                }
                if ($guestToken) {
                    $query->orWhere('guest_token', $guestToken);
                }
                // Jika tidak ada user dan tidak ada guestToken, jangan tampilkan apa-apa
                if (!$user && !$guestToken) {
                    $query->whereRaw('1 = 0');
                }
            });

        // Filter Status
        if ($statusFilter !== 'all') {
            if (in_array($statusFilter, ['PENDING', 'NEW', 'UNPAID'])) {
                $transactionsQuery->whereIn('status', ['PENDING', 'NEW', 'UNPAID']);
            } elseif ($statusFilter === 'PAID') {
                $transactionsQuery->whereIn('status', ['PAID', 'AWAITING_KIOSK_SCAN']);
            } elseif ($statusFilter === 'COMPLETED') {
                $transactionsQuery->whereIn('status', ['COMPLETED', 'DISPENSING']);
            } elseif ($statusFilter === 'CANCELLED') {
                $transactionsQuery->whereIn('status', ['CANCELLED', 'EXPIRED', 'FAILED']);
            } else {
                $transactionsQuery->where('status', $statusFilter);
            }
        }

        // Search Keyword
        if (!empty($searchFilter)) {
            $transactionsQuery->where(function ($q) use ($searchFilter) {
                $q->where('invoiceId', 'like', "%{$searchFilter}%")
                  ->orWhere('referenceId', 'like', "%{$searchFilter}%")
                  ->orWhere('water_type', 'like', "%{$searchFilter}%");
            });
        }

        // Sorting
        switch ($sortFilter) {
            case 'oldest':
                $transactionsQuery->orderBy('created_at', 'asc')->orderBy('timestamp', 'asc');
                break;
            case 'amount_high':
                $transactionsQuery->orderBy('payAmount', 'desc');
                break;
            case 'amount_low':
                $transactionsQuery->orderBy('payAmount', 'asc');
                break;
            case 'latest':
            default:
                $transactionsQuery->orderBy('created_at', 'desc')->orderBy('timestamp', 'desc');
                break;
        }

        $transactions = $transactionsQuery->paginate(10)->withQueryString();

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

    /**
     * Update Nomor WhatsApp / Telepon Pelanggan
     */
    public function updatePhone(Request $request)
    {
        $user = $request->user() ?? (function_exists('auth') ? auth()->user() : null);
        if (!$user) {
            return redirect()->route('login')->with('error', 'Silakan masuk terlebih dahulu untuk memperbarui nomor telepon.');
        }

        $validated = $request->validate([
            'phone' => 'nullable|string|max:30',
        ]);

        $rawPhone = trim($validated['phone'] ?? '');
        $cleanPhone = preg_replace('/[^0-9]/', '', $rawPhone);

        // Standarisasi nomor telepon Indonesia (e.g. 62812... -> 0812...)
        if (!empty($cleanPhone)) {
            if (str_starts_with($cleanPhone, '628')) {
                $cleanPhone = '08' . substr($cleanPhone, 3);
            }
        }

        $user->update(['phone' => $cleanPhone]);

        // Juga update nomor telepon pada transaksi pending milik user jika ada
        if (!empty($cleanPhone)) {
            Transaksi::where('user_id', $user->id)
                ->whereIn('status', ['NEW', 'PENDING', 'UNPAID'])
                ->update(['userPhone' => $cleanPhone]);
        }

        return redirect()->route('profile')->with('success', 'Nomor WhatsApp berhasil diperbarui! Notifikasi pembayaran otomatis akan dikirim ke nomor ini.');
    }
}
