<?php

namespace App\Http\Controllers\Kiosk;

use App\Http\Controllers\Controller;
use App\Models\Kiosk;
use App\Models\Transaksi;
use Illuminate\Http\Request;

class KioskScreenController extends Controller
{
    /**
     * Layar Utama Kios (Pilihan Suhu & Volume Air)
     */
    public function index(Request $request)
    {
        if (! $request->user() && ! $request->session()->has('guest_order_id')) {
            $request->session()->put('guest_order_id', bin2hex(random_bytes(16)));
        }

        $kioskId = $request->query('kiosk_id', config('aiyo.default_kiosk_id', 'FHK-JAKARTA-01'));
        $kiosk = Kiosk::firstOrCreate(
            ['id' => $kioskId],
            [
                'name' => 'FHK Stasiun Gambir No. 1',
                'location' => 'Lobby Stasiun Gambir, Jakarta Pusat',
                'api_secret_token' => config('aiyo.kiosk_api_secret', 'fhk_esp32_secret_token_2026'),
                'status' => 'ONLINE',
                'tank_capacity_liters' => 50.0,
                'current_water_level_pct' => 88.5,
                'current_temp_celsius' => 7.5,
                'last_heartbeat' => now(),
            ]
        );

        return view('kiosk.index', compact('kiosk'));
    }

    /**
     * Layar Pembayaran QRIS Dinamis
     */
    public function qris(string $invoiceId)
    {
        $transaksi = Transaksi::with('kiosk')->findOrFail($invoiceId);

        // Jika transaksi sudah selesai, langsung arahkan ke receipt
        if ($transaksi->status === 'COMPLETED') {
            return redirect()->route('kiosk.receipt', ['invoiceId' => $invoiceId]);
        }

        // Redirect langsung ke website resmi AiYO Bills Invoice Gateway!
        if (!empty($transaksi->invoice_url) && str_contains($transaksi->invoice_url, 'aiyo.id')) {
            return redirect()->away($transaksi->invoice_url);
        }

        if (!empty($transaksi->invoiceId) && !empty($transaksi->aiyo_access_token)) {
            $aiyoUrl = "https://bills-invoice.aiyo.id/bills/invoice/{$transaksi->invoiceId}?accessToken=" . urlencode($transaksi->aiyo_access_token);
            return redirect()->away($aiyoUrl);
        }

        return redirect()->away("https://bills-invoice.aiyo.id/bills/invoice/{$invoiceId}");
    }

    /**
     * Layar Animasi Pengisian & Sterilisasi UV
     */
    public function dispensing(string $invoiceId)
    {
        $transaksi = Transaksi::with('kiosk')->findOrFail($invoiceId);
        return view('kiosk.dispensing', compact('transaksi'));
    }

    /**
     * Layar Struk Digital
     */
    public function receipt(string $invoiceId)
    {
        $transaksi = Transaksi::with('kiosk')->findOrFail($invoiceId);
        return view('kiosk.receipt', compact('transaksi'));
    }
}
