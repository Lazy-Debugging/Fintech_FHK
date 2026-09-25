@extends('layouts.kiosk_layout')

@section('title', 'Pilih Jenis Air & Volume - Fresh Hydration Kios')

@section('content')
<div class="w-full max-w-4xl mx-auto space-y-5 sm:space-y-8 my-auto py-2">

    <!-- Hero Title -->
    <div class="text-center space-y-2">
        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-cyan-950/80 border border-cyan-800 text-cyan-300 text-xs font-semibold uppercase tracking-wider">
            <i class="fa-solid fa-sparkles"></i> 100% UV-C Sterilized & Mineral Enriched
        </span>
        <h2 class="text-2xl sm:text-4xl font-black tracking-tight text-white">
            Sentuh & Pilih Hidrasi Higienis Anda
        </h2>
        <p class="text-sm text-slate-400 max-w-lg mx-auto">
            Air minum murni langsung dari dispenser dengan sterilisasi lampu UV otomatis yang dikontrol presisi oleh mikrokontroler ESP32.
        </p>
    </div>

    @if(session('error'))
    <div class="p-4 rounded-2xl bg-rose-500/10 border border-rose-500/30 text-rose-300 text-sm flex items-center gap-3">
        <i class="fa-solid fa-triangle-exclamation text-rose-400 text-lg"></i>
        <div>{{ session('error') }}</div>
    </div>
    @endif

    {{-- Order sekarang diproses via fetch() JS ke OrderController@createOrder --}}

    <!-- Main Selection Form Container -->
    <div class="glass-panel rounded-2xl sm:rounded-3xl p-4 sm:p-8 space-y-6 sm:space-y-8 relative overflow-hidden">
        <div class="absolute -top-24 -right-24 w-60 h-60 bg-cyan-500/10 rounded-full blur-3xl pointer-events-none"></div>
        <div class="absolute -bottom-24 -left-24 w-60 h-60 bg-purple-500/10 rounded-full blur-3xl pointer-events-none"></div>

        <!-- 1. Pilihan Suhu Air (Water Temperature) -->
        <div class="space-y-3">
            <label class="block text-xs font-bold uppercase tracking-wider text-slate-400 flex items-center gap-2">
                <span class="w-5 h-5 rounded-full bg-cyan-500/20 text-cyan-400 flex items-center justify-center text-xs">1</span>
                Pilih Suhu Air
            </label>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                
                <!-- Opsi Dingin (Cold) -->
                <div onclick="selectTemperature('COLD')" id="opt-cold"
                     class="temp-card active cursor-pointer p-5 rounded-2xl border-2 border-cyan-500 bg-gradient-to-br from-cyan-950/40 via-slate-900/60 to-slate-900/80 hover:border-cyan-400 transition-all duration-300 relative group">
                    <div class="flex items-start justify-between">
                        <div class="w-12 h-12 rounded-xl bg-cyan-500/20 border border-cyan-500/30 flex items-center justify-center text-cyan-400 text-2xl group-hover:scale-110 transition">
                            <i class="fa-solid fa-snowflake"></i>
                        </div>
                        <span class="px-2.5 py-1 rounded-full bg-cyan-500/20 text-cyan-300 text-xs font-semibold border border-cyan-500/30">
                            ~ 7.5°C Chilled
                        </span>
                    </div>
                    <div class="mt-4">
                        <h3 class="text-xl font-bold text-white group-hover:text-cyan-300 transition">Air Dingin (Cold)</h3>
                        <p class="text-xs text-slate-400 mt-1">Sangat segar seketika, cocok untuk botol olahraga & pelepas dahaga terik siang.</p>
                    </div>
                    <div class="absolute bottom-4 right-4 check-indicator text-cyan-400 text-xl">
                        <i class="fa-solid fa-circle-check"></i>
                    </div>
                </div>

                <!-- Opsi Normal (Ambient) -->
                <div onclick="selectTemperature('NORMAL')" id="opt-normal"
                     class="temp-card cursor-pointer p-5 rounded-2xl border-2 border-slate-800 bg-slate-900/40 hover:border-slate-700 transition-all duration-300 relative group">
                    <div class="flex items-start justify-between">
                        <div class="w-12 h-12 rounded-xl bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-400 text-2xl group-hover:scale-110 transition">
                            <i class="fa-solid fa-droplet"></i>
                        </div>
                        <span class="px-2.5 py-1 rounded-full bg-emerald-500/10 text-emerald-300 text-xs font-semibold border border-emerald-500/20">
                            ~ 25°C Room Temp
                        </span>
                    </div>
                    <div class="mt-4">
                        <h3 class="text-xl font-bold text-white group-hover:text-emerald-300 transition">Air Normal (Ambient)</h3>
                        <p class="text-xs text-slate-400 mt-1">Suhu ruangan ramah pencernaan, ideal untuk obat, vitamin, dan hidrasi harian.</p>
                    </div>
                    <div class="absolute bottom-4 right-4 check-indicator hidden text-emerald-400 text-xl">
                        <i class="fa-solid fa-circle-check"></i>
                    </div>
                </div>

            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            @auth
            <div class="space-y-1.5">
                <label for="voucher-code" class="text-xs font-bold uppercase tracking-wider text-slate-400">Kode Voucher</label>
                <input id="voucher-code" type="text" maxlength="40" placeholder="Masukkan kode voucher" class="w-full rounded-xl border border-slate-700 bg-slate-900 px-4 py-2.5 text-xs text-white uppercase outline-none focus:border-cyan-400">
            </div>
            @endauth

            <div class="space-y-1.5 @guest sm:col-span-2 @endguest">
                <label for="user-phone" class="text-xs font-bold uppercase tracking-wider text-slate-400 flex items-center gap-1.5">
                    <i class="fa-brands fa-whatsapp text-emerald-400"></i> No. WhatsApp (Notifikasi Otomatis)
                </label>
                <input id="user-phone" type="text" maxlength="30" value="{{ auth()->user()->phone ?? '' }}" placeholder="Contoh: 081234567890" class="w-full rounded-xl border border-slate-700 bg-slate-900 px-4 py-2.5 text-xs text-white outline-none focus:border-emerald-400">
            </div>
        </div>

        <!-- 2. Pilihan Volume Air (Volume Option) -->
        <div class="space-y-3">
            <label class="block text-xs font-bold uppercase tracking-wider text-slate-400 flex items-center gap-2">
                <span class="w-5 h-5 rounded-full bg-cyan-500/20 text-cyan-400 flex items-center justify-center text-xs">2</span>
                Pilih Ukuran Volume
            </label>
            <div class="grid grid-cols-1 min-[420px]:grid-cols-3 gap-3 sm:gap-4">
                
                <!-- 250 ml -->
                <div onclick="selectVolume(250)" id="vol-250"
                     class="vol-card cursor-pointer p-4 rounded-2xl border-2 border-slate-800 bg-slate-900/40 text-center hover:border-slate-700 transition duration-200">
                    <div class="text-slate-400 text-lg mb-1"><i class="fa-solid fa-mug-saucer"></i></div>
                    <div class="text-lg sm:text-xl font-extrabold text-white">250 ml</div>
                    <div class="text-[11px] text-slate-400">Gelas / Cup</div>
                    <div id="price-250" class="mt-2 text-xs font-bold text-cyan-400">Rp 2.000</div>
                </div>

                <!-- 500 ml (Default Active) -->
                <div onclick="selectVolume(500)" id="vol-500"
                     class="vol-card active cursor-pointer p-4 rounded-2xl border-2 border-cyan-500 bg-cyan-950/30 text-center hover:border-cyan-400 transition duration-200 relative">
                    <span class="absolute -top-2.5 left-1/2 -translate-x-1/2 px-2 py-0.5 rounded-full bg-cyan-500 text-[10px] font-black uppercase text-slate-950 tracking-wider">
                        Populer
                    </span>
                    <div class="text-cyan-400 text-lg mb-1"><i class="fa-solid fa-bottle-water"></i></div>
                    <div class="text-lg sm:text-xl font-extrabold text-white">500 ml</div>
                    <div class="text-[11px] text-slate-400">Tumbler Sedang</div>
                    <div id="price-500" class="mt-2 text-xs font-bold text-cyan-400">Rp 3.500</div>
                </div>

                <!-- 1000 ml -->
                <div onclick="selectVolume(1000)" id="vol-1000"
                     class="vol-card cursor-pointer p-4 rounded-2xl border-2 border-slate-800 bg-slate-900/40 text-center hover:border-slate-700 transition duration-200">
                    <div class="text-slate-400 text-lg mb-1"><i class="fa-solid fa-jar"></i></div>
                    <div class="text-lg sm:text-xl font-extrabold text-white">1000 ml</div>
                    <div class="text-[11px] text-slate-400">Botol 1 Liter</div>
                    <div id="price-1000" class="mt-2 text-xs font-bold text-cyan-400">Rp 6.000</div>
                </div>

            </div>
        </div>

        <!-- 3. Ringkasan & Tombol Aksi Pembayaran -->
        <div class="pt-4 border-t border-slate-800/80 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
            <div class="w-full sm:w-auto">
                <div class="text-xs text-slate-400">Total Pembayaran AiYO QRIS:</div>
                <div class="text-3xl font-black text-transparent bg-clip-text bg-gradient-to-r from-cyan-400 to-sky-200" id="display-total-price">
                    Rp 3.500
                </div>
                <div class="text-[11px] text-slate-500 flex items-center gap-1.5 mt-0.5">
                    <i class="fa-solid fa-lock text-emerald-400"></i>
                    <span>Tervalidasi Otomatis via Dynamic QRIS</span>
                </div>
            </div>

            <button id="btn-pay" onclick="submitOrder()"
                    class="w-full sm:w-auto px-8 py-4 rounded-2xl bg-gradient-to-r from-cyan-500 to-blue-600 hover:from-cyan-400 hover:to-blue-500 text-slate-950 font-black text-base shadow-xl shadow-cyan-500/25 hover:shadow-cyan-500/40 transform active:scale-95 transition flex items-center justify-center gap-3">
                <i class="fa-solid fa-qrcode text-lg"></i>
                <span>Bayar dengan AiYO QRIS</span>
                <i class="fa-solid fa-arrow-right text-sm"></i>
            </button>
        </div>

        <!-- Error Box for Order Failures -->
        <div id="order-error-box" class="hidden p-3 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-300 text-xs font-semibold flex items-center gap-2">
            <i class="fa-solid fa-triangle-exclamation text-rose-400"></i>
            <span></span>
        </div>

    </div>

    <!-- 3-Pillar IoT Assurance Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-xs text-slate-400">
        <div class="p-4 rounded-2xl bg-slate-900/40 border border-slate-800/60 flex items-center gap-3">
            <div class="w-8 h-8 rounded-lg bg-purple-500/10 text-purple-400 flex items-center justify-center text-base">
                <i class="fa-solid fa-radiation"></i>
            </div>
            <div>
                <div class="font-bold text-slate-200">Sterilisasi UV Nozzle</div>
                <div>Relay UV aktif sebelum penuangan dimulai</div>
            </div>
        </div>

        <div class="p-4 rounded-2xl bg-slate-900/40 border border-slate-800/60 flex items-center gap-3">
            <div class="w-8 h-8 rounded-lg bg-cyan-500/10 text-cyan-400 flex items-center justify-center text-base">
                <i class="fa-solid fa-gauge-high"></i>
            </div>
            <div>
                <div class="font-bold text-slate-200">Flow Meter Presisi</div>
                <div>Sensor pulsa air ESP32 akurasi ±5ml</div>
            </div>
        </div>

        <div class="p-4 rounded-2xl bg-slate-900/40 border border-slate-800/60 flex items-center gap-3">
            <div class="w-8 h-8 rounded-lg bg-emerald-500/10 text-emerald-400 flex items-center justify-center text-base">
                <i class="fa-solid fa-receipt"></i>
            </div>
            <div>
                <div class="font-bold text-slate-200">Struk Digital Otomatis</div>
                <div>Simpan bukti pembayaran langsung di HP</div>
            </div>
        </div>
    </div>

    <!-- Banner Redeem QR untuk Pelanggan HP -->
    <div class="glass-panel rounded-2xl sm:rounded-3xl p-4 sm:p-6 bg-gradient-to-r from-cyan-950/40 via-slate-900/80 to-blue-950/40 border border-cyan-500/30 flex flex-col sm:flex-row items-center justify-between gap-4 shadow-xl">
        <div class="flex items-center gap-4 text-center sm:text-left">
            <div class="w-20 h-20 rounded-2xl bg-white p-2 shadow-lg shrink-0 flex items-center justify-center mx-auto sm:mx-0">
                <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=FHK-KIOSK-QR:{{ $kiosk->id }}" alt="QR Code Kios" class="w-full h-full object-contain">
            </div>
            <div class="space-y-1">
                <div class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-cyan-500/20 text-cyan-300 text-[11px] font-extrabold">
                    <i class="fa-solid fa-qrcode"></i> SCAN UNTUK REDEEM AIR
                </div>
                <h3 class="text-base font-black text-white">Sudah Bayar dari HP?</h3>
                <p class="text-xs text-slate-300">
                    Buka <strong>Profil &gt; Riwayat Transaksi</strong> di HP Anda, klik <strong>"Scan QR Kios"</strong>, lalu arahkan kamera HP ke QR Code ini!
                </p>
            </div>
        </div>
        <div class="shrink-0 text-center sm:text-right">
            <span class="text-xs font-mono text-cyan-400 font-bold bg-slate-900/80 px-3 py-1.5 rounded-xl border border-cyan-800">
                ID Kios: {{ $kiosk->id }}
            </span>
        </div>
    </div>

</div>
@endsection

@section('scripts')
<script>
    let selectedTemp = 'COLD';
    let selectedVol = 500;
    const kioskId = "{{ $kiosk->id }}";

    // Auto-polling Layar Kios: Cek apakah ada penuangan air yang dipicu dari Scan HP
    const pollDispenseBaseUrl = "{{ url('api/kiosk') }}";
    setInterval(function() {
        fetch(`${pollDispenseBaseUrl}/${encodeURIComponent(kioskId)}/poll-dispense`)
            .then(res => res.json())
            .then(data => {
                if (data.dispensing && data.redirectUrl) {
                    window.location.href = data.redirectUrl;
                }
            })
            .catch(() => {});
    }, 2000);

    // Matrix Harga Dynamic dari Database
    const priceMatrix = {!! json_encode($priceMatrix ?? [
        'COLD'   => [ 250 => 2000, 500 => 3500, 1000 => 6000 ],
        'NORMAL' => [ 250 => 1500, 500 => 2500, 1000 => 4500 ]
    ]) !!};

    function updatePriceUI() {
        const currentPrices = priceMatrix[selectedTemp];
        document.getElementById('price-250').textContent = 'Rp ' + currentPrices[250].toLocaleString('id-ID');
        document.getElementById('price-500').textContent = 'Rp ' + currentPrices[500].toLocaleString('id-ID');
        document.getElementById('price-1000').textContent = 'Rp ' + currentPrices[1000].toLocaleString('id-ID');

        const total = currentPrices[selectedVol];
        document.getElementById('display-total-price').textContent = 'Rp ' + total.toLocaleString('id-ID');
    }

    function selectTemperature(temp) {
        selectedTemp = temp;
        const coldCard = document.getElementById('opt-cold');
        const normalCard = document.getElementById('opt-normal');

        if (temp === 'COLD') {
            coldCard.className = 'temp-card active cursor-pointer p-5 rounded-2xl border-2 border-cyan-500 bg-gradient-to-br from-cyan-950/40 via-slate-900/60 to-slate-900/80 transition relative group';
            normalCard.className = 'temp-card cursor-pointer p-5 rounded-2xl border-2 border-slate-800 bg-slate-900/40 hover:border-slate-700 transition relative group';
            coldCard.querySelector('.check-indicator').classList.remove('hidden');
            normalCard.querySelector('.check-indicator').classList.add('hidden');
        } else {
            normalCard.className = 'temp-card active cursor-pointer p-5 rounded-2xl border-2 border-emerald-500 bg-gradient-to-br from-emerald-950/40 via-slate-900/60 to-slate-900/80 transition relative group';
            coldCard.className = 'temp-card cursor-pointer p-5 rounded-2xl border-2 border-slate-800 bg-slate-900/40 hover:border-slate-700 transition relative group';
            normalCard.querySelector('.check-indicator').classList.remove('hidden');
            coldCard.querySelector('.check-indicator').classList.add('hidden');
        }

        updatePriceUI();
    }

    function selectVolume(vol) {
        selectedVol = vol;
        [250, 500, 1000].forEach(v => {
            const card = document.getElementById('vol-' + v);
            if (v === vol) {
                card.className = 'vol-card active cursor-pointer p-4 rounded-2xl border-2 border-cyan-500 bg-cyan-950/30 text-center transition duration-200 relative';
            } else {
                card.className = 'vol-card cursor-pointer p-4 rounded-2xl border-2 border-slate-800 bg-slate-900/40 text-center hover:border-slate-700 transition duration-200';
            }
        });

        updatePriceUI();
    }

    function submitOrder() {
        const btn = document.getElementById('btn-pay');
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin text-lg"></i> <span>Membuka AiYO QRIS...</span>';

        const currentPrices = priceMatrix[selectedTemp];
        const total = currentPrices[selectedVol];

        @auth
        const voucherInput = document.getElementById('voucher-code');
        const voucherCode = voucherInput ? voucherInput.value.trim() : '';
        @else
        const voucherCode = '';
        @endauth

        const userPhoneInput = document.getElementById('user-phone');
        const userPhone = userPhoneInput ? userPhoneInput.value.trim() : '';

        // POST ke endpoint Laravel OrderController untuk membuat transaksi & invoice AiYO
        fetch("{{ route('kiosk.order') }}", {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                kiosk_id:    kioskId,
                water_type:  selectedTemp,
                volume_ml:   selectedVol,
                voucher_code: voucherCode || undefined,
                user_email:  "{{ auth()->user()->email ?? '' }}",
                user_name:   "{{ auth()->user()->name ?? '' }}",
                user_phone:  userPhone || undefined,
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success && data.redirectUrl) {
                window.location.href = data.redirectUrl;
            } else {
                const errMsg = data.message || data.errors?.kiosk_id?.[0] || 'Gagal membuat pesanan.';
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-qrcode text-lg"></i> <span>Bayar dengan AiYO QRIS</span> <i class="fa-solid fa-arrow-right text-sm"></i>';
                const errBox = document.getElementById('order-error-box');
                if (errBox) { errBox.querySelector('span').textContent = errMsg; errBox.classList.remove('hidden'); }
                else { alert(errMsg); }
            }
        })
        .catch(err => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-qrcode text-lg"></i> <span>Bayar dengan AiYO QRIS</span> <i class="fa-solid fa-arrow-right text-sm"></i>';
            alert('Terjadi kesalahan jaringan. Silakan coba lagi.');
        });
    }

    // Initialize UI on load
    updatePriceUI();
</script>
@endsection
