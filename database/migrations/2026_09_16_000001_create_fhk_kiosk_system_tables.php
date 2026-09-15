<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Tabel Kiosks
        Schema::create('kiosks', function (Blueprint $table) {
            $table->string('id', 30)->primary(); // e.g. FHK-JAKARTA-01
            $table->string('name', 100);
            $table->string('location', 255);
            $table->string('ip_address', 45)->nullable();
            $table->string('api_secret_token', 100);
            $table->enum('status', ['ONLINE', 'OFFLINE', 'DISPENSING', 'MAINTENANCE', 'OUT_OF_WATER'])->default('ONLINE');
            $table->decimal('tank_capacity_liters', 8, 2)->default(50.0);
            $table->decimal('current_water_level_pct', 5, 2)->default(100.0);
            $table->decimal('current_temp_celsius', 4, 1)->default(9.0);
            $table->timestamp('last_heartbeat')->nullable();
            $table->timestamps();
        });

        // 2. Tabel Transaksi (kompatibel dengan schema transaksi.sql & fitur FHK)
        Schema::create('transaksi', function (Blueprint $table) {
            $table->string('invoiceId', 64)->primary();
            $table->string('referenceId', 50)->unique();
            $table->string('kiosk_id', 30)->nullable();
            $table->string('userName', 100)->default('Guest Customer');
            $table->string('userEmail', 100)->default('customer@fhk.id');
            $table->string('userPhone', 30)->default('0812000000');
            $table->string('water_type', 20)->default('COLD'); // COLD, NORMAL
            $table->integer('volume_ml')->default(500); // 250, 500, 1000, etc.
            $table->unsignedBigInteger('payAmount')->default(0);
            $table->text('aiyo_access_token')->nullable();
            $table->string('status', 25)->default('NEW'); // NEW, PENDING, PAID, DISPENSING, COMPLETED, EXPIRED, FAILED
            $table->text('remarks')->nullable();
            $table->text('items')->nullable();
            $table->text('qr_content')->nullable();
            $table->text('invoice_url')->nullable();
            $table->timestamp('dispense_started_at')->nullable();
            $table->timestamp('dispense_completed_at')->nullable();
            $table->timestamp('timestamp')->useCurrent();
            $table->timestamps();

            $table->foreign('kiosk_id')->references('id')->on('kiosks')->onDelete('set null');
        });

        // 3. Tabel Maintenance & Lifespan Filter
        Schema::create('filter_maintenance', function (Blueprint $table) {
            $table->id();
            $table->string('kiosk_id', 30);
            $table->enum('filter_type', ['SEDIMENT', 'CARBON_BLOCK', 'ULTRAFILTRATION', 'UV_LAMP']);
            $table->string('filter_name', 100);
            $table->decimal('capacity_liters_limit', 10, 2)->default(5000.0);
            $table->decimal('used_liters', 10, 2)->default(0.0);
            $table->integer('operating_hours_limit')->nullable()->default(8000); // untuk lampu UV
            $table->integer('operating_hours_used')->nullable()->default(0);
            $table->date('last_replaced_at')->nullable();
            $table->enum('status', ['GOOD', 'WARNING', 'EXPIRED'])->default('GOOD');
            $table->timestamps();

            $table->foreign('kiosk_id')->references('id')->on('kiosks')->onDelete('cascade');
        });

        // 4. Tabel Telemetri Sensor ESP32 (Ultrasonic level, temperature, status)
        Schema::create('telemetry_logs', function (Blueprint $table) {
            $table->id();
            $table->string('kiosk_id', 30);
            $table->decimal('water_level_cm', 6, 2);
            $table->decimal('water_level_pct', 5, 2);
            $table->decimal('temperature_celsius', 4, 1)->nullable();
            $table->boolean('uv_lamp_active')->default(false);
            $table->string('event_type', 30)->default('HEARTBEAT'); // HEARTBEAT, DISPENSE, ALERT
            $table->string('notes', 255)->nullable();
            $table->timestamp('recorded_at')->useCurrent();
            $table->timestamps();

            $table->foreign('kiosk_id')->references('id')->on('kiosks')->onDelete('cascade');
        });

        // 5. Tabel Jadwal Sterilisasi UV Otomatis
        Schema::create('uv_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('kiosk_id', 30);
            $table->string('cycle_time', 10)->default('02:00'); // format HH:mm
            $table->integer('duration_seconds')->default(180); // 3 menit
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_executed_at')->nullable();
            $table->timestamps();

            $table->foreign('kiosk_id')->references('id')->on('kiosks')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('uv_schedules');
        Schema::dropIfExists('telemetry_logs');
        Schema::dropIfExists('filter_maintenance');
        Schema::dropIfExists('transaksi');
        Schema::dropIfExists('kiosks');
    }
};
