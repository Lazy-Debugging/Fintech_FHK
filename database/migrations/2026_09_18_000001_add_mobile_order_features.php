<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 50)->nullable()->unique()->after('name');
            $table->string('role', 20)->default('user')->after('password');
            $table->string('google_id')->nullable()->unique()->after('email');
        });

        Schema::create('vouchers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name', 100);
            $table->string('discount_type', 12);
            $table->unsignedBigInteger('discount_value');
            $table->unsignedBigInteger('minimum_amount')->default(0);
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('usage_count')->default(0);
            $table->unsignedInteger('per_user_limit')->default(1);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('transaksi', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('kiosk_id')->constrained()->nullOnDelete();
            $table->foreignId('voucher_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            $table->unsignedBigInteger('original_amount')->nullable()->after('payAmount');
            $table->unsignedBigInteger('discount_amount')->default(0)->after('original_amount');
            $table->string('redemption_token_hash', 64)->nullable()->unique()->after('status');
            $table->timestamp('redemption_expires_at')->nullable()->after('redemption_token_hash');
            $table->timestamp('redeemed_at')->nullable()->after('redemption_expires_at');
        });

        Schema::create('voucher_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('voucher_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('invoice_id', 64)->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voucher_redemptions');
        Schema::table('transaksi', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
            $table->dropConstrainedForeignId('voucher_id');
            $table->dropColumn(['original_amount', 'discount_amount', 'redemption_token_hash', 'redemption_expires_at', 'redeemed_at']);
        });
        Schema::dropIfExists('vouchers');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['username', 'role', 'google_id']);
        });
    }
};