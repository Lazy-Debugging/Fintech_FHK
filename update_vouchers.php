<?php
/**
 * update_vouchers.php - Zero Dependency Self-Contained Updater & Cache Cleaner
 * Akses via browser: https://app.mesinbayar.com/fhk/update_vouchers.php
 */

header('Content-Type: text/html; charset=utf-8');

$baseDir = __DIR__;
$results = [];

// 1. Pastikan folder tujuan ada
@mkdir($baseDir . '/resources/views/admin', 0777, true);
@mkdir($baseDir . '/app/Http/Controllers/Admin', 0777, true);
@mkdir($baseDir . '/storage/framework/views', 0777, true);

// 2. Jika user upload vouchers.blade.php atau VoucherController.php di folder root, pindahkan ke folder yang benar
if (file_exists($baseDir . '/vouchers.blade.php')) {
    @copy($baseDir . '/vouchers.blade.php', $baseDir . '/resources/views/admin/vouchers.blade.php');
}
if (file_exists($baseDir . '/VoucherController.php')) {
    @copy($baseDir . '/VoucherController.php', $baseDir . '/app/Http/Controllers/Admin/VoucherController.php');
}

// 3. Tulis kode vouchers.blade.php secara langsung (Embedded Payload)
$bladeTarget = $baseDir . '/resources/views/admin/vouchers.blade.php';
$embeddedBlade = '@extends(\'layouts.admin_layout\')

@section(\'title\', \'Manajemen Voucher - FHK Admin\')

@section(\'styles\')
<style>
    /* Styling khusus tiket voucher */
    .ticket-card {
        background: radial-gradient(circle at top left, transparent 14px, #111827 15px) top left,
                    radial-gradient(circle at top right, transparent 14px, #111827 15px) top right,
                    radial-gradient(circle at bottom left, transparent 14px, #111827 15px) bottom left,
                    radial-gradient(circle at bottom right, transparent 14px, #111827 15px) bottom right;
        background-size: 51% 51%;
        background-repeat: no-repeat;
    }
    .ticket-notch-left {
        left: -12px;
        top: 50%;
        transform: translateY(-50%);
    }
    .ticket-notch-right {
        right: -12px;
        top: 50%;
        transform: translateY(-50%);
    }
</style>
@endsection

@section(\'content\')
<div class="space-y-6 sm:space-y-8">

    <!-- Top Action Bar & Header -->
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-amber-500/20 text-amber-400 flex items-center justify-center text-xl font-bold border border-amber-500/30 shadow-md shadow-amber-500/10">
                    <i class="fa-solid fa-ticket"></i>
                </div>
                <div>
                    <h2 class="text-xl sm:text-2xl font-black text-white tracking-tight">Manajemen Voucher Diskon</h2>
                    <p class="text-xs text-slate-400 mt-0.5">Buat kupon promosi, atur kuota pemakaian, nilai diskon, dan pantau status voucher kios.</p>
                </div>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <a href="#voucher-list" class="px-4 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-bold transition flex items-center gap-2 border border-slate-700">
                <i class="fa-solid fa-list-check text-cyan-400"></i>
                <span>Lihat Daftar Voucher</span>
            </a>
            <a href="{{ route(\'admin.dashboard\') }}" class="px-4 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-bold transition flex items-center gap-2 border border-slate-700">
                <i class="fa-solid fa-arrow-left"></i>
                <span>Kembali</span>
            </a>
        </div>
    </div>

    <!-- Alert Notifikasi -->
    @if(session(\'status\'))
        <div id="status-alert" class="p-4 rounded-xl bg-emerald-500/15 border border-emerald-500/30 text-emerald-300 text-xs sm:text-sm font-semibold flex items-center justify-between shadow-lg shadow-emerald-950/20 transition">
            <div class="flex items-center gap-3">
                <div class="w-7 h-7 rounded-lg bg-emerald-500/20 flex items-center justify-center text-emerald-400">
                    <i class="fa-solid fa-circle-check text-base"></i>
                </div>
                <span>{{ session(\'status\') }}</span>
            </div>
            <button onclick="document.getElementById(\'status-alert\').remove()" class="opacity-70 hover:opacity-100 p-1 text-slate-400 hover:text-white">
                <i class="fa-solid fa-xmark text-sm"></i>
            </button>
        </div>
    @endif

    @if($errors->any())
        <div id="error-alert" class="p-4 rounded-xl bg-rose-500/15 border border-rose-500/30 text-rose-300 text-xs sm:text-sm font-semibold shadow-lg shadow-rose-950/20">
            <div class="flex items-start gap-3">
                <div class="w-7 h-7 rounded-lg bg-rose-500/20 flex items-center justify-center text-rose-400 shrink-0 mt-0.5">
                    <i class="fa-solid fa-triangle-exclamation text-base"></i>
                </div>
                <div class="flex-1">
                    <div class="font-bold mb-1">Gagal menyimpan voucher:</div>
                    <ul class="list-disc list-inside space-y-0.5 text-xs text-rose-200">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
                <button onclick="document.getElementById(\'error-alert\').remove()" class="opacity-70 hover:opacity-100 p-1 text-slate-400 hover:text-white">
                    <i class="fa-solid fa-xmark text-sm"></i>
                </button>
            </div>
        </div>
    @endif

    <!-- Metric Stats Cards -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
        <div class="admin-card rounded-2xl p-4 sm:p-5 space-y-2">
            <div class="flex items-center justify-between text-xs text-slate-400">
                <span>Total Voucher</span>
                <div class="w-8 h-8 rounded-lg bg-cyan-500/10 text-cyan-400 flex items-center justify-center">
                    <i class="fa-solid fa-ticket"></i>
                </div>
            </div>
            <div class="text-xl sm:text-2xl font-black text-white">
                {{ number_format($totalVouchers ?? 0) }}
            </div>
            <div class="text-[11px] text-cyan-400 font-medium">
                Kupon terdaftar
            </div>
        </div>

        <div class="admin-card rounded-2xl p-4 sm:p-5 space-y-2">
            <div class="flex items-center justify-between text-xs text-slate-400">
                <span>Voucher Aktif</span>
                <div class="w-8 h-8 rounded-lg bg-emerald-500/10 text-emerald-400 flex items-center justify-center">
                    <i class="fa-solid fa-circle-check"></i>
                </div>
            </div>
            <div class="text-xl sm:text-2xl font-black text-white">
                {{ number_format($activeVouchers ?? 0) }}
            </div>
            <div class="text-[11px] text-emerald-400 font-medium flex items-center gap-1">
                <i class="fa-solid fa-bolt"></i> Siap diklaim pembeli
            </div>
        </div>

        <div class="admin-card rounded-2xl p-4 sm:p-5 space-y-2">
            <div class="flex items-center justify-between text-xs text-slate-400">
                <span>Total Pemakaian</span>
                <div class="w-8 h-8 rounded-lg bg-amber-500/10 text-amber-400 flex items-center justify-center">
                    <i class="fa-solid fa-chart-line"></i>
                </div>
            </div>
            <div class="text-xl sm:text-2xl font-black text-white">
                {{ number_format($totalUsage ?? 0) }} <span class="text-xs text-slate-400 font-normal">kali</span>
            </div>
            <div class="text-[11px] text-amber-400 font-medium">
                Voucher telah ditebus
            </div>
        </div>

        <div class="admin-card rounded-2xl p-4 sm:p-5 space-y-2">
            <div class="flex items-center justify-between text-xs text-slate-400">
                <span>Variasi Diskon</span>
                <div class="w-8 h-8 rounded-lg bg-purple-500/10 text-purple-400 flex items-center justify-center">
                    <i class="fa-solid fa-tags"></i>
                </div>
            </div>
            <div class="text-xl sm:text-2xl font-black text-white">
                {{ $fixedCount ?? 0 }} <span class="text-xs text-slate-400 font-normal">Nominal</span> / {{ $percentCount ?? 0 }} <span class="text-xs text-slate-400 font-normal">%</span>
            </div>
            <div class="text-[11px] text-purple-400 font-medium">
                Kombinasi promo
            </div>
        </div>
    </div>

    <!-- Section 1: Form Pembuatan Voucher & Live Preview -->
    <div class="admin-card rounded-2xl p-5 sm:p-7 space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-800 pb-4">
            <div>
                <h3 class="text-base sm:text-lg font-bold text-white flex items-center gap-2">
                    <i class="fa-solid fa-plus-circle text-cyan-400"></i> Buat Voucher Diskon Baru
                </h3>
                <p class="text-xs text-slate-400 mt-0.5">Lengkapi formulir di bawah ini untuk menerbitkan kupon diskon baru.</p>
            </div>
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-cyan-500/10 border border-cyan-500/20 text-cyan-300 text-xs font-semibold">
                <i class="fa-solid fa-sparkles text-[10px]"></i> Live Preview Aktif
            </span>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
            
            <!-- FORM INPUTS (8 Kolom di Layar Lebar) -->
            <form method="POST" action="{{ route(\'admin.vouchers.store\') }}" class="lg:col-span-7 space-y-5" id="voucherForm">
                @csrf

                <!-- Baris 1: Kode & Nama Voucher -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label for="code" class="text-xs font-bold text-slate-300 flex items-center gap-1.5">
                                <i class="fa-solid fa-barcode text-amber-400"></i> Kode Voucher <span class="text-rose-400">*</span>
                            </label>
                            <button type="button" onclick="generateRandomCode()" class="text-[11px] text-cyan-400 hover:text-cyan-300 font-bold flex items-center gap-1 transition">
                                <i class="fa-solid fa-shuffle text-[10px]"></i> Acak Kode
                            </button>
                        </div>
                        <div class="relative">
                            <input 
                                type="text" 
                                id="code" 
                                name="code" 
                                value="{{ old(\'code\') }}" 
                                placeholder="Contoh: FHKHEMAT20" 
                                required 
                                maxlength="40"
                                oninput="this.value = this.value.toUpperCase(); updatePreview();"
                                class="w-full bg-slate-900 border border-slate-700 focus:border-cyan-400 focus:ring-1 focus:ring-cyan-400 rounded-xl px-3.5 py-2.5 text-xs sm:text-sm font-extrabold tracking-wider text-white uppercase placeholder-slate-500 transition"
                            >
                        </div>
                        <p class="text-[11px] text-slate-400 mt-1">Otomatis diubah ke huruf kapital tanpa spasi.</p>
                    </div>

                    <div>
                        <label for="name" class="block text-xs font-bold text-slate-300 mb-1.5 flex items-center gap-1.5">
                            <i class="fa-solid fa-tag text-cyan-400"></i> Nama / Judul Promo <span class="text-rose-400">*</span>
                        </label>
                        <input 
                            type="text" 
                            id="name" 
                            name="name" 
                            value="{{ old(\'name\') }}" 
                            placeholder="Contoh: Promo Pengguna Baru FHK" 
                            required 
                            maxlength="100"
                            oninput="updatePreview();"
                            class="w-full bg-slate-900 border border-slate-700 focus:border-cyan-400 focus:ring-1 focus:ring-cyan-400 rounded-xl px-3.5 py-2.5 text-xs sm:text-sm font-semibold text-white placeholder-slate-500 transition"
                        >
                        <p class="text-[11px] text-slate-400 mt-1">Deskripsi singkat yang tampil ke pelanggan.</p>
                    </div>
                </div>

                <!-- Baris 2: Tipe Diskon & Nilai Diskon -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="discount_type" class="block text-xs font-bold text-slate-300 mb-1.5 flex items-center gap-1.5">
                            <i class="fa-solid fa-sliders text-purple-400"></i> Jenis Potongan <span class="text-rose-400">*</span>
                        </label>
                        <div class="relative">
                            <select 
                                id="discount_type" 
                                name="discount_type" 
                                onchange="onDiscountTypeChange(); updatePreview();"
                                class="w-full bg-slate-900 border border-slate-700 focus:border-cyan-400 focus:ring-1 focus:ring-cyan-400 rounded-xl px-3.5 py-2.5 text-xs sm:text-sm font-bold text-white transition cursor-pointer appearance-none pr-10"
                            >
                                <option value="fixed" {{ old(\'discount_type\') == \'fixed\' ? \'selected\' : \'\' }}>Potongan Nominal (Rp)</option>
                                <option value="percent" {{ old(\'discount_type\') == \'percent\' ? \'selected\' : \'\' }}>Diskon Persentase (%)</option>
                            </select>
                            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-3.5 text-slate-400">
                                <i class="fa-solid fa-chevron-down text-xs"></i>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label for="discount_value" class="block text-xs font-bold text-slate-300 mb-1.5 flex items-center justify-between">
                            <span class="flex items-center gap-1.5">
                                <i class="fa-solid fa-percent text-emerald-400" id="discount_value_icon"></i>
                                <span id="discount_value_label">Besaran Diskon</span> <span class="text-rose-400">*</span>
                            </span>
                        </label>
                        <div class="relative">
                            <span id="discount_prefix" class="absolute left-3.5 top-2.5 text-xs font-bold text-slate-400">Rp</span>
                            <input 
                                type="number" 
                                id="discount_value" 
                                name="discount_value" 
                                value="{{ old(\'discount_value\', 2000) }}" 
                                min="1" 
                                required 
                                oninput="updatePreview();"
                                class="w-full bg-slate-900 border border-slate-700 focus:border-cyan-400 focus:ring-1 focus:ring-cyan-400 rounded-xl pl-11 pr-10 py-2.5 text-xs sm:text-sm font-extrabold text-white placeholder-slate-500 transition"
                            >
                            <span id="discount_suffix" class="hidden absolute right-3.5 top-2.5 text-xs font-bold text-slate-400">%</span>
                        </div>
                        
                        <!-- Quick presets -->
                        <div class="flex items-center gap-1.5 mt-2 flex-wrap" id="quick_presets">
                            <button type="button" onclick="setDiscount(1000)" class="px-2 py-0.5 rounded-md bg-slate-800 hover:bg-slate-700 border border-slate-700 text-[10px] text-slate-300 font-semibold transition">1k</button>
                            <button type="button" onclick="setDiscount(2000)" class="px-2 py-0.5 rounded-md bg-slate-800 hover:bg-slate-700 border border-slate-700 text-[10px] text-slate-300 font-semibold transition">2k</button>
                            <button type="button" onclick="setDiscount(3000)" class="px-2 py-0.5 rounded-md bg-slate-800 hover:bg-slate-700 border border-slate-700 text-[10px] text-slate-300 font-semibold transition">3k</button>
                            <button type="button" onclick="setDiscount(5000)" class="px-2 py-0.5 rounded-md bg-slate-800 hover:bg-slate-700 border border-slate-700 text-[10px] text-slate-300 font-semibold transition">5k</button>
                        </div>
                    </div>
                </div>

                <!-- Baris 3: Minimal Transaksi & Kuota Global -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="minimum_amount" class="block text-xs font-bold text-slate-300 mb-1.5 flex items-center gap-1.5">
                            <i class="fa-solid fa-cart-shopping text-cyan-400"></i> Minimal Belanja (Opsional)
                        </label>
                        <div class="relative">
                            <span class="absolute left-3.5 top-2.5 text-xs font-bold text-slate-400">Rp</span>
                            <input 
                                type="number" 
                                id="minimum_amount" 
                                name="minimum_amount" 
                                value="{{ old(\'minimum_amount\', 0) }}" 
                                min="0" 
                                step="500" 
                                placeholder="0"
                                oninput="updatePreview();"
                                class="w-full bg-slate-900 border border-slate-700 focus:border-cyan-400 focus:ring-1 focus:ring-cyan-400 rounded-xl pl-11 pr-3.5 py-2.5 text-xs sm:text-sm font-bold text-white placeholder-slate-500 transition"
                            >
                        </div>
                        <p class="text-[11px] text-slate-400 mt-1">Isi 0 jika tidak ada syarat minimum belanja.</p>
                    </div>

                    <div>
                        <label for="usage_limit" class="block text-xs font-bold text-slate-300 mb-1.5 flex items-center gap-1.5">
                            <i class="fa-solid fa-users text-amber-400"></i> Kuota Pemakaian Global
                        </label>
                        <div class="relative">
                            <input 
                                type="number" 
                                id="usage_limit" 
                                name="usage_limit" 
                                value="{{ old(\'usage_limit\') }}" 
                                min="1" 
                                placeholder="Tak Terbatas"
                                oninput="updatePreview();"
                                class="w-full bg-slate-900 border border-slate-700 focus:border-cyan-400 focus:ring-1 focus:ring-cyan-400 rounded-xl px-3.5 py-2.5 text-xs sm:text-sm font-bold text-white placeholder-slate-500 transition"
                            >
                            <span class="absolute right-3.5 top-2.5 text-xs text-slate-500">kali klaim</span>
                        </div>
                        <p class="text-[11px] text-slate-400 mt-1">Kosongkan jika kuota tidak terbatas (unlimited).</p>
                    </div>
                </div>

                <!-- Baris 4: Limit Per Pengguna & Periode Aktif -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label for="per_user_limit" class="block text-xs font-bold text-slate-300 mb-1.5 flex items-center gap-1.5">
                            <i class="fa-solid fa-user-lock text-sky-400"></i> Limit Per Akun <span class="text-rose-400">*</span>
                        </label>
                        <div class="relative">
                            <input 
                                type="number" 
                                id="per_user_limit" 
                                name="per_user_limit" 
                                value="{{ old(\'per_user_limit\', 1) }}" 
                                min="1" 
                                required
                                class="w-full bg-slate-900 border border-slate-700 focus:border-cyan-400 focus:ring-1 focus:ring-cyan-400 rounded-xl px-3.5 py-2.5 text-xs sm:text-sm font-bold text-white transition"
                            >
                            <span class="absolute right-3.5 top-2.5 text-xs text-slate-500">kali/user</span>
                        </div>
                    </div>

                    <div>
                        <label for="starts_at" class="block text-xs font-bold text-slate-300 mb-1.5 flex items-center gap-1.5">
                            <i class="fa-regular fa-calendar-check text-emerald-400"></i> Tanggal Mulai
                        </label>
                        <input 
                            type="datetime-local" 
                            id="starts_at" 
                            name="starts_at" 
                            value="{{ old(\'starts_at\') }}"
                            onchange="updatePreview();"
                            class="w-full bg-slate-900 border border-slate-700 focus:border-cyan-400 focus:ring-1 focus:ring-cyan-400 rounded-xl px-3 py-2 text-xs font-medium text-white transition"
                        >
                    </div>

                    <div>
                        <label for="expires_at" class="block text-xs font-bold text-slate-300 mb-1.5 flex items-center gap-1.5">
                            <i class="fa-regular fa-calendar-xmark text-rose-400"></i> Tanggal Berakhir
                        </label>
                        <input 
                            type="datetime-local" 
                            id="expires_at" 
                            name="expires_at" 
                            value="{{ old(\'expires_at\') }}"
                            onchange="updatePreview();"
                            class="w-full bg-slate-900 border border-slate-700 focus:border-cyan-400 focus:ring-1 focus:ring-cyan-400 rounded-xl px-3 py-2 text-xs font-medium text-white transition"
                        >
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="pt-2">
                    <button 
                        type="submit" 
                        class="w-full py-3.5 px-6 rounded-xl bg-gradient-to-r from-cyan-500 via-blue-600 to-cyan-500 hover:from-cyan-400 hover:to-blue-500 text-slate-950 font-black text-sm tracking-wide shadow-lg shadow-cyan-500/20 hover:shadow-cyan-500/30 transition transform active:scale-[0.99] flex items-center justify-center gap-2"
                    >
                        <i class="fa-solid fa-floppy-disk text-base"></i>
                        <span>Simpan & Terbitkan Voucher</span>
                    </button>
                </div>
            </form>

            <!-- LIVE PREVIEW KARTU VOUCHER (5 Kolom di Layar Lebar) -->
            <div class="lg:col-span-5 bg-slate-950/80 border border-slate-800 rounded-2xl p-5 space-y-4">
                <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                    <span class="text-xs font-extrabold uppercase tracking-wider text-slate-400 flex items-center gap-2">
                        <i class="fa-solid fa-eye text-cyan-400"></i> Preview Kartu Pelanggan
                    </span>
                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500/20 text-emerald-400 border border-emerald-500/30">
                        Status: AKTIF
                    </span>
                </div>

                <!-- Kartu Tiket Digital -->
                <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-slate-900 via-slate-900 to-slate-950 border border-slate-700/80 shadow-2xl p-5 text-white">
                    <!-- Ornamen Background Glow -->
                    <div class="absolute -top-12 -right-12 w-36 h-36 bg-cyan-500/10 rounded-full blur-2xl pointer-events-none"></div>
                    <div class="absolute -bottom-12 -left-12 w-36 h-36 bg-amber-500/10 rounded-full blur-2xl pointer-events-none"></div>

                    <!-- Header Tiket -->
                    <div class="flex items-center justify-between gap-3 mb-4">
                        <div class="flex items-center gap-2.5">
                            <div class="w-8 h-8 rounded-lg bg-gradient-to-tr from-cyan-500 to-blue-600 flex items-center justify-center text-white text-sm font-bold shadow-md shadow-cyan-500/30">
                                <i class="fa-solid fa-droplet"></i>
                            </div>
                            <div>
                                <div class="text-[10px] font-extrabold uppercase tracking-wider text-cyan-400">Fresh Hydration Kios</div>
                                <div class="text-xs font-bold text-slate-300">Voucher Diskon Spesial</div>
                            </div>
                        </div>
                        <span id="preview_type_badge" class="px-2 py-0.5 rounded-md text-[10px] font-black tracking-wide uppercase bg-amber-500/20 text-amber-300 border border-amber-500/40">
                            Potongan Rp
                        </span>
                    </div>

                    <!-- Diskon Amount Display -->
                    <div class="my-4 text-center py-3 bg-slate-950/60 rounded-xl border border-slate-800/80">
                        <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Hemat Hingga</div>
                        <div class="text-2xl sm:text-3xl font-black text-transparent bg-clip-text bg-gradient-to-r from-amber-300 via-amber-400 to-cyan-300 tracking-tight" id="preview_discount_text">
                            Rp 2.000 OFF
                        </div>
                        <div class="text-[11px] text-slate-400 mt-0.5" id="preview_min_order">
                            Tanpa minimum transaksi
                        </div>
                    </div>

                    <!-- Garis Putus-putus Tiket -->
                    <div class="relative my-4 flex items-center justify-center">
                        <div class="w-full border-t-2 border-dashed border-slate-700"></div>
                        <div class="absolute -left-7 w-4 h-4 rounded-full bg-slate-950 border border-slate-700"></div>
                        <div class="absolute -right-7 w-4 h-4 rounded-full bg-slate-950 border border-slate-700"></div>
                    </div>

                    <!-- Detail Promo & Kode Box -->
                    <div class="space-y-3">
                        <div>
                            <div class="text-[11px] text-slate-400">Nama Kupon Promo:</div>
                            <div class="text-xs sm:text-sm font-bold text-white truncate" id="preview_name">
                                Promo Pengguna Baru FHK
                            </div>
                        </div>

                        <!-- Box Kode Salin -->
                        <div class="flex items-center justify-between gap-2 p-2.5 rounded-xl bg-slate-950 border border-slate-800">
                            <div>
                                <div class="text-[9px] uppercase tracking-wider text-slate-500 font-bold">KODE VOUCHER</div>
                                <div class="text-sm font-black tracking-widest text-cyan-300 font-mono" id="preview_code">
                                    FHKHEMAT20
                                </div>
                            </div>
                            <button type="button" class="px-2.5 py-1.5 rounded-lg bg-cyan-500/20 hover:bg-cyan-500/30 text-cyan-300 text-xs font-bold border border-cyan-500/40 transition flex items-center gap-1.5">
                                <i class="fa-regular fa-copy"></i>
                                <span>SALIN</span>
                            </button>
                        </div>

                        <!-- Info Kuota & Masa Berlaku -->
                        <div class="flex items-center justify-between text-[11px] text-slate-400 pt-1">
                            <span id="preview_quota">
                                <i class="fa-solid fa-users text-slate-500 mr-1"></i> Kuota: Unlimited
                            </span>
                            <span id="preview_expiry">
                                <i class="fa-regular fa-clock text-slate-500 mr-1"></i> Berlaku Selamanya
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Tips Informasi -->
                <div class="p-3 rounded-xl bg-slate-900/60 border border-slate-800 text-[11px] text-slate-400 space-y-1">
                    <div class="font-bold text-slate-300 flex items-center gap-1.5">
                        <i class="fa-solid fa-circle-info text-cyan-400"></i> Cara Pelanggan Menggunakan:
                    </div>
                    <p>Pelanggan memasukkan kode voucher saat memilih air di layar Kios PWA atau melalui Aplikasi Mobile untuk mendapatkan potongan harga seketika.</p>
                </div>
            </div>

        </div>
    </div>

    <!-- Section 2: Daftar Semua Voucher -->
    <div class="admin-card rounded-2xl p-5 sm:p-6 space-y-5" id="voucher-list">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-800 pb-4">
            <div>
                <h3 class="text-base sm:text-lg font-bold text-white flex items-center gap-2">
                    <i class="fa-solid fa-table-list text-amber-400"></i> Daftar Voucher Aktif & Riwayat
                </h3>
                <p class="text-xs text-slate-400 mt-0.5">Kelola status aktif, pantau jumlah pemakaian, atau hapus kupon voucher.</p>
            </div>

            <!-- Instant Search Bar -->
            <div class="relative w-full sm:w-64">
                <i class="fa-solid fa-magnifying-glass absolute left-3 top-2.5 text-xs text-slate-400"></i>
                <input 
                    type="text" 
                    id="tableSearch" 
                    oninput="filterVouchersTable()" 
                    placeholder="Cari kode atau nama..." 
                    class="w-full bg-slate-900 border border-slate-700 rounded-xl pl-9 pr-3 py-1.5 text-xs text-white placeholder-slate-500 focus:border-cyan-400 focus:outline-none"
                >
            </div>
        </div>

        <!-- Table View (Desktop) & Cards (Mobile) -->
        <div class="overflow-x-auto -mx-5 sm:mx-0">
            <table class="w-full text-left text-xs text-slate-300" id="vouchersTable">
                <thead class="bg-slate-950/70 text-slate-400 uppercase text-[10px] tracking-wider border-b border-slate-800">
                    <tr>
                        <th class="py-3 px-4">Kode & Nama Voucher</th>
                        <th class="py-3 px-4">Nilai Diskon</th>
                        <th class="py-3 px-4">Penggunaan</th>
                        <th class="py-3 px-4">Masa Berlaku</th>
                        <th class="py-3 px-4 text-center">Status</th>
                        <th class="py-3 px-4 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/80">
                    @forelse($vouchers as $voucher)
                        @php
                            $isExpired = $voucher->expires_at && $voucher->expires_at->isPast();
                            $isExhausted = $voucher->usage_limit && $voucher->usage_count >= $voucher->usage_limit;
                            $pctUsed = $voucher->usage_limit ? min(100, round(($voucher->usage_count / $voucher->usage_limit) * 100)) : null;
                        @endphp
                        <tr class="hover:bg-slate-900/60 transition voucher-row">
                            <!-- Kode & Nama -->
                            <td class="py-3.5 px-4 font-medium">
                                <div class="flex items-center gap-2">
                                    <span class="px-2 py-1 rounded-md bg-slate-800 border border-slate-700 font-mono font-black text-cyan-300 text-xs tracking-wider voucher-code-val">
                                        {{ $voucher->code }}
                                    </span>
                                    <button 
                                        type="button" 
                                        onclick="copyCode(\'{{ $voucher->code }}\')" 
                                        title="Salin Kode" 
                                        class="text-slate-500 hover:text-cyan-300 transition"
                                    >
                                        <i class="fa-regular fa-copy text-xs"></i>
                                    </button>
                                </div>
                                <div class="text-xs font-bold text-white mt-1 voucher-name-val">
                                    {{ $voucher->name }}
                                </div>
                                <div class="text-[10px] text-slate-500 mt-0.5">
                                    Limit per user: {{ $voucher->per_user_limit }}x klaim
                                </div>
                            </td>

                            <!-- Nilai Diskon -->
                            <td class="py-3.5 px-4 font-medium">
                                @if($voucher->discount_type === \'percent\')
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-purple-500/20 text-purple-300 font-extrabold border border-purple-500/30">
                                        <i class="fa-solid fa-percent text-[10px]"></i> {{ $voucher->discount_value }}% OFF
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-emerald-500/20 text-emerald-300 font-extrabold border border-emerald-500/30">
                                        <i class="fa-solid fa-tag text-[10px]"></i> Rp {{ number_format($voucher->discount_value, 0, \',\', \'.\') }} OFF
                                    </span>
                                @endif
                                <div class="text-[10px] text-slate-400 mt-1">
                                    @if($voucher->minimum_amount > 0)
                                        Min. belanja: Rp {{ number_format($voucher->minimum_amount, 0, \',\', \'.\') }}
                                    @else
                                        Tanpa minimum belanja
                                    @endif
                                </div>
                            </td>

                            <!-- Penggunaan -->
                            <td class="py-3.5 px-4">
                                <div class="flex items-center gap-2">
                                    <span class="font-bold text-white">
                                        {{ $voucher->usage_count }}
                                    </span>
                                    <span class="text-slate-500">/</span>
                                    <span class="text-slate-400">
                                        {{ $voucher->usage_limit ?? \'∞\' }}
                                    </span>
                                </div>
                                @if($voucher->usage_limit)
                                    <div class="w-24 bg-slate-800 rounded-full h-1.5 mt-1.5 overflow-hidden">
                                        <div class="h-1.5 rounded-full {{ $pctUsed >= 90 ? \'bg-rose-500\' : ($pctUsed >= 50 ? \'bg-amber-400\' : \'bg-cyan-400\') }}" style="width: {{ $pctUsed }}%"></div>
                                    </div>
                                    <span class="text-[9px] text-slate-500">{{ $pctUsed }}% terpakai</span>
                                @else
                                    <span class="text-[10px] text-slate-500">Kuota tak terbatas</span>
                                @endif
                            </td>

                            <!-- Masa Berlaku -->
                            <td class="py-3.5 px-4 text-[11px]">
                                @if(!$voucher->starts_at && !$voucher->expires_at)
                                    <span class="text-emerald-400 font-semibold flex items-center gap-1">
                                        <i class="fa-solid fa-infinity text-xs"></i> Berlaku Selamanya
                                    </span>
                                @else
                                    <div class="space-y-0.5">
                                        @if($voucher->starts_at)
                                            <div>Mulai: <span class="text-slate-300">{{ $voucher->starts_at->format(\'d M Y H:i\') }}</span></div>
                                        @endif
                                        @if($voucher->expires_at)
                                            <div>Berakhir: <span class="{{ $isExpired ? \'text-rose-400 font-bold\' : \'text-slate-300\' }}">{{ $voucher->expires_at->format(\'d M Y H:i\') }}</span></div>
                                        @endif
                                    </div>
                                    @if($isExpired)
                                        <span class="inline-block mt-1 px-1.5 py-0.5 rounded text-[9px] font-bold bg-rose-500/20 text-rose-300 border border-rose-500/30">Kadaluarsa</span>
                                    @endif
                                @endif
                            </td>

                            <!-- Status -->
                            <td class="py-3.5 px-4 text-center">
                                @if(!$voucher->is_active)
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-slate-800 text-slate-400 border border-slate-700">
                                        <i class="fa-solid fa-circle text-[6px]"></i> Nonaktif
                                    </span>
                                @elseif($isExpired)
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-rose-500/20 text-rose-400 border border-rose-500/30">
                                        <i class="fa-solid fa-clock-rotate-left text-[8px]"></i> Berakhir
                                    </span>
                                @elseif($isExhausted)
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-500/20 text-amber-400 border border-amber-500/30">
                                        <i class="fa-solid fa-ban text-[8px]"></i> Habis
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500/20 text-emerald-400 border border-emerald-500/30">
                                        <i class="fa-solid fa-circle-check text-[8px]"></i> Aktif
                                    </span>
                                @endif
                            </td>

                            <!-- Aksi -->
                            <td class="py-3.5 px-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <!-- Toggle Aktif/Nonaktif -->
                                    <form method="POST" action="{{ route(\'admin.vouchers.update\', $voucher) }}" class="inline">
                                        @csrf
                                        @method(\'PATCH\')
                                        <input type="hidden" name="is_active" value="{{ $voucher->is_active ? 0 : 1 }}">
                                        @if($voucher->is_active)
                                            <button 
                                                type="submit" 
                                                class="px-2.5 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-amber-300 hover:text-amber-200 text-xs font-semibold border border-slate-700 transition flex items-center gap-1"
                                                title="Nonaktifkan Voucher"
                                            >
                                                <i class="fa-solid fa-pause text-[10px]"></i>
                                                <span class="hidden sm:inline">Nonaktifkan</span>
                                            </button>
                                        @else
                                            <button 
                                                type="submit" 
                                                class="px-2.5 py-1.5 rounded-lg bg-emerald-950/40 hover:bg-emerald-900/50 text-emerald-300 hover:text-emerald-200 text-xs font-semibold border border-emerald-800/60 transition flex items-center gap-1"
                                                title="Aktifkan Voucher"
                                            >
                                                <i class="fa-solid fa-play text-[10px]"></i>
                                                <span class="hidden sm:inline">Aktifkan</span>
                                            </button>
                                        @endif
                                    </form>

                                    <!-- Hapus Voucher -->
                                    <form method="POST" action="{{ route(\'admin.vouchers.destroy\', $voucher) }}" onsubmit="return confirm(\'Apakah Anda yakin ingin menghapus voucher {{ $voucher->code }}?\')" class="inline">
                                        @csrf
                                        @method(\'DELETE\')
                                        <button 
                                            type="submit" 
                                            class="p-1.5 rounded-lg bg-rose-950/30 hover:bg-rose-900/50 text-rose-400 hover:text-rose-300 border border-rose-800/40 transition" 
                                            title="Hapus Voucher"
                                        >
                                            <i class="fa-solid fa-trash-can text-xs"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-12 text-center text-slate-500">
                                <div class="w-16 h-16 mx-auto mb-3 rounded-2xl bg-slate-900 border border-slate-800 flex items-center justify-center text-slate-600 text-2xl">
                                    <i class="fa-solid fa-ticket-simple"></i>
                                </div>
                                <div class="text-sm font-bold text-slate-400">Belum ada voucher yang dibuat</div>
                                <p class="text-xs text-slate-500 mt-1 max-w-sm mx-auto">Gunakan formulir di atas untuk menerbitkan kupon diskon baru bagi pelanggan kios FHK.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        @if($vouchers->hasPages())
            <div class="pt-4 border-t border-slate-800 flex justify-center">
                {{ $vouchers->links() }}
            </div>
        @endif
    </div>

</div>

<!-- Toast Notifikasi Copy -->
<div id="copyToast" class="fixed bottom-6 right-6 z-50 transform translate-y-20 opacity-0 pointer-events-none transition duration-300 px-4 py-2.5 rounded-xl bg-slate-900 border border-cyan-500/40 text-cyan-300 font-bold text-xs flex items-center gap-2 shadow-xl shadow-cyan-950/50">
    <i class="fa-solid fa-circle-check text-sm text-cyan-400"></i>
    <span id="copyToastMsg">Kode berhasil disalin!</span>
</div>

@endsection

@section(\'scripts\')
<script>
    // Generator acak kode voucher
    function generateRandomCode() {
        const prefixes = [\'SEGAR\', \'FHK\', \'HEMAT\', \'DISKON\', \'AIR\', \'BERSIH\'];
        const randomPrefix = prefixes[Math.floor(Math.random() * prefixes.length)];
        const randomNum = Math.floor(10 + Math.random() * 90);
        const codeInput = document.getElementById(\'code\');
        codeInput.value = randomPrefix + randomNum;
        updatePreview();
    }

    // Set preset discount nominal
    function setDiscount(val) {
        document.getElementById(\'discount_type\').value = \'fixed\';
        document.getElementById(\'discount_value\').value = val;
        onDiscountTypeChange();
        updatePreview();
    }

    // Event saat jenis diskon berubah
    function onDiscountTypeChange() {
        const type = document.getElementById(\'discount_type\').value;
        const prefix = document.getElementById(\'discount_prefix\');
        const suffix = document.getElementById(\'discount_suffix\');
        const icon = document.getElementById(\'discount_value_icon\');
        const label = document.getElementById(\'discount_value_label\');
        const presets = document.getElementById(\'quick_presets\');
        const badge = document.getElementById(\'preview_type_badge\');

        if (type === \'percent\') {
            prefix.classList.add(\'hidden\');
            suffix.classList.remove(\'hidden\');
            icon.className = \'fa-solid fa-percent text-purple-400\';
            label.innerText = \'Persentase Diskon (%)\';
            badge.innerText = \'Diskon Persen\';
            badge.className = \'px-2 py-0.5 rounded-md text-[10px] font-black tracking-wide uppercase bg-purple-500/20 text-purple-300 border border-purple-500/40\';

            // Ganti presets ke persen
            presets.innerHTML = `
                <button type="button" onclick="setPercent(10)" class="px-2 py-0.5 rounded-md bg-slate-800 hover:bg-slate-700 border border-slate-700 text-[10px] text-slate-300 font-semibold transition">10%</button>
                <button type="button" onclick="setPercent(20)" class="px-2 py-0.5 rounded-md bg-slate-800 hover:bg-slate-700 border border-slate-700 text-[10px] text-slate-300 font-semibold transition">20%</button>
                <button type="button" onclick="setPercent(50)" class="px-2 py-0.5 rounded-md bg-slate-800 hover:bg-slate-700 border border-slate-700 text-[10px] text-slate-300 font-semibold transition">50%</button>
            `;
        } else {
            prefix.classList.remove(\'hidden\');
            suffix.classList.add(\'hidden\');
            icon.className = \'fa-solid fa-rupiah-sign text-emerald-400\';
            label.innerText = \'Besaran Potongan (Rp)\';
            badge.innerText = \'Potongan Rp\';
            badge.className = \'px-2 py-0.5 rounded-md text-[10px] font-black tracking-wide uppercase bg-amber-500/20 text-amber-300 border border-amber-500/40\';

            // Ganti presets ke nominal
            presets.innerHTML = `
                <button type="button" onclick="setDiscount(1000)" class="px-2 py-0.5 rounded-md bg-slate-800 hover:bg-slate-700 border border-slate-700 text-[10px] text-slate-300 font-semibold transition">1k</button>
                <button type="button" onclick="setDiscount(2000)" class="px-2 py-0.5 rounded-md bg-slate-800 hover:bg-slate-700 border border-slate-700 text-[10px] text-slate-300 font-semibold transition">2k</button>
                <button type="button" onclick="setDiscount(3000)" class="px-2 py-0.5 rounded-md bg-slate-800 hover:bg-slate-700 border border-slate-700 text-[10px] text-slate-300 font-semibold transition">3k</button>
                <button type="button" onclick="setDiscount(5000)" class="px-2 py-0.5 rounded-md bg-slate-800 hover:bg-slate-700 border border-slate-700 text-[10px] text-slate-300 font-semibold transition">5k</button>
            `;
        }
    }

    function setPercent(val) {
        document.getElementById(\'discount_value\').value = val;
        updatePreview();
    }

    // Update real-time Live Preview voucher card
    function updatePreview() {
        const code = document.getElementById(\'code\').value.trim() || \'FHKHEMAT20\';
        const name = document.getElementById(\'name\').value.trim() || \'Promo Pengguna Baru FHK\';
        const type = document.getElementById(\'discount_type\').value;
        const val = parseInt(document.getElementById(\'discount_value\').value) || 0;
        const minOrder = parseInt(document.getElementById(\'minimum_amount\').value) || 0;
        const quota = document.getElementById(\'usage_limit\').value;
        const expiry = document.getElementById(\'expires_at\').value;

        document.getElementById(\'preview_code\').innerText = code;
        document.getElementById(\'preview_name\').innerText = name;

        // Diskon text
        const discountText = document.getElementById(\'preview_discount_text\');
        if (type === \'percent\') {
            discountText.innerText = val + \'% OFF\';
        } else {
            discountText.innerText = \'Rp \' + val.toLocaleString(\'id-ID\') + \' OFF\';
        }

        // Min Order
        const minOrderText = document.getElementById(\'preview_min_order\');
        if (minOrder > 0) {
            minOrderText.innerText = \'Min. belanja Rp \' + minOrder.toLocaleString(\'id-ID\');
        } else {
            minOrderText.innerText = \'Tanpa minimum transaksi\';
        }

        // Quota
        const quotaText = document.getElementById(\'preview_quota\');
        if (quota && parseInt(quota) > 0) {
            quotaText.innerHTML = `<i class="fa-solid fa-users text-slate-500 mr-1"></i> Kuota: ${quota} klaim`;
        } else {
            quotaText.innerHTML = `<i class="fa-solid fa-users text-slate-500 mr-1"></i> Kuota: Unlimited`;
        }

        // Expiry
        const expiryText = document.getElementById(\'preview_expiry\');
        if (expiry) {
            const dateObj = new Date(expiry);
            const options = { day: \'numeric\', month: \'short\', year: \'numeric\' };
            expiryText.innerHTML = `<i class="fa-regular fa-clock text-slate-500 mr-1"></i> S/d ${dateObj.toLocaleDateString(\'id-ID\', options)}`;
        } else {
            expiryText.innerHTML = `<i class="fa-regular fa-clock text-slate-500 mr-1"></i> Berlaku Selamanya`;
        }
    }

    // Salin kode ke clipboard
    function copyCode(code) {
        navigator.clipboard.writeText(code).then(() => {
            const toast = document.getElementById(\'copyToast\');
            const msg = document.getElementById(\'copyToastMsg\');
            msg.innerText = `Kode ${code} berhasil disalin!`;
            toast.classList.remove(\'opacity-0\', \'translate-y-20\', \'pointer-events-none\');
            setTimeout(() => {
                toast.classList.add(\'opacity-0\', \'translate-y-20\', \'pointer-events-none\');
            }, 2500);
        });
    }

    // Filter pencarian tabel voucher
    function filterVouchersTable() {
        const query = document.getElementById(\'tableSearch\').value.toLowerCase();
        const rows = document.querySelectorAll(\'.voucher-row\');

        rows.forEach(row => {
            const code = row.querySelector(\'.voucher-code-val\')?.innerText.toLowerCase() || \'\';
            const name = row.querySelector(\'.voucher-name-val\')?.innerText.toLowerCase() || \'\';

            if (code.includes(query) || name.includes(query)) {
                row.style.display = \'\';
            } else {
                row.style.display = \'none\';
            }
        });
    }

    // Inisialisasi saat halaman dimuat
    document.addEventListener(\'DOMContentLoaded\', () => {
        onDiscountTypeChange();
        updatePreview();
    });
</script>
@endsection';
$embeddedCtrl  = '<?php

namespace App\\Http\\Controllers\\Admin;

use App\\Http\\Controllers\\Controller;
use App\\Models\\Voucher;
use Illuminate\\Http\\Request;
use Illuminate\\Support\\Str;

class VoucherController extends Controller
{
    public function index()
    {
        $vouchers       = Voucher::latest()->paginate(15);
        $totalVouchers  = Voucher::count();
        $activeVouchers = Voucher::where(\'is_active\', true)->count();
        $totalUsage     = Voucher::sum(\'usage_count\');
        $fixedCount     = Voucher::where(\'discount_type\', \'fixed\')->count();
        $percentCount   = Voucher::where(\'discount_type\', \'percent\')->count();

        return view(\'admin.vouchers\', compact(
            \'vouchers\',
            \'totalVouchers\',
            \'activeVouchers\',
            \'totalUsage\',
            \'fixedCount\',
            \'percentCount\'
        ));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            \'code\'           => \'required|string|max:40|unique:vouchers,code\',
            \'name\'           => \'required|string|max:100\',
            \'discount_type\'  => \'required|in:fixed,percent\',
            \'discount_value\' => \'required|integer|min:1\',
            \'minimum_amount\' => \'nullable|integer|min:0\',
            \'usage_limit\'    => \'nullable|integer|min:1\',
            \'per_user_limit\' => \'required|integer|min:1\',
            \'starts_at\'      => \'nullable|date\',
            \'expires_at\'     => \'nullable|date|after:starts_at\',
        ]);

        $data[\'code\'] = Str::upper(trim($data[\'code\']));
        $data[\'is_active\'] = $request->boolean(\'is_active\', true);

        // Pastikan input tanggal dan batas kosong disimpan sebagai NULL
        if (empty($data[\'starts_at\'])) {
            $data[\'starts_at\'] = null;
        }
        if (empty($data[\'expires_at\'])) {
            $data[\'expires_at\'] = null;
        }
        if (!isset($data[\'minimum_amount\']) || $data[\'minimum_amount\'] === \'\') {
            $data[\'minimum_amount\'] = 0;
        }
        if (empty($data[\'usage_limit\'])) {
            $data[\'usage_limit\'] = null;
        }

        Voucher::create($data);

        return back()->with(\'status\', \'Voucher baru berhasil dibuat dan siap digunakan!\');
    }

    public function update(Request $request, Voucher $voucher)
    {
        $data = $request->validate([
            \'is_active\' => \'required|boolean\'
        ]);

        $voucher->update($data);

        $statusText = $voucher->is_active ? \'diaktifkan\' : \'dinonaktifkan\';
        return back()->with(\'status\', "Voucher {$voucher->code} berhasil {$statusText}.");
    }

    public function destroy(Voucher $voucher)
    {
        $code = $voucher->code;
        $voucher->delete();

        return back()->with(\'status\', "Voucher {$code} berhasil dihapus.");
    }
}';
$wBlade = @file_put_contents($bladeTarget, $embeddedBlade);
if ($wBlade !== false) {
    $results[] = [
        'file' => 'resources/views/admin/vouchers.blade.php',
        'status' => 'SUCCESS',
        'msg' => 'Tampilan Blade terbaru berhasil ditulis (' . number_format($wBlade) . ' bytes)'
    ];
} else {
    $results[] = [
        'file' => 'resources/views/admin/vouchers.blade.php',
        'status' => 'FAILED',
        'msg' => 'Gagal menulis file blade. Cek permission folder resources/views/admin'
    ];
}

$ctrlTarget = $baseDir . '/app/Http/Controllers/Admin/VoucherController.php';
$wCtrl = @file_put_contents($ctrlTarget, $embeddedCtrl);
if ($wCtrl !== false) {
    $results[] = [
        'file' => 'app/Http/Controllers/Admin/VoucherController.php',
        'status' => 'SUCCESS',
        'msg' => 'Controller terbaru berhasil ditulis (' . number_format($wCtrl) . ' bytes)'
    ];
} else {
    $results[] = [
        'file' => 'app/Http/Controllers/Admin/VoucherController.php',
        'status' => 'FAILED',
        'msg' => 'Gagal menulis file controller. Cek permission folder app/Http/Controllers/Admin'
    ];
}

// 4. Bersihkan Cache View Laravel (storage/framework/views)
$cacheCleared = 0;
$viewDirs = [
    $baseDir . '/storage/framework/views',
    $baseDir . '/storage/framework/cache',
];

foreach ($viewDirs as $vd) {
    if (is_dir($vd)) {
        $cFiles = glob($vd . '/*');
        if ($cFiles) {
            foreach ($cFiles as $cf) {
                if (is_file($cf) && basename($cf) !== '.gitignore') {
                    if (@unlink($cf)) {
                        $cacheCleared++;
                    }
                }
            }
        }
    }
}

// 5. Bersihkan cache bootstrap jika ada
$bootstrapCacheDir = $baseDir . '/bootstrap/cache';
if (is_dir($bootstrapCacheDir)) {
    $bFiles = ['routes-v7.php', 'config.php', 'services.php', 'packages.php'];
    foreach ($bFiles as $bf) {
        $bPath = $bootstrapCacheDir . '/' . $bf;
        if (file_exists($bPath)) {
            @unlink($bPath);
        }
    }
}

// 6. Jalankan artisan dengan proteksi function_exists (Aman dari disable_functions)
if (function_exists('exec') && file_exists($baseDir . '/artisan')) {
    try {
        @exec('php artisan view:clear 2>&1');
        @exec('php artisan route:clear 2>&1');
    } catch (\Throwable $e) {}
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sinkronisasi Tampilan Voucher Sukses</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #0b1120; color: #f1f5f9; }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4">
    <div class="max-w-xl w-full bg-slate-900 border border-slate-800 rounded-2xl p-6 sm:p-8 shadow-2xl space-y-6">
        
        <div class="flex items-center gap-4 border-b border-slate-800 pb-5">
            <div class="w-12 h-12 rounded-xl bg-emerald-500/20 text-emerald-400 flex items-center justify-center text-2xl border border-emerald-500/30">
                <i class="fa-solid fa-circle-check"></i>
            </div>
            <div>
                <h1 class="text-xl font-black text-white">Sinkronisasi Berhasil!</h1>
                <p class="text-xs text-slate-400">File Voucher & Cache View Laravel Telah Diperbarui</p>
            </div>
        </div>

        <div class="space-y-3">
            <?php foreach ($results as $res): ?>
                <div class="flex items-center justify-between p-3.5 rounded-xl bg-slate-950/70 border border-slate-800 text-xs">
                    <div class="space-y-1">
                        <div class="font-mono font-bold text-slate-200"><?php echo htmlspecialchars($res['file']); ?></div>
                        <div class="text-[11px] text-slate-400"><?php echo htmlspecialchars($res['msg']); ?></div>
                    </div>
                    <?php if ($res['status'] === 'SUCCESS'): ?>
                        <span class="px-2.5 py-1 rounded-md text-[10px] font-black bg-emerald-500/20 text-emerald-400 border border-emerald-500/30">
                            SUKSES
                        </span>
                    <?php else: ?>
                        <span class="px-2.5 py-1 rounded-md text-[10px] font-black bg-rose-500/20 text-rose-400 border border-rose-500/30">
                            GAGAL
                        </span>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>

            <div class="flex items-center justify-between p-3.5 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-xs text-emerald-300">
                <div class="flex items-center gap-2">
                    <i class="fa-solid fa-broom text-emerald-400"></i>
                    <span>Cache View Lama Dibersihkan:</span>
                </div>
                <span class="font-black"><?php echo $cacheCleared; ?> file cache dihapus</span>
            </div>
        </div>

        <div class="p-4 rounded-xl bg-cyan-500/10 border border-cyan-500/20 text-xs text-cyan-200 space-y-1">
            <div class="font-bold flex items-center gap-1.5 text-cyan-300">
                <i class="fa-solid fa-circle-info"></i> Langkah Terakhir:
            </div>
            <p>Silakan klik tombol di bawah untuk membuka halaman Admin Voucher. Jika di browser Anda masih tampak lama, tekan <b>Ctrl + F5</b> (Hard Refresh) untuk membersihkan cache browser.</p>
        </div>

        <div class="pt-2 flex flex-col sm:flex-row gap-3">
            <a href="admin/vouchers" class="flex-1 py-3.5 px-4 rounded-xl bg-gradient-to-r from-cyan-500 to-blue-600 hover:from-cyan-400 hover:to-blue-500 text-slate-950 font-black text-xs sm:text-sm text-center transition shadow-lg shadow-cyan-500/20 flex items-center justify-center gap-2">
                <span>Buka Halaman Admin Voucher Sekarang</span>
                <i class="fa-solid fa-arrow-right"></i>
            </a>
        </div>

    </div>
</body>
</html>