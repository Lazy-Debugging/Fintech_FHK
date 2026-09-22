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
            <button onclick="triggerEmergencyUv()" class="px-4 py-2.5 rounded-xl bg-purple-900/50 hover:bg-purple-800/60 text-purple-200 border border-purple-700/60 text-xs font-bold transition flex items-center justify-center gap-2 shadow-lg shadow-purple-950/40">
                <i class="fa-solid fa-shield-virus text-purple-400"></i>
                <span>Jalankan Sterilisasi UV Sekarang</span>
            </button>
            <a href="{{ route('admin.simulator') }}" class="px-4 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-bold transition flex items-center justify-center gap-2 border border-slate-700">
                <i class="fa-solid fa-microchip text-cyan-400"></i>
                <span>Simulator ESP32</span>
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
                <span>Air Dikeluarkan</span>
                <div class="w-8 h-8 rounded-lg bg-cyan-500/10 text-cyan-400 flex items-center justify-center">
                    <i class="fa-solid fa-bottle-water"></i>
                </div>
            </div>
            <div class="text-xl sm:text-2xl font-black text-white break-words">
                {{ number_format($totalLitersDispensed, 1, ',', '.') }} <span class="text-base text-slate-400 font-normal">Liter</span>
            </div>
            <div class="text-[11px] text-cyan-400 font-medium">
                Tersaring & disterilkan UV
            </div>
        </div>

        <div class="admin-card rounded-2xl p-4 sm:p-5 space-y-2">
            <div class="flex items-center justify-between text-xs text-slate-400">
                <span>Suhu Air Dingin</span>
                <div class="w-8 h-8 rounded-lg bg-sky-500/10 text-sky-400 flex items-center justify-center">
                    <i class="fa-solid fa-temperature-arrow-down"></i>
                </div>
            </div>
            <div class="text-xl sm:text-2xl font-black text-white">
                {{ $selectedKiosk->current_temp_celsius ?? 7.5 }}°C
            </div>
            <div class="text-[11px] text-sky-400 font-medium">
                Kompresor Chiller Optimal
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

            <div class="text-xs text-slate-400 bg-slate-900/60 p-3 rounded-xl border border-slate-800/80 space-y-1">
                <div class="flex justify-between">
                    <span>Ambang Batas Minimum:</span>
                    <span class="text-amber-400 font-bold">10% (5 Liter)</span>
                </div>
                <div class="flex justify-between">
                    <span>Status Dispenser:</span>
                    <span class="text-emerald-400 font-bold">Siap Melayani Pengisian</span>
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
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold {{ $tx->water_type === 'COLD' ? 'bg-cyan-950 text-cyan-300' : 'bg-emerald-950 text-emerald-300' }}">
                                    {{ $tx->water_type }}
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

</div>
@endsection

@section('scripts')
<script>
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
