<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transaksi', function (Blueprint $table) {
            $table->string('guest_token', 64)->nullable()->after('voucher_id');
            $table->text('redemption_token_encrypted')->nullable()->after('redemption_token_hash');
        });
    }

    public function down(): void
    {
        Schema::table('transaksi', function (Blueprint $table) {
            $table->dropColumn(['guest_token', 'redemption_token_encrypted']);
        });
    }
};