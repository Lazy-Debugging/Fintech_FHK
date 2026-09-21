@extends('layouts.admin_layout')

@section('title', 'Pengaturan Harga Kios - Fresh Hydration Kios')

@section('content')
<div class="space-y-6 sm:space-y-8">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-cyan-500/20 text-cyan-400 flex items-center justify-center text-xl font-bold border border-cyan-500/30">
                    <i class="fa-solid fa-tags"></i>
                </div>
                <div>
                    <h2 class="text-xl sm:text-2xl font-black text-white tracking-tight">Pengaturan Harga Kios</h2>
                    <p class="text-xs text-slate-400 mt-0.5">Atur tarif penjualan air (Cold / Normal) secara global atau per unit Kios.</p>
                </div>
            </div>
        </div>
        <div>
            <a href="{{ route('admin.dashboard') }}" class="px-4 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-bold transition flex items-center gap-2 border border-slate-700">
                <i class="fa-solid fa-arrow-left"></i>
                <span>Kembali ke Dashboard</span>
            </a>
        </div>
    </div>

    <!-- Alert status -->
    <div id="alert-box" class="hidden p-4 rounded-xl text-sm font-semibold flex items-center justify-between transition">
        <span id="alert-msg"></span>
        <button onclick="document.getElementById('alert-box').classList.add('hidden')" class="opacity-70 hover:opacity-100">
            <i class="fa-solid fa-xmark"></i>
        </button>
    </div>

    <!-- TAB 1: Harga Global (Default Semua Kios) -->
    <div class="admin-card rounded-2xl p-5 sm:p-6 space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-800 pb-4">
            <div>
                <h3 class="text-base font-bold text-white flex items-center gap-2">
                    <i class="fa-solid fa-globe text-cyan-400"></i> Harga Default Global
                </h3>
                <p class="text-xs text-slate-400">Harga ini berlaku otomatis untuk semua Kios yang tidak memiliki penyesuaian khusus.</p>
            </div>
            <button onclick="saveGlobalPrices()" class="px-5 py-2.5 rounded-xl bg-cyan-500 hover:bg-cyan-400 text-slate-950 text-xs font-extrabold transition flex items-center justify-center gap-2 shadow-lg shadow-cyan-500/20">
                <i class="fa-solid fa-floppy-disk"></i>
                <span>Simpan Harga Global</span>
            </button>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- COLD WATER -->
            <div class="bg-slate-950/60 rounded-xl p-4 border border-slate-800/80 space-y-4">
                <div class="flex items-center gap-2 text-cyan-400 font-bold text-sm border-b border-slate-800 pb-2">
                    <i class="fa-solid fa-snowflake text-lg"></i>
                    <span>Air Dingin (COLD)</span>
                </div>
                <div class="space-y-3">
                    @foreach([250 => '250 ml (Gelas)', 500 => '500 ml (Botol Sedang)', 1000 => '1000 ml (Botol Besar/Tumbler)'] as $vol => $label)
                        @php
                            $globalCold = $globalPrices->firstWhere(fn($p) => $p->water_type === 'COLD' && $p->volume_ml == $vol);
                            $priceCold = $globalCold ? $globalCold->price : ($vol == 250 ? 2000 : ($vol == 500 ? 3500 : 6000));
                        @endphp
                        <div class="flex items-center justify-between gap-3">
                            <label class="text-xs text-slate-300 font-medium shrink-0 w-1/2">{{ $label }}</label>
                            <div class="relative flex-1">
                                <span class="absolute left-3 top-2.5 text-xs text-slate-500 font-bold">Rp</span>
                                <input type="number" data-global-type="COLD" data-global-vol="{{ $vol }}" value="{{ $priceCold }}" min="0" step="500" class="w-full bg-slate-900 border border-slate-700 rounded-xl pl-9 pr-3 py-2 text-xs font-bold text-white focus:border-cyan-400 focus:outline-none text-right">
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- NORMAL WATER -->
            <div class="bg-slate-950/60 rounded-xl p-4 border border-slate-800/80 space-y-4">
                <div class="flex items-center gap-2 text-emerald-400 font-bold text-sm border-b border-slate-800 pb-2">
                    <i class="fa-solid fa-droplet text-lg"></i>
                    <span>Air Suhu Ruang (NORMAL)</span>
                </div>
                <div class="space-y-3">
                    @foreach([250 => '250 ml (Gelas)', 500 => '500 ml (Botol Sedang)', 1000 => '1000 ml (Botol Besar/Tumbler)'] as $vol => $label)
                        @php
                            $globalNorm = $globalPrices->firstWhere(fn($p) => $p->water_type === 'NORMAL' && $p->volume_ml == $vol);
                            $priceNorm = $globalNorm ? $globalNorm->price : ($vol == 250 ? 1500 : ($vol == 500 ? 2500 : 4500));
                        @endphp
                        <div class="flex items-center justify-between gap-3">
                            <label class="text-xs text-slate-300 font-medium shrink-0 w-1/2">{{ $label }}</label>
                            <div class="relative flex-1">
                                <span class="absolute left-3 top-2.5 text-xs text-slate-500 font-bold">Rp</span>
                                <input type="number" data-global-type="NORMAL" data-global-vol="{{ $vol }}" value="{{ $priceNorm }}" min="0" step="500" class="w-full bg-slate-900 border border-slate-700 rounded-xl pl-9 pr-3 py-2 text-xs font-bold text-white focus:border-emerald-400 focus:outline-none text-right">
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <!-- TAB 2: Harga Khusus Per Unit Kios -->
    <div class="admin-card rounded-2xl p-5 sm:p-6 space-y-6">
        <div class="border-b border-slate-800 pb-4">
            <h3 class="text-base font-bold text-white flex items-center gap-2">
                <i class="fa-solid fa-sliders text-purple-400"></i> Penyesuaian Harga Spesifik Per Kios
            </h3>
            <p class="text-xs text-slate-400">Gunakan fitur ini jika ada kios tertentu di lokasi premium (misal: Bandara/Mall) yang memerlukan tarif berbeda.</p>
        </div>

        @if($kiosksWithPricing->isEmpty())
            <div class="text-center py-8 text-slate-500 text-xs">
                Belum ada data unit kios yang terdaftar di database.
            </div>
        @else
            <div class="space-y-6">
                @foreach($kiosksWithPricing as $kiosk)
                    @php
                        $hasCustom = $kiosk->prices->isNotEmpty();
                    @endphp
                    <div class="bg-slate-950/70 border border-slate-800 rounded-2xl p-4 sm:p-5 space-y-4">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-800/80 pb-3">
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="font-extrabold text-white text-sm">{{ $kiosk->name }}</span>
                                    <code class="text-[11px] px-2 py-0.5 rounded bg-slate-800 text-slate-400">{{ $kiosk->id }}</code>
                                    @if($hasCustom)
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-purple-500/20 text-purple-300 border border-purple-500/30">Tarif Khusus</span>
                                    @else
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-800 text-slate-400">Mengikuti Global</span>
                                    @endif
                                </div>
                                <p class="text-[11px] text-slate-400 mt-0.5"><i class="fa-solid fa-location-dot text-slate-500"></i> {{ $kiosk->location ?? '-' }}</p>
                            </div>
                            <div class="flex items-center gap-2">
                                @if($hasCustom)
                                    <button onclick="resetKioskToGlobal('{{ $kiosk->id }}')" class="px-3 py-1.5 rounded-xl bg-slate-800 hover:bg-red-950/60 hover:text-red-300 text-slate-400 border border-slate-700 text-xs font-semibold transition flex items-center gap-1.5">
                                        <i class="fa-solid fa-rotate-left"></i> Reset Ke Global
                                    </button>
                                @endif
                                <button onclick="saveKioskPrices('{{ $kiosk->id }}')" class="px-4 py-1.5 rounded-xl bg-purple-600 hover:bg-purple-500 text-white text-xs font-bold transition flex items-center gap-1.5 shadow-md shadow-purple-900/30">
                                    <i class="fa-solid fa-floppy-disk"></i> Simpan Kios Ini
                                </button>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <!-- COLD -->
                            <div class="space-y-2">
                                <span class="text-[11px] font-bold text-cyan-400 flex items-center gap-1">
                                    <i class="fa-solid fa-snowflake"></i> Air Dingin (COLD)
                                </span>
                                @foreach([250, 500, 1000] as $vol)
                                    @php
                                        $customP = $kiosk->prices->firstWhere(fn($p) => $p->water_type === 'COLD' && $p->volume_ml == $vol);
                                        $globalP = $globalPrices->firstWhere(fn($p) => $p->water_type === 'COLD' && $p->volume_ml == $vol);
                                        $pVal = $customP ? $customP->price : ($globalP ? $globalP->price : ($vol == 250 ? 2000 : ($vol == 500 ? 3500 : 6000)));
                                    @endphp
                                    <div class="flex items-center justify-between gap-2 text-xs">
                                        <span class="text-slate-400">{{ $vol }} ml:</span>
                                        <div class="relative w-36">
                                            <span class="absolute left-2.5 top-2 text-[11px] text-slate-500 font-bold">Rp</span>
                                            <input type="number" data-kiosk-id="{{ $kiosk->id }}" data-kiosk-type="COLD" data-kiosk-vol="{{ $vol }}" value="{{ $pVal }}" min="0" step="500" class="w-full bg-slate-900 border border-slate-700 rounded-lg pl-8 pr-2 py-1.5 text-xs font-bold text-white focus:border-purple-400 focus:outline-none text-right">
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            <!-- NORMAL -->
                            <div class="space-y-2">
                                <span class="text-[11px] font-bold text-emerald-400 flex items-center gap-1">
                                    <i class="fa-solid fa-droplet"></i> Air Normal (NORMAL)
                                </span>
                                @foreach([250, 500, 1000] as $vol)
                                    @php
                                        $customP = $kiosk->prices->firstWhere(fn($p) => $p->water_type === 'NORMAL' && $p->volume_ml == $vol);
                                        $globalP = $globalPrices->firstWhere(fn($p) => $p->water_type === 'NORMAL' && $p->volume_ml == $vol);
                                        $pVal = $customP ? $customP->price : ($globalP ? $globalP->price : ($vol == 250 ? 1500 : ($vol == 500 ? 2500 : 4500)));
                                    @endphp
                                    <div class="flex items-center justify-between gap-2 text-xs">
                                        <span class="text-slate-400">{{ $vol }} ml:</span>
                                        <div class="relative w-36">
                                            <span class="absolute left-2.5 top-2 text-[11px] text-slate-500 font-bold">Rp</span>
                                            <input type="number" data-kiosk-id="{{ $kiosk->id }}" data-kiosk-type="NORMAL" data-kiosk-vol="{{ $vol }}" value="{{ $pVal }}" min="0" step="500" class="w-full bg-slate-900 border border-slate-700 rounded-lg pl-8 pr-2 py-1.5 text-xs font-bold text-white focus:border-purple-400 focus:outline-none text-right">
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>

<script>
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    function showAlert(msg, isSuccess = true) {
        const box = document.getElementById('alert-box');
        const txt = document.getElementById('alert-msg');
        box.className = `p-4 rounded-xl text-sm font-semibold flex items-center justify-between transition ${
            isSuccess ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30' : 'bg-red-500/20 text-red-300 border border-red-500/30'
        }`;
        txt.textContent = msg;
        box.classList.remove('hidden');
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function saveGlobalPrices() {
        const inputs = document.querySelectorAll('input[data-global-type]');
        const prices = [];
        inputs.forEach(input => {
            prices.push({
                kiosk_id: null,
                water_type: input.getAttribute('data-global-type'),
                volume_ml: parseInt(input.getAttribute('data-global-vol')),
                price: parseInt(input.value) || 0
            });
        });

        fetch('{{ route('admin.pricing.update') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            },
            body: JSON.stringify({ prices })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showAlert(data.message, true);
                setTimeout(() => location.reload(), 1200);
            } else {
                showAlert(data.message || 'Gagal menyimpan harga.', false);
            }
        })
        .catch(err => {
            showAlert('Terjadi kesalahan jaringan.', false);
        });
    }

    function saveKioskPrices(kioskId) {
        const inputs = document.querySelectorAll(`input[data-kiosk-id="${kioskId}"]`);
        const prices = [];
        inputs.forEach(input => {
            prices.push({
                kiosk_id: kioskId,
                water_type: input.getAttribute('data-kiosk-type'),
                volume_ml: parseInt(input.getAttribute('data-kiosk-vol')),
                price: parseInt(input.value) || 0
            });
        });

        fetch('{{ route('admin.pricing.update') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            },
            body: JSON.stringify({ prices })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showAlert(data.message, true);
                setTimeout(() => location.reload(), 1200);
            } else {
                showAlert(data.message || 'Gagal menyimpan harga kios.', false);
            }
        })
        .catch(err => {
            showAlert('Terjadi kesalahan jaringan.', false);
        });
    }

    function resetKioskToGlobal(kioskId) {
        if (!confirm(`Reset semua harga untuk Kios ${kioskId} agar mengikuti harga Global?`)) return;

        fetch('{{ route('admin.pricing.reset') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            },
            body: JSON.stringify({ kiosk_id: kioskId })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showAlert(data.message, true);
                setTimeout(() => location.reload(), 1200);
            } else {
                showAlert(data.message || 'Gagal me-reset harga kios.', false);
            }
        })
        .catch(err => {
            showAlert('Terjadi kesalahan jaringan.', false);
        });
    }
</script>
@endsection
