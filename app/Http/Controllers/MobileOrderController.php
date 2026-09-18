<?php

namespace App\Http\Controllers;

use App\Models\Transaksi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

class MobileOrderController extends Controller
{
    public function collect(Request $request, string $invoiceId)
    {
        $transaction = Transaksi::with('kiosk')->findOrFail($invoiceId);
        abort_unless($transaction->status === 'AWAITING_KIOSK_SCAN', 409);
        abort_unless($transaction->user_id === $request->user()?->id || (!$transaction->user_id && $transaction->guest_token === $request->session()->get('guest_order_id')), 403);
        $redemptionToken = Crypt::decryptString($transaction->redemption_token_encrypted);
        return view('mobile.collect', compact('transaction', 'redemptionToken'));
    }
}