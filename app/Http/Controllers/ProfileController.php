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

        $transactions = Transaksi::with('kiosk')
            ->when($user, fn ($query) => $query->where('user_id', $user->id))
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
