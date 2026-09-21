<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kiosk_pricing', function (Blueprint $table) {
            $table->id();
            $table->string('kiosk_id', 30)->nullable()->index(); // null = berlaku untuk semua kios (global)
            $table->enum('water_type', ['COLD', 'NORMAL']);
            $table->unsignedInteger('volume_ml');              // 250, 500, 1000
            $table->unsignedInteger('price');                  // harga dalam rupiah
            $table->boolean('is_active')->default(true);
            $table->string('updated_by', 100)->nullable();     // nama admin yang update
            $table->timestamps();

            $table->unique(['kiosk_id', 'water_type', 'volume_ml']); // satu harga per kombinasi
        });

        // Isi harga default FHK
        $defaults = [
            ['kiosk_id' => null, 'water_type' => 'COLD',   'volume_ml' => 250,  'price' => 2000],
            ['kiosk_id' => null, 'water_type' => 'COLD',   'volume_ml' => 500,  'price' => 3500],
            ['kiosk_id' => null, 'water_type' => 'COLD',   'volume_ml' => 1000, 'price' => 6000],
            ['kiosk_id' => null, 'water_type' => 'NORMAL', 'volume_ml' => 250,  'price' => 1500],
            ['kiosk_id' => null, 'water_type' => 'NORMAL', 'volume_ml' => 500,  'price' => 2500],
            ['kiosk_id' => null, 'water_type' => 'NORMAL', 'volume_ml' => 1000, 'price' => 4500],
        ];

        foreach ($defaults as $row) {
            DB::table('kiosk_pricing')->insert(array_merge($row, [
                'is_active'  => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('kiosk_pricing');
    }
};
