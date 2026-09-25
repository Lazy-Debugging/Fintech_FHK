<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\KioskPricing;
use App\Models\Kiosk;
use Illuminate\Http\Request;

class PricingController extends Controller
{
    /**
     * Pastikan tabel kiosk_pricing ada dan memiliki data default
     */
    protected function ensureTableExists(): void
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('kiosk_pricing')) {
            \Illuminate\Support\Facades\Schema::create('kiosk_pricing', function (\Illuminate\Database\Schema\Blueprint $table) {
                $table->id();
                $table->string('kiosk_id', 30)->nullable()->index();
                $table->string('water_type', 20);
                $table->unsignedInteger('volume_ml');
                $table->unsignedInteger('price');
                $table->boolean('is_active')->default(true);
                $table->string('updated_by', 100)->nullable();
                $table->timestamps();
                $table->unique(['kiosk_id', 'water_type', 'volume_ml']);
            });

            $defaults = [
                ['kiosk_id' => null, 'water_type' => 'COLD',   'volume_ml' => 250,  'price' => 2000],
                ['kiosk_id' => null, 'water_type' => 'COLD',   'volume_ml' => 500,  'price' => 3500],
                ['kiosk_id' => null, 'water_type' => 'COLD',   'volume_ml' => 1000, 'price' => 6000],
                ['kiosk_id' => null, 'water_type' => 'NORMAL', 'volume_ml' => 250,  'price' => 1500],
                ['kiosk_id' => null, 'water_type' => 'NORMAL', 'volume_ml' => 500,  'price' => 2500],
                ['kiosk_id' => null, 'water_type' => 'NORMAL', 'volume_ml' => 1000, 'price' => 4500],
            ];

            foreach ($defaults as $row) {
                \Illuminate\Support\Facades\DB::table('kiosk_pricing')->insert(array_merge($row, [
                    'is_active'  => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]));
            }
        }
    }

    /**
     * Tampilkan halaman pengaturan harga
     */
    public function index(Request $request)
    {
        $this->ensureTableExists();

        $kiosks   = Kiosk::all();
        $kioskId  = $request->query('kiosk_id'); // null = global

        // Ambil semua harga aktif (global + per kiosk)
        $globalPrices  = KioskPricing::whereNull('kiosk_id')->orderBy('water_type')->orderBy('volume_ml')->get();

        // Jika data global kosong, populate default
        if ($globalPrices->isEmpty()) {
            foreach (KioskPricing::defaultMatrix() as $wt => $volumes) {
                foreach ($volumes as $vol => $prc) {
                    KioskPricing::updateOrCreate(
                        ['kiosk_id' => null, 'water_type' => $wt, 'volume_ml' => (int)$vol],
                        ['price' => (int)$prc, 'is_active' => true, 'updated_by' => 'System']
                    );
                }
            }
            $globalPrices = KioskPricing::whereNull('kiosk_id')->orderBy('water_type')->orderBy('volume_ml')->get();
        }

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
        $this->ensureTableExists();

        try {
            $validated = $request->validate([
                'prices'              => 'required|array',
                'prices.*.kiosk_id'   => 'nullable',
                'prices.*.water_type' => 'required|string|in:COLD,NORMAL,cold,normal',
                'prices.*.volume_ml'  => 'required|integer',
                'prices.*.price'      => 'required|integer|min:0|max:999999',
            ]);

            $adminName = auth()->user()->name ?? 'Admin';
            $updated   = 0;

            foreach ($request->input('prices') as $item) {
                $kioskId   = !empty($item['kiosk_id']) ? trim((string)$item['kiosk_id']) : null;
                $waterType = strtoupper(trim($item['water_type']));
                $volumeMl  = (int) $item['volume_ml'];
                $price     = (int) $item['price'];

                KioskPricing::updateOrCreate(
                    [
                        'kiosk_id'   => $kioskId,
                        'water_type' => $waterType,
                        'volume_ml'  => $volumeMl,
                    ],
                    [
                        'price'      => $price,
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
        } catch (\Illuminate\Validation\ValidationException $ve) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal: ' . implode(', ', $ve->validator->errors()->all()),
            ], 422);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('PricingController::update error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan harga: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reset harga kiosk tertentu ke harga global
     */
    public function resetToGlobal(Request $request)
    {
        $this->ensureTableExists();

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
