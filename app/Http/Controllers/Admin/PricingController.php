<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\KioskPricing;
use App\Models\Kiosk;
use Illuminate\Http\Request;

class PricingController extends Controller
{
    /**
     * Tampilkan halaman pengaturan harga
     */
    public function index(Request $request)
    {
        $kiosks   = Kiosk::all();
        $kioskId  = $request->query('kiosk_id'); // null = global

        // Ambil semua harga aktif (global + per kiosk)
        $globalPrices  = KioskPricing::whereNull('kiosk_id')->orderBy('water_type')->orderBy('volume_ml')->get();
        $kiosksWithPricing = Kiosk::all()->map(function ($kiosk) {
            $kiosk->prices = KioskPricing::where('kiosk_id', $kiosk->id)
                ->orderBy('water_type')->orderBy('volume_ml')->get();
            return $kiosk;
        });

        return view('admin.pricing', compact('kiosks', 'globalPrices', 'kiosksWithPricing'));
    }

    /**
     * Simpan perubahan harga (global atau per kiosk)
     */
    public function update(Request $request)
    {
        $request->validate([
            'prices'            => 'required|array',
            'prices.*.kiosk_id' => 'nullable|string|exists:kiosks,id',
            'prices.*.water_type' => 'required|in:COLD,NORMAL',
            'prices.*.volume_ml'  => 'required|integer|in:250,500,1000',
            'prices.*.price'      => 'required|integer|min:0|max:99999',
        ]);

        $adminName = auth()->user()->name ?? 'Admin';
        $updated   = 0;

        foreach ($request->input('prices') as $item) {
            KioskPricing::updateOrCreate(
                [
                    'kiosk_id'   => $item['kiosk_id'] ?: null,
                    'water_type' => $item['water_type'],
                    'volume_ml'  => (int) $item['volume_ml'],
                ],
                [
                    'price'      => (int) $item['price'],
                    'is_active'  => true,
                    'updated_by' => $adminName,
                ]
            );
            $updated++;
        }

        return response()->json([
            'success' => true,
            'message' => "Berhasil menyimpan {$updated} pengaturan harga.",
        ]);
    }

    /**
     * Reset harga kiosk tertentu ke harga global
     */
    public function resetToGlobal(Request $request)
    {
        $kioskId = $request->input('kiosk_id');
        if (!$kioskId) {
            return response()->json(['success' => false, 'message' => 'kiosk_id diperlukan.'], 422);
        }

        KioskPricing::where('kiosk_id', $kioskId)->delete();

        return response()->json([
            'success' => true,
            'message' => "Harga kios {$kioskId} berhasil direset ke harga global.",
        ]);
    }
}
