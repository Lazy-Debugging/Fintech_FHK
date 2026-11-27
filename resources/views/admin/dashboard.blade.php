@extends('layouts.admin_layout')

@section('title', 'Monitoring & Maintenance - Fresh Hydration Kios')

@section('content')
<div class="space-y-5 sm:space-y-8">

    <!-- Top Action Bar & Kiosk Status Header -->
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
            <div class="flex flex-wrap items-center gap-2 sm:gap-3">
                <h2 class="text-xl sm:text-2xl font-black text-white tracking-tight">{{ $selectedKiosk->name ?? 'FHK Kios' }}</h2>
                <span class="px-3 py-1 rounded-full text-xs font-bold {{ $selectedKiosk->status === 'ONLINE' ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30' : ($selectedKiosk->status === 'DISPENSING' ? 'bg-purple-500/20 text-purple-300 border border-purple-500/30' : 'bg-amber-500/20 text-amber-300 border border-amber-500/30') }}">
                    <i class="fa-solid fa-circle text-[8px] mr-1"></i> {{ $selectedKiosk->status }}
                </span>
            </div>
            <p class="text-xs text-slate-400 mt-1">
                <i class="fa-solid fa-location-dot text-cyan-400 mr-1"></i> {{ $selectedKiosk->location ?? '-' }} • ID: <code class="text-slate-300">{{ $selectedKiosk->id }}</code>
            </p>
        </div>

        <div class="grid w-full sm:w-auto grid-cols-1 sm:flex items-stretch gap-2 sm:gap-3">
            <button onclick="openRefillModal()" class="px-4 py-2.5 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white text-xs font-bold transition flex items-center justify-center gap-2 shadow-lg shadow-emerald-950/40">
                <i class="fa-solid fa-faucet-drip"></i>
                <span>Isi Ulang Air (Refill)</span>
            </button>
            <button onclick="openWifiModal()" class="px-4 py-2.5 rounded-xl bg-sky-900/50 hover:bg-sky-800/60 text-sky-200 border border-sky-700/60 text-xs font-bold transition flex items-center justify-center gap-2 shadow-lg shadow-sky-950/40">
                <i class="fa-solid fa-wifi text-sky-400"></i>
                <span>Ganti Wi-Fi Kios</span>
            </button>
            <button onclick="triggerEmergencyUv()" class="px-4 py-2.5 rounded-xl bg-purple-900/50 hover:bg-purple-800/60 text-purple-200 border border-purple-700/60 text-xs font-bold transition flex items-center justify-center gap-2 shadow-lg shadow-purple-950/40">
                <i class="fa-solid fa-shield-virus text-purple-400"></i>
                <span>Jalankan Sterilisasi UV Sekarang</span>
            </button>
            <a href="{{ route('admin.simulator') }}" class="px-4 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-bold transition flex items-center justify-center gap-2 border border-slate-700">
                <i class="fa-solid fa-microchip text-cyan-400"></i>
                <span>Simulator ESP32</span>
            </a>
            <a href="{{ route('admin.wiring') }}" class="px-4 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-bold transition flex items-center justify-center gap-2 border border-slate-700">
                <i class="fa-solid fa-network-wired text-emerald-400"></i>
                <span>Skema Wiring</span>
            </a>
        </div>
    </div>

    <!-- KPI Metric Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
        
        <div class="admin-card rounded-2xl p-4 sm:p-5 space-y-2">
            <div class="flex items-center justify-between text-xs text-slate-400">
                <span>Total Pendapatan (AiYO)</span>
                <div class="w-8 h-8 rounded-lg bg-emerald-500/10 text-emerald-400 flex items-center justify-center">
                    <i class="fa-solid fa-rupiah-sign"></i>
                </div>
            </div>
            <div class="text-xl sm:text-2xl font-black text-white break-words">
                Rp {{ number_format($totalRevenue, 0, ',', '.') }}
            </div>
            <div class="text-[11px] text-emerald-400 font-medium flex items-center gap-1">
                <i class="fa-solid fa-check"></i> {{ $completedTransactions }} transaksi lunas
            </div>
        </div>

        <div class="admin-card rounded-2xl p-4 sm:p-5 space-y-2">
            <div class="flex items-center justify-between text-xs text-slate-400">
                <span>Output Air Terjual</span>
                <div class="w-8 h-8 rounded-lg bg-cyan-500/10 text-cyan-400 flex items-center justify-center">
                    <i class="fa-solid fa-bottle-water"></i>
                </div>
            </div>
            <div class="text-xl sm:text-2xl font-black text-white break-words">
                {{ number_format($totalWaterSoldMl ?? ($totalLitersDispensed * 1000), 0, ',', '.') }} <span class="text-base text-slate-400 font-normal">mL</span>
            </div>
            <div class="text-[11px] text-cyan-400 font-medium">
                ~ {{ number_format($totalLitersDispensed, 1, ',', '.') }} Liter dari penjualan
            </div>
        </div>

        <div class="admin-card rounded-2xl p-4 sm:p-5 space-y-2">
            <div class="flex items-center justify-between text-xs text-slate-400">
                <span>Sisa Stok Air Tangki</span>
                <div class="w-8 h-8 rounded-lg bg-teal-500/10 text-teal-400 flex items-center justify-center">
                    <i class="fa-solid fa-flask-vial"></i>
                </div>
            </div>
            <div class="text-xl sm:text-2xl font-black text-white">
                {{ number_format($remainingWaterMl ?? 0, 0, ',', '.') }} <span class="text-base text-slate-400 font-normal">mL</span>
            </div>
            <div class="text-[11px] font-semibold {{ ($selectedKiosk->current_water_level_pct <= 5.0 || $selectedKiosk->status === 'OUT_OF_WATER') ? 'text-rose-400' : (($selectedKiosk->current_water_level_pct <= 20.0) ? 'text-amber-400' : 'text-emerald-400') }}">
                {{ $selectedKiosk->current_water_level_pct }}% ({{ ($selectedKiosk->current_water_level_pct <= 5.0 || $selectedKiosk->status === 'OUT_OF_WATER') ? 'Stok Habis / Out of Water' : (($selectedKiosk->current_water_level_pct <= 20.0) ? 'Stok Menipis' : 'Stok Optimal') }})
            </div>
        </div>

        <div class="admin-card rounded-2xl p-4 sm:p-5 space-y-2">
            <div class="flex items-center justify-between text-xs text-slate-400">
                <span>Lampu UV-C ESP32</span>
                <div class="w-8 h-8 rounded-lg bg-purple-500/10 text-purple-400 flex items-center justify-center">
                    <i class="fa-solid fa-radiation"></i>
                </div>
            </div>
            <div class="text-xl sm:text-2xl font-black text-white">
                Aktif (99.9%)
            </div>
            <div class="text-[11px] text-purple-400 font-medium">
                Sterilisasi Pre & Post Dispense
            </div>
        </div>

    </div>

    <!-- Middle Section: Visual Tank Level & Filter Maintenance Lifespan -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        <!-- 1. Tangki Air (Ultrasonic Fluid Level Sensor) -->
        <div class="admin-card rounded-2xl p-4 sm:p-6 space-y-6 flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between">
                    <h3 class="font-extrabold text-white text-base flex items-center gap-2">
                        <i class="fa-solid fa-flask text-cyan-400"></i>
                        <span>Level Tangki Air Utama</span>
                    </h3>
                    <span class="text-[11px] px-2 py-0.5 rounded bg-cyan-950 text-cyan-300 border border-cyan-800">
                        Ultrasonic Sensor
                    </span>
                </div>
                <p class="text-xs text-slate-400 mt-1">Pembacaan sensor jarak HC-SR04 / JSN-SR04T</p>
            </div>

            <!-- Visual Cylindrical Tank Graphic -->
            <div class="flex items-center justify-center py-4">
                <div class="w-32 h-52 border-4 border-slate-700 rounded-3xl relative overflow-hidden bg-slate-900/90 shadow-2xl flex flex-col justify-end p-1">
                    
                    <!-- Water Level Wave -->
                    <div id="tank-visual-water" class="w-full bg-gradient-to-t from-cyan-600 via-sky-500 to-cyan-300 rounded-2xl transition-all duration-700 relative"
                         style="height: {{ $selectedKiosk->current_water_level_pct }}%;">
                        <div class="w-full h-2 bg-white/40 animate-pulse"></div>
                    </div>

                    <!-- Percentage Label Overlay -->
                    <div class="absolute inset-0 flex flex-col items-center justify-center text-center drop-shadow-md">
                        <span class="text-3xl font-black text-white font-mono" id="tank-pct-display">{{ $selectedKiosk->current_water_level_pct }}%</span>
                        <span class="text-[11px] font-bold text-slate-300">
                            ~ {{ number_format(($selectedKiosk->tank_capacity_liters * $selectedKiosk->current_water_level_pct) / 100, 1) }} / {{ $selectedKiosk->tank_capacity_liters }} L
                        </span>
                    </div>

                </div>
            </div>

            <div class="text-xs text-slate-400 bg-slate-900/60 p-3 rounded-xl border border-slate-800/80 space-y-1.5 font-mono">
                <div class="flex justify-between">
                    <span class="font-sans">Kapasitas Input:</span>
                    <span class="text-white font-bold">{{ number_format($tankCapacityMl ?? 20000, 0, ',', '.') }} mL</span>
                </div>
                <div class="flex justify-between">
                    <span class="font-sans">Output Terjual:</span>
                    <span class="text-cyan-400 font-bold">- {{ number_format($totalWaterSoldMl ?? 0, 0, ',', '.') }} mL</span>
                </div>
                <div class="flex justify-between border-t border-slate-800 pt-1">
                    <span class="font-sans font-bold">Sisa Air Tangki:</span>
                    <span class="{{ ($selectedKiosk->current_water_level_pct <= 5.0 || $selectedKiosk->status === 'OUT_OF_WATER') ? 'text-rose-400 font-black' : 'text-emerald-400 font-bold' }}">
                        {{ number_format($remainingWaterMl ?? 0, 0, ',', '.') }} mL ({{ $selectedKiosk->current_water_level_pct }}%)
                    </span>
                </div>
                <div class="flex justify-between pt-1">
                    <span class="font-sans">Status Kios:</span>
                    <span class="{{ ($selectedKiosk->current_water_level_pct <= 5.0 || $selectedKiosk->status === 'OUT_OF_WATER') ? 'text-rose-400 font-black' : 'text-emerald-400 font-bold' }}">
                        {{ ($selectedKiosk->current_water_level_pct <= 5.0 || $selectedKiosk->status === 'OUT_OF_WATER') ? 'OUT OF WATER' : 'SIAP MELAYANI' }}
                    </span>
                </div>
            </div>
        </div>

        <!-- 2. Kondisi Filter & Lampu UV Maintenance -->
        <div class="lg:col-span-2 admin-card rounded-2xl p-4 sm:p-6 space-y-6">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 border-b border-slate-800 pb-4">
                <div>
                    <h3 class="font-extrabold text-white text-base flex items-center gap-2">
                        <i class="fa-solid fa-filter text-purple-400"></i>
                        <span>Pemantauan Filter & Masa Pakai Lampu UV</span>
                    </h3>
                    <p class="text-xs text-slate-400 mt-1">Indikator kebutuhan penggantian elemen filter & lampu UV-C</p>
                </div>
                <div class="text-xs text-slate-500">
                    Kapasitas Terpakai
                </div>
            </div>

            <div class="space-y-5">
                @forelse($filters as $filter)
                @php
                    $pct = $filter->lifespan_percentage;
                    $color = $pct > 50 ? 'emerald' : ($pct > 20 ? 'amber' : 'red');
                @endphp
                <div class="p-4 rounded-xl bg-slate-900/60 border border-slate-800 space-y-3">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="w-10 h-10 rounded-xl bg-slate-800 flex items-center justify-center text-{{ $color }}-400 text-lg border border-slate-700">
                                @if($filter->filter_type === 'UV_LAMP')
                                    <i class="fa-solid fa-radiation"></i>
                                @else
                                    <i class="fa-solid fa-filter"></i>
                                @endif
                            </div>
                            <div>
                                <h4 class="font-bold text-white text-sm">{{ $filter->filter_name }}</h4>
                                <p class="text-[11px] text-slate-400">
                                    Tipe: {{ $filter->filter_type }} • Terakhir ganti: {{ $filter->last_replaced_at ? $filter->last_replaced_at->format('d M Y') : 'Baru' }}
                                </p>
                            </div>
                        </div>

                        <div class="flex items-center justify-between sm:justify-end gap-3">
                            <span class="text-sm font-black font-mono text-{{ $color }}-400">
                                {{ $pct }}% Tersisa
                            </span>
                            <button onclick="resetFilter({{ $filter->id }}, '{{ $filter->filter_name }}')"
                                    class="px-3 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white text-xs transition border border-slate-700">
                                Ganti / Reset
                            </button>
                        </div>
                    </div>

                    <!-- Progress Lifespan Bar -->
                    <div class="w-full bg-slate-800 rounded-full h-2 overflow-hidden">
                        <div class="bg-{{ $color }}-500 h-full rounded-full transition-all duration-500" style="width: {{ $pct }}%;"></div>
                    </div>
                </div>
                @empty
                <div class="text-center py-6 text-xs text-slate-500">
                    Belum ada data filter terkonfigurasi.
                </div>
                @endforelse
            </div>
        </div>

    </div>

    <!-- Bottom Section: Jadwal Sterilisasi UV Otomatis & Transaksi Terbaru -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        <!-- Jadwal Sterilisasi UV Otomatis -->
        <div class="admin-card rounded-2xl p-4 sm:p-6 space-y-4">
            <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                <h3 class="font-bold text-white text-sm flex items-center gap-2">
                    <i class="fa-solid fa-clock-rotate-left text-purple-400"></i>
                    <span>Jadwal Sterilisasi UV Otomatis</span>
                </h3>
                <span class="text-[10px] px-2 py-0.5 rounded bg-purple-950 text-purple-300 border border-purple-800">
                    ESP32 Cron
                </span>
            </div>
            <p class="text-xs text-slate-400">
                ESP32 menyalakan UV secara otomatis pada jam sepi untuk membersihkan biofilm pada pipa dan nozzle.
            </p>

            <div class="space-y-3">
                @forelse($uvSchedules as $schedule)
                <div class="p-3.5 rounded-xl bg-slate-900/70 border border-slate-800 flex items-center justify-between text-xs">
                    <div>
                        <div class="font-bold text-white flex items-center gap-2">
                            <i class="fa-regular fa-clock text-purple-400"></i>
                            Pukul {{ $schedule->cycle_time }} WIB
                        </div>
                        <div class="text-[11px] text-slate-400">Durasi: {{ $schedule->duration_seconds }} Detik (Flushing UV)</div>
                    </div>
                    <span class="px-2 py-1 rounded bg-emerald-950 text-emerald-400 text-[10px] font-bold border border-emerald-800">
                        AKTIF
                    </span>
                </div>
                @empty
                <div class="text-xs text-slate-500 text-center py-4">Tidak ada jadwal aktif.</div>
                @endforelse
            </div>
        </div>

        <!-- Tabel Transaksi Terbaru (AiYO QRIS) -->
        <div class="lg:col-span-2 admin-card rounded-2xl p-4 sm:p-6 space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 border-b border-slate-800 pb-3">
                <h3 class="font-bold text-white text-sm flex items-center gap-2">
                    <i class="fa-solid fa-receipt text-cyan-400"></i>
                    <span>Riwayat Transaksi AiYO QRIS Terbaru</span>
                </h3>
                <span class="text-xs text-slate-400">10 Transaksi Terakhir</span>
            </div>

            <div class="overflow-x-auto -mx-4 sm:mx-0 px-4 sm:px-0">
                <table class="w-full min-w-[620px] text-left text-xs">
                    <thead>
                        <tr class="border-b border-slate-800 text-slate-400">
                            <th class="pb-3 font-semibold">Ref ID</th>
                            <th class="pb-3 font-semibold">Waktu</th>
                            <th class="pb-3 font-semibold">Jenis Air</th>
                            <th class="pb-3 font-semibold">Volume</th>
                            <th class="pb-3 font-semibold">Nominal</th>
                            <th class="pb-3 font-semibold">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/60 font-mono text-slate-300">
                        @forelse($recentTransactions as $tx)
                        <tr>
                            <td class="py-3 text-cyan-400 font-bold">{{ $tx->referenceId }}</td>
                            <td class="py-3 font-sans text-slate-400">{{ ($tx->created_at ?? $tx->timestamp)?->format('H:i:s') ?? '-' }}</td>
                            <td class="py-3 font-sans">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold {{ $tx->water_type === 'COLD' ? 'bg-slate-800 text-slate-400' : 'bg-emerald-950 text-emerald-300' }}">
                                    {{ $tx->water_type === 'COLD' ? 'COLD (Riwayat Lama)' : 'NORMAL' }}
                                </span>
                            </td>
                            <td class="py-3">{{ $tx->volume_ml }} ml</td>
                            <td class="py-3 font-sans font-bold text-white">Rp {{ number_format($tx->payAmount, 0, ',', '.') }}</td>
                            <td class="py-3 font-sans">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold {{ $tx->status === 'COMPLETED' ? 'bg-emerald-500/20 text-emerald-400' : ($tx->status === 'PAID' ? 'bg-purple-500/20 text-purple-400' : 'bg-slate-800 text-slate-400') }}">
                                    {{ $tx->status }}
                                </span>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="text-center py-6 font-sans text-slate-500">
                                Belum ada transaksi tercatat.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <!-- Modal Input Isi Ulang Air (Refill) -->
    <div id="refill-modal" class="fixed inset-0 z-50 bg-black/80 backdrop-blur-sm hidden flex items-center justify-center p-4">
        <div class="admin-card rounded-2xl max-w-md w-full p-6 space-y-5 border border-slate-700 shadow-2xl relative">
            <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                <h3 class="text-base font-bold text-white flex items-center gap-2">
                    <i class="fa-solid fa-faucet-drip text-emerald-400"></i>
                    <span>Input Pengisian Ulang Air (Refill)</span>
                </h3>
                <button type="button" onclick="closeRefillModal()" class="text-slate-400 hover:text-white p-1">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>

            <p class="text-xs text-slate-300">
                Pencatatan volume air yang baru saja dituangkan teknisi ke dalam tangki dispenser kios <b>{{ $selectedKiosk->name }}</b>.
            </p>

            <div class="space-y-3 text-xs">
                <label class="block font-bold text-slate-300">Pilih Volume Pengisian:</label>
                <div class="grid grid-cols-3 gap-2">
                    <button type="button" onclick="setRefillPreset(19)" class="p-2.5 rounded-xl bg-slate-900 border border-slate-700 hover:border-emerald-500 text-center font-bold text-white transition focus:border-emerald-400 focus:bg-emerald-950/30">
                        19 Liter<br><span class="text-[10px] text-slate-400 font-normal">1 Galon Std</span>
                    </button>
                    <button type="button" onclick="setRefillPreset(20)" class="p-2.5 rounded-xl bg-slate-900 border border-slate-700 hover:border-emerald-500 text-center font-bold text-white transition focus:border-emerald-400 focus:bg-emerald-950/30">
                        20 Liter<br><span class="text-[10px] text-slate-400 font-normal">Tangki Sedang</span>
                    </button>
                    <button type="button" onclick="setRefillPreset(50)" class="p-2.5 rounded-xl bg-slate-900 border border-slate-700 hover:border-emerald-500 text-center font-bold text-white transition focus:border-emerald-400 focus:bg-emerald-950/30">
                        50 Liter<br><span class="text-[10px] text-slate-400 font-normal">Tangki Besar</span>
                    </button>
                </div>

                <div class="pt-2">
                    <label for="refill-liters-input" class="block font-bold text-slate-400 mb-1">Volume Kustom (Liter):</label>
                    <input type="number" id="refill-liters-input" step="0.5" min="1" max="500" value="19"
                           class="w-full bg-slate-900 border border-slate-700 rounded-xl px-4 py-2.5 text-white font-mono text-sm focus:border-emerald-400 focus:outline-none">
                    <span class="text-[11px] text-slate-400 mt-1 block">Setara dengan: <b id="refill-ml-preview" class="text-emerald-400">19.000 mL</b></span>
                </div>
            </div>

            <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-800">
                <button type="button" onclick="closeRefillModal()" class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-bold transition">
                    Batal
                </button>
                <button type="button" id="btn-submit-refill" onclick="submitRefill()" class="px-5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold transition flex items-center gap-2 shadow-lg shadow-emerald-950/50">
                    <i class="fa-solid fa-check"></i>
                    <span>Simpan & Pulihkan Kios</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Modal Ganti Wi-Fi Kios dari Jarak Jauh (Cloud) -->
    <div id="wifi-modal" class="fixed inset-0 z-50 bg-black/80 backdrop-blur-sm hidden flex items-center justify-center p-4">
        <div class="admin-card rounded-2xl max-w-md w-full p-6 space-y-5 border border-slate-700 shadow-2xl relative">
            <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                <h3 class="text-base font-bold text-white flex items-center gap-2">
                    <i class="fa-solid fa-wifi text-sky-400"></i>
                    <span>Ganti Wi-Fi Kios dari Cloud</span>
                </h3>
                <button type="button" onclick="closeWifiModal()" class="text-slate-400 hover:text-white p-1">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>

            <p class="text-xs text-slate-300">
                Kirim konfigurasi SSID & Password baru langsung ke mesin kios <strong>{{ $selectedKiosk->name ?? $selectedKiosk->id }}</strong>. ESP32 yang sedang online akan otomatis mengunduh konfigurasi ini dalam 2 detik, menyimpan ke memori Flash NVS, dan reboot ke Wi-Fi baru.
            </p>

            <div class="space-y-4">
                <div class="space-y-1.5">
                    <label class="text-xs font-bold text-slate-400 flex items-center gap-1.5">
                        <i class="fa-solid fa-network-wired text-sky-400"></i> Nama Wi-Fi (SSID Baru)
                    </label>
                    <input type="text" id="wifi-ssid-input" placeholder="Contoh: WiFi-Kantor / Hotspot-HP" class="w-full rounded-xl border border-slate-700 bg-slate-900 px-4 py-2.5 text-xs text-white outline-none focus:border-sky-400">
                </div>

                <div class="space-y-1.5">
                    <label class="text-xs font-bold text-slate-400 flex items-center gap-1.5">
                        <i class="fa-solid fa-key text-amber-400"></i> Password Wi-Fi
                    </label>
                    <input type="text" id="wifi-pass-input" placeholder="Kosongkan jika Wi-Fi tanpa sandi" class="w-full rounded-xl border border-slate-700 bg-slate-900 px-4 py-2.5 text-xs text-white outline-none focus:border-sky-400">
                </div>

                <div class="p-3 rounded-xl bg-sky-950/40 border border-sky-800/50 text-[11px] text-sky-300 space-y-1">
                    <div class="font-bold flex items-center gap-1">
                        <i class="fa-solid fa-circle-info"></i> Catatan Penting:
                    </div>
                    <div>Pastikan nama Wi-Fi dan sandi sudah benar. Jika ESP32 gagal tersambung ke Wi-Fi baru, ESP32 otomatis menyalakan Hotspot darurat <strong>FHK-Kiosk-Setup (192.168.4.1)</strong>.</div>
                </div>
            </div>

            <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-800">
                <button type="button" onclick="closeWifiModal()" class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-semibold transition">
                    Batal
                </button>
                <button type="button" id="btn-submit-wifi" onclick="submitSetWifi()" class="px-5 py-2 rounded-xl bg-sky-600 hover:bg-sky-500 text-white text-xs font-bold transition flex items-center gap-2 shadow-lg shadow-sky-950/50">
                    <i class="fa-solid fa-paper-plane"></i>
                    <span>Kirim Perintah ke ESP32</span>
                </button>
            </div>
        </div>
    </div>

</div>
@endsection

@section('scripts')
<script>
    function openWifiModal() {
        document.getElementById('wifi-modal').classList.remove('hidden');
    }

    function closeWifiModal() {
        document.getElementById('wifi-modal').classList.add('hidden');
    }

    async function submitSetWifi() {
        const ssidInput = document.getElementById('wifi-ssid-input');
        const passInput = document.getElementById('wifi-pass-input');
        const ssid = ssidInput ? ssidInput.value.trim() : '';
        const pass = passInput ? passInput.value.trim() : '';
        const btn = document.getElementById('btn-submit-wifi');

        if (!ssid) {
            alert('Mohon masukkan Nama Wi-Fi (SSID).');
            return;
        }

        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Mengirim ke Kios...';

        try {
            const res = await fetch("{{ route('admin.set_wifi', ['kioskId' => $selectedKiosk->id]) }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    ssid: ssid,
                    password: pass
                })
            });
            const data = await res.json();
            alert(data.message || 'Perintah ganti Wi-Fi berhasil dikirim ke kios!');
            closeWifiModal();
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Kirim Perintah ke ESP32';
        } catch (e) {
            alert('Gagal mengirim perintah ganti Wi-Fi.');
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Kirim Perintah ke ESP32';
        }
    }
    function openRefillModal() {
        document.getElementById('refill-modal').classList.remove('hidden');
    }

    function closeRefillModal() {
        document.getElementById('refill-modal').classList.add('hidden');
    }

    function setRefillPreset(liters) {
        const input = document.getElementById('refill-liters-input');
        input.value = liters;
        updateRefillPreview();
    }

    function updateRefillPreview() {
        const input = document.getElementById('refill-liters-input');
        const liters = parseFloat(input.value) || 0;
        const ml = Math.round(liters * 1000);
        document.getElementById('refill-ml-preview').textContent = ml.toLocaleString('id-ID') + ' mL';
    }

    document.getElementById('refill-liters-input')?.addEventListener('input', updateRefillPreview);

    async function submitRefill() {
        const input = document.getElementById('refill-liters-input');
        const liters = parseFloat(input.value) || 19;
        const btn = document.getElementById('btn-submit-refill');

        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Menyimpan...';

        try {
            const res = await fetch("{{ route('admin.refill_water', ['kioskId' => $selectedKiosk->id]) }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    refill_liters: liters
                })
            });
            const data = await res.json();
            alert(data.message || 'Pengisian ulang air berhasil dicatat!');
            window.location.reload();
        } catch (e) {
            alert('Gagal mencatat pengisian ulang air.');
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-check"></i> Simpan & Pulihkan Kios';
        }
    }
    async function triggerEmergencyUv() {
        if (!confirm('Jalankan siklus sterilisasi UV manual pada pipa & nozzle kios sekarang?')) return;

        try {
            const res = await fetch("{{ route('admin.trigger_uv', ['kioskId' => $selectedKiosk->id]) }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            });
            const data = await res.json();
            alert(data.message);
        } catch (e) {
            alert('Gagal memicu sterilisasi UV.');
        }
    }

    async function resetFilter(filterId, name) {
        if (!confirm(`Konfirmasi penggantian filter '${name}' ke kondisi baru (100%)?`)) return;

        try {
            const res = await fetch(`/admin/reset-filter/${filterId}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            });
            const data = await res.json();
            alert(data.message);
            window.location.reload();
        } catch (e) {
            alert('Gagal reset filter.');
        }
    }
</script>
@endsection
