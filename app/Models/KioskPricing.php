<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
     * Ambil matriks harga dalam format array PHP:
     * ['COLD' => [250 => 2000, ...], 'NORMAL' => [...]]
     *
     * Prioritas: harga khusus kiosk > harga global (kiosk_id = null)
     */
    public static function getPriceMatrix(?string $kioskId = null): array
    {
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

        return $matrix;
    }

    /**
     * Ambil harga spesifik untuk kombinasi water_type + volume_ml.
     * Fallback ke harga default jika tidak ada di DB.
     */
    public static function getPrice(string $waterType, int $volumeMl, ?string $kioskId = null): int
    {
        $defaults = [
            'COLD'   => [250 => 2000, 500 => 3500, 1000 => 6000],
            'NORMAL' => [250 => 1500, 500 => 2500, 1000 => 4500],
        ];

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

        // Fallback ke hardcoded
        return $defaults[strtoupper($waterType)][$volumeMl] ?? 2000;
    }
}
