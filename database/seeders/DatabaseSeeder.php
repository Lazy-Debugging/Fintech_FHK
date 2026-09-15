<?php

namespace Database\Seeders;

use App\Models\FilterMaintenance;
use App\Models\Kiosk;
use App\Models\TelemetryLog;
use App\Models\Transaksi;
use App\Models\UvSchedule;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Kiosk Utama
        $kiosk = Kiosk::updateOrCreate(
            ['id' => 'FHK-JAKARTA-01'],
            [
                'name'                    => 'FHK Stasiun Gambir No. 1',
                'location'                => 'Lobby Stasiun Gambir, Jakarta Pusat',
                'ip_address'              => '192.168.1.105',
                'api_secret_token'        => 'fhk_esp32_secret_token_2026',
                'status'                  => 'ONLINE',
                'tank_capacity_liters'    => 50.0,
                'current_water_level_pct' => 88.5,
                'current_temp_celsius'    => 7.5,
                'last_heartbeat'          => now(),
            ]
        );

        // 2. Filter & UV Lamp Maintenance
        FilterMaintenance::updateOrCreate(
            ['kiosk_id' => $kiosk->id, 'filter_name' => 'Sediment Filter Polypropylene 5μm'],
            [
                'filter_type'            => 'SEDIMENT',
                'capacity_liters_limit'  => 5000.0,
                'used_liters'            => 940.0,
                'last_replaced_at'       => now()->subMonths(1),
                'status'                 => 'GOOD'
            ]
        );

        FilterMaintenance::updateOrCreate(
            ['kiosk_id' => $kiosk->id, 'filter_name' => 'Activated Carbon Block CTO'],
            [
                'filter_type'            => 'CARBON_BLOCK',
                'capacity_liters_limit'  => 5000.0,
                'used_liters'            => 940.0,
                'last_replaced_at'       => now()->subMonths(1),
                'status'                 => 'GOOD'
            ]
        );

        FilterMaintenance::updateOrCreate(
            ['kiosk_id' => $kiosk->id, 'filter_name' => 'Ultrafiltration Hollow Fiber 0.01μm'],
            [
                'filter_type'            => 'ULTRAFILTRATION',
                'capacity_liters_limit'  => 10000.0,
                'used_liters'            => 1420.0,
                'last_replaced_at'       => now()->subMonths(2),
                'status'                 => 'GOOD'
            ]
        );

        FilterMaintenance::updateOrCreate(
            ['kiosk_id' => $kiosk->id, 'filter_name' => 'Philips UV-C Germicidal Sterilizer Lamp'],
            [
                'filter_type'            => 'UV_LAMP',
                'capacity_liters_limit'  => 0,
                'used_liters'            => 0,
                'operating_hours_limit'  => 9000,
                'operating_hours_used'   => 1480,
                'last_replaced_at'       => now()->subMonths(3),
                'status'                 => 'GOOD'
            ]
        );

        // 3. Jadwal Sterilisasi UV Otomatis (ESP32 Scheduled Routine)
        UvSchedule::updateOrCreate(
            ['kiosk_id' => $kiosk->id, 'cycle_time' => '02:00'],
            [
                'duration_seconds'  => 180,
                'is_active'         => true,
                'last_executed_at'  => now()->subDay()->setTime(2, 0)
            ]
        );

        UvSchedule::updateOrCreate(
            ['kiosk_id' => $kiosk->id, 'cycle_time' => '14:30'],
            [
                'duration_seconds'  => 60,
                'is_active'         => true,
                'last_executed_at'  => now()->subDay()->setTime(14, 30)
            ]
        );

        // 4. Riwayat Transaksi Demo
        $sampleOrders = [
            [
                'invoiceId'   => 'INV-AIYO-001',
                'referenceId' => 'FHK260915001',
                'water_type'  => 'COLD',
                'volume_ml'   => 500,
                'payAmount'   => 3500,
                'status'      => 'COMPLETED',
                'created_at'  => now()->subMinutes(25)
            ],
            [
                'invoiceId'   => 'INV-AIYO-002',
                'referenceId' => 'FHK260915002',
                'water_type'  => 'NORMAL',
                'volume_ml'   => 500,
                'payAmount'   => 2500,
                'status'      => 'COMPLETED',
                'created_at'  => now()->subMinutes(18)
            ],
            [
                'invoiceId'   => 'INV-AIYO-003',
                'referenceId' => 'FHK260915003',
                'water_type'  => 'COLD',
                'volume_ml'   => 1000,
                'payAmount'   => 6000,
                'status'      => 'COMPLETED',
                'created_at'  => now()->subMinutes(6)
            ]
        ];

        foreach ($sampleOrders as $sample) {
            Transaksi::updateOrCreate(
                ['invoiceId' => $sample['invoiceId']],
                [
                    'referenceId'           => $sample['referenceId'],
                    'kiosk_id'              => $kiosk->id,
                    'userName'              => 'Pengunjung Stasiun',
                    'userEmail'             => 'user@example.com',
                    'userPhone'             => '08123456789',
                    'water_type'            => $sample['water_type'],
                    'volume_ml'             => $sample['volume_ml'],
                    'payAmount'             => $sample['payAmount'],
                    'status'                => $sample['status'],
                    'dispense_started_at'   => $sample['created_at'],
                    'dispense_completed_at' => $sample['created_at']->copy()->addSeconds(15),
                    'created_at'            => $sample['created_at']
                ]
            );
        }

        // 5. Telemetri Awal
        TelemetryLog::create([
            'kiosk_id'            => $kiosk->id,
            'water_level_cm'      => 12.5,
            'water_level_pct'     => 88.5,
            'temperature_celsius' => 7.5,
            'uv_lamp_active'      => false,
            'event_type'          => 'HEARTBEAT',
            'notes'               => 'Sistem startup inisialisasi normal',
            'recorded_at'         => now()
        ]);
    }
}
