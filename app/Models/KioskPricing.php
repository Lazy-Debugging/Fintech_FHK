<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class KioskPricing extends Model
{
    protected $table = 'kiosk_pricing';

    protected $fillable = [
        'kiosk_id',
        'water_type',
        'volume_ml',
        'price',
        'is_active',
        'updated_by',
    ];

    protected $casts = [
        'volume_ml'  => 'integer',
        'price'      => 'integer',
        'is_active'  => 'boolean',
    ];

    /**
     * Hardcoded defaults — dipakai sebagai fallback jika tabel belum dimigrasi.
     */
    public static function defaultMatrix(): array
    {
        return [
            'COLD'   => [250 => 2000, 500 => 3500, 1000 => 6000],
            'NORMAL' => [250 => 1500, 500 => 2500, 1000 => 4500],
        ];
    }

    /**
     * Ambil matriks harga dalam format array PHP:
     * ['COLD' => [250 => 2000, ...], 'NORMAL' => [...]]
     *
     * Prioritas: harga khusus kiosk > harga global (kiosk_id = null)
     * Fallback ke defaultMatrix() jika tabel belum ada.
     */
    public static function getPriceMatrix(?string $kioskId = null): array
    {
        try {
            $matrix = [
                'COLD'   => [],
                'NORMAL' => [],
            ];

            // Ambil harga global dulu
            $global = static::whereNull('kiosk_id')->where('is_active', true)->get();
            foreach ($global as $row) {
                $matrix[$row->water_type][$row->volume_ml] = $row->price;
            }

            // Override dengan harga khusus kiosk jika ada
            if ($kioskId) {
                $specific = static::where('kiosk_id', $kioskId)->where('is_active', true)->get();
                foreach ($specific as $row) {
                    $matrix[$row->water_type][$row->volume_ml] = $row->price;
                }
            }

            // Jika matrix kosong (tabel ada tapi tidak ada data), pakai default
            if (empty($matrix['COLD']) && empty($matrix['NORMAL'])) {
                return static::defaultMatrix();
            }

            return $matrix;
        } catch (\Throwable $e) {
            Log::warning('KioskPricing::getPriceMatrix fallback ke default', ['error' => $e->getMessage()]);
            return static::defaultMatrix();
        }
    }

    /**
     * Ambil harga spesifik untuk kombinasi water_type + volume_ml.
     * Fallback ke harga default jika tabel belum ada atau data tidak ditemukan.
     */
    public static function getPrice(string $waterType, int $volumeMl, ?string $kioskId = null): int
    {
        $defaults = static::defaultMatrix();

        try {
            // Cari harga khusus kiosk
            if ($kioskId) {
                $row = static::where('kiosk_id', $kioskId)
                    ->where('water_type', strtoupper($waterType))
                    ->where('volume_ml', $volumeMl)
                    ->where('is_active', true)
                    ->first();
                if ($row) return $row->price;
            }

            // Cari harga global
            $row = static::whereNull('kiosk_id')
                ->where('water_type', strtoupper($waterType))
                ->where('volume_ml', $volumeMl)
                ->where('is_active', true)
                ->first();
            if ($row) return $row->price;
        } catch (\Throwable $e) {
            Log::warning('KioskPricing::getPrice fallback ke default', ['error' => $e->getMessage()]);
        }

        // Fallback ke hardcoded
        return $defaults[strtoupper($waterType)][$volumeMl] ?? 2000;
    }
}

