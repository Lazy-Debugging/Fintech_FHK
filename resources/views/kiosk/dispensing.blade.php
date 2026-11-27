@extends('layouts.kiosk_layout')

@section('title', 'Ambil Air & Penuangan - Fresh Hydration Kios')

@section('content')
<div class="w-full max-w-2xl mx-auto my-auto py-2 sm:py-4 text-center space-y-5 sm:space-y-6">

    <!-- Top Badge -->
    <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-purple-950/80 border border-purple-800/80 text-purple-300 text-xs font-semibold animate-pulse">
        <i class="fa-solid fa-shield-virus text-purple-400"></i>
        <span>UV-C Purifier Chamber Aktif • Sterilisasi 99.9%</span>
    </div>

    <!-- KARTU 1: SCAN QR DI KAMERA KIOS (Tampil jika belum di-scan) -->
    <div id="qr-pickup-card" class="glass-panel rounded-2xl sm:rounded-3xl p-5 sm:p-8 space-y-4 sm:space-y-5 relative overflow-hidden border-2 border-cyan-500/50 shadow-2xl shadow-cyan-950/50 {{ in_array($transaksi->status, ['DISPENSING', 'COMPLETED']) ? 'hidden' : '' }}">
        <div class="flex items-center justify-between border-b border-slate-800 pb-3">
            <span class="inline-flex items-center gap-2 text-xs font-black uppercase tracking-wider text-cyan-400">
                <i class="fa-solid fa-camera text-base"></i> Scan di Kamera Kiosk
            </span>
            <span class="px-3 py-1 rounded-full bg-cyan-500/20 text-cyan-300 text-xs font-mono font-bold border border-cyan-500/30">
                {{ $transaksi->invoiceId }}
            </span>
        </div>

        <div class="space-y-2">
            <h3 class="text-xl sm:text-2xl font-black text-white tracking-tight">
                Arahkan QR ke Kamera Mesin Kios
            </h3>
            <p class="text-xs text-slate-400 max-w-md mx-auto">
                Pegang layar ini sekitar <strong class="text-cyan-300">8–15 cm</strong> di depan lensa kamera ESP32 hingga terdengar bunyi <strong class="text-white">bip 2x</strong>, lalu kran dispenser akan otomatis menuangkan air.
            </p>
        </div>

        <!-- QR Code Visual (High-Contrast & Wide Margin untuk Kamera ESP32) -->
        <div class="space-y-3">
            <div id="qrcode-pickup" class="relative p-4 bg-white rounded-2xl shadow-2xl border-4 border-slate-800 w-60 h-60 sm:w-64 sm:h-64 mx-auto flex items-center justify-center overflow-hidden">
                <img id="fallback-qr-img" src="https://api.qrserver.com/v1/create-qr-code/?size=260x260&margin=15&ecc=M&data={{ urlencode($transaksi->invoiceId) }}" 
                     alt="QR Code Pengambilan {{ $transaksi->invoiceId }}" 
                     class="w-full h-full object-contain">
            </div>
            <div class="text-center">
                <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-slate-800/80 border border-slate-700 text-[11px] font-mono text-cyan-300">
                    <i class="fa-solid fa-ticket text-cyan-400"></i>
                    <span>ID: {{ $transaksi->invoiceId }}</span>
                </div>
            </div>
        </div>

        <div class="pt-2 flex flex-col sm:flex-row items-center justify-center gap-3">
            <button id="btn-manual-dispense" onclick="triggerManualDispense()" 
                    class="w-full sm:w-auto px-6 py-2.5 rounded-xl bg-gradient-to-r from-cyan-500 to-blue-600 hover:from-cyan-400 text-white text-xs font-bold transition flex items-center justify-center gap-2 shadow-lg shadow-cyan-500/20">
                <i class="fa-solid fa-faucet-drip"></i>
                <span>Atau Tuang Otomatis Tanpa Scan</span>
            </button>
        </div>
    </div>

    <!-- KARTU 2: ANIMASI PENUANGAN AIR (Aktif saat DISPENSING) -->
    <div id="dispensing-card" class="glass-panel rounded-2xl sm:rounded-3xl p-4 sm:p-10 space-y-6 sm:space-y-8 relative overflow-hidden border border-purple-500/40 shadow-2xl shadow-purple-950/60 {{ !in_array($transaksi->status, ['DISPENSING', 'COMPLETED']) ? 'hidden' : '' }}">
        
        <!-- Glowing Ambient Lighting -->
        <div class="absolute inset-0 bg-gradient-to-b from-purple-500/10 via-cyan-500/5 to-transparent pointer-events-none"></div>

        <!-- Dispenser Nozzle & Animated Tumbler Graphic -->
        <div class="relative w-52 h-52 sm:w-64 sm:h-64 mx-auto flex flex-col items-center justify-between py-2">
            
            <!-- Dispenser Nozzle with UV Emitter -->
            <div class="w-20 h-8 rounded-b-xl bg-slate-800 border-2 border-slate-600 relative z-20 flex items-center justify-center shadow-lg">
                <div id="uv-emitter" class="w-12 h-3 rounded-full bg-purple-500 shadow-[0_0_20px_#a855f7] animate-pulse"></div>
            </div>

            <!-- Water Stream (Animated SVG / Div) -->
            <div id="water-stream" class="w-3 h-24 sm:h-32 bg-gradient-to-b from-purple-400 via-cyan-300 to-sky-400 rounded-full shadow-[0_0_15px_#38bdf8] opacity-90 transition-all duration-500 animate-pulse"></div>

            <!-- Tumbler / Cup Silhouette Graphic -->
            <div class="w-28 h-28 border-4 border-slate-600/80 rounded-b-3xl relative overflow-hidden bg-slate-900/60 shadow-inner flex items-end">
                <!-- Liquid Fill Indicator -->
                <div id="liquid-fill" class="w-full bg-gradient-to-t from-cyan-600 via-sky-400 to-cyan-300 transition-all duration-300" style="height: 15%;">
                    <div class="w-full h-1.5 bg-cyan-200/80 animate-pulse"></div>
                </div>
            </div>

        </div>

        <!-- Realtime Volume Counter & Status Text -->
        <div class="space-y-2">
            <div class="text-xs font-bold uppercase tracking-wider text-purple-400" id="step-indicator">
                Langkah: Mensterilkan Nozzle & Menuangkan Air...
            </div>
            
            <div class="text-3xl sm:text-5xl font-black text-white font-mono flex flex-wrap items-center justify-center gap-1">
                <span id="volume-counter">0</span>
                <span class="text-2xl text-slate-500">/ {{ $transaksi->volume_ml }} ml</span>
            </div>

            <p class="text-xs text-slate-400 max-w-md mx-auto" id="step-desc">
                Harap letakkan botol/tumbler Anda tepat di bawah nozzle dispenser. Jangan menarik botol sebelum proses selesai.
            </p>
        </div>

        <!-- Progress Bar -->
        <div class="w-full bg-slate-800/80 rounded-full h-3 overflow-hidden border border-slate-700/60 p-0.5">
            <div id="progress-bar" class="bg-gradient-to-r from-purple-500 via-cyan-400 to-emerald-400 h-full rounded-full transition-all duration-300" style="width: 10%;"></div>
        </div>

        <!-- Quick Hardware Complete Trigger (Demo / Testing Fallback) -->
        <div class="pt-2 border-t border-slate-800/80 flex items-center justify-between text-xs text-slate-500">
            <span class="flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-full bg-cyan-400 animate-ping"></span>
                <span>ESP32 Relays & Flow Sensor Active</span>
            </span>
            <button onclick="instantComplete()" class="text-cyan-400 hover:text-cyan-300 font-medium underline">
                Simulasikan Selesai Instan
            </button>
        </div>

    </div>

</div>
@endsection

@section('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
    const invoiceId = "{{ $transaksi->invoiceId }}";
    const targetVolume = {{ (int) $transaksi->volume_ml }};
    const waterType = "{{ $transaksi->water_type }}";

    // Render QR Code lokal resolusi tinggi dengan Quiet Zone putih yang tebal
    document.addEventListener("DOMContentLoaded", function() {
        const box = document.getElementById("qrcode-pickup");
        if (box && typeof QRCode !== 'undefined') {
            try {
                box.innerHTML = '';
                new QRCode(box, {
                    text: invoiceId,
                    width: 220,
                    height: 220,
                    colorDark: "#000000",
                    colorLight: "#ffffff",
                    correctLevel: QRCode.CorrectLevel.M
                });
            } catch(e) {
                console.warn("Fallback to image QR:", e);
            }
        }
    });

    let currentVolume = 0;
    const volumeEl = document.getElementById('volume-counter');
    const fillEl = document.getElementById('liquid-fill');
    const progressEl = document.getElementById('progress-bar');
    const stepIndEl = document.getElementById('step-indicator');
    const stepDescEl = document.getElementById('step-desc');
    const qrCard = document.getElementById('qr-pickup-card');
    const dispenseCard = document.getElementById('dispensing-card');

    // Deteksi base prefix secara dinamis (apakah berjalan di /fhk/ atau root)
    const basePath = window.location.pathname.includes('/fhk') ? '/fhk' : '';
    let isFinished = false;

    // Polling status riil dari ESP32 setiap 1.2 detik
    const pollInterval = setInterval(checkDispenseStatus, 1200);

    async function checkDispenseStatus() {
        if (isFinished) return;

        try {
            const res = await fetch(basePath + "/api/dispense-status/" + invoiceId);
            if (!res.ok) return;
            const data = await res.json();

            if (data.status === 'DISPENSING') {
                // Tampilkan card penuangan dan sembunyikan QR
                if (qrCard) qrCard.classList.add('hidden');
                if (dispenseCard) dispenseCard.classList.remove('hidden');

                stepIndEl.textContent = `Sedang Menuang Air ${waterType} (${targetVolume} mL)...`;
                stepDescEl.textContent = 'Aliran air sedang dituang oleh IoT Dispenser. Harap letakkan gelas tepat di bawah nozzle.';
                
                // Animasi visual bertahap jika data progress belum ada
                if (currentVolume < targetVolume * 0.9) {
                    currentVolume += Math.floor(targetVolume / 18);
                    updateVisuals(currentVolume);
                }
            } else if (data.status === 'COMPLETED' || data.isCompleted) {
                isFinished = true;
                clearInterval(pollInterval);
                finishFlow(data.receiptUrl);
            }
        } catch (e) {
            console.warn('[FHK] Gagal poll dispense status:', e);
        }
    }

    // Tombol alternatif: Tuang langsung tanpa perlu scan kamera
    async function triggerManualDispense() {
        const btn = document.getElementById('btn-manual-dispense');
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> <span>Mengaktifkan Dispenser...</span>';
        }

        try {
            const res = await fetch(basePath + "/kiosk/trigger-dispense/" + invoiceId, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            });
            const data = await res.json();
            if (data.success) {
                if (qrCard) qrCard.classList.add('hidden');
                if (dispenseCard) dispenseCard.classList.remove('hidden');
            } else {
                alert(data.message || 'Gagal memulai penuangan air.');
                if (btn) btn.disabled = false;
            }
        } catch (err) {
            // Coba fallback ke route api
            fetch(basePath + "/api/trigger-dispense/" + invoiceId, { method: 'POST' });
            if (qrCard) qrCard.classList.add('hidden');
            if (dispenseCard) dispenseCard.classList.remove('hidden');
        }
    }

    function updateVisuals(vol) {
        if (vol > targetVolume) vol = targetVolume;
        volumeEl.textContent = vol;
        const pct = Math.min(100, Math.round((vol / targetVolume) * 100));
        fillEl.style.height = pct + '%';
        progressEl.style.width = pct + '%';
    }

    function finishFlow(receiptUrl) {
        updateVisuals(targetVolume);
        stepIndEl.textContent = 'Pengisian Selesai! Menyiapkan Struk Digital...';
        stepIndEl.className = 'text-xs font-bold uppercase tracking-wider text-emerald-400';
        document.getElementById('water-stream').style.opacity = '0';

        let targetUrl = receiptUrl || (basePath + "/kiosk/receipt/" + invoiceId);
        if (basePath && !targetUrl.includes('/fhk/')) {
            targetUrl = targetUrl.replace('mesinbayar.com/', 'mesinbayar.com/fhk/');
        }

        setTimeout(() => {
            window.location.href = targetUrl;
        }, 1500);
    }

    // Tombol darurat manual jika ingin bypass simulasi instan
    async function instantComplete() {
        if (isFinished) return;
        isFinished = true;
        clearInterval(pollInterval);

        try {
            const res = await fetch(basePath + "/api/simulate-dispense-complete/" + invoiceId, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            });
            const data = await res.json();
            finishFlow(data.receiptUrl);
        } catch (e) {
            finishFlow(basePath + "/kiosk/receipt/" + invoiceId);
        }
    }
</script>
@endsection
