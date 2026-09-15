@extends('layouts.kiosk_layout')

@section('title', 'Sedang Menuang Air - Fresh Hydration Kios')

@section('content')
<div class="w-full max-w-2xl mx-auto my-auto py-4 text-center space-y-8">

    <!-- Top Badge -->
    <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-purple-950/80 border border-purple-800/80 text-purple-300 text-xs font-semibold animate-pulse">
        <i class="fa-solid fa-shield-virus text-purple-400"></i>
        <span>UV-C Purifier Chamber Aktif • Sterilisasi 99.9%</span>
    </div>

    <!-- Main Dispensing Animation Box -->
    <div class="glass-panel rounded-3xl p-8 sm:p-10 space-y-8 relative overflow-hidden border border-purple-500/40 shadow-2xl shadow-purple-950/60">
        
        <!-- Glowing Ambient Lighting -->
        <div class="absolute inset-0 bg-gradient-to-b from-purple-500/10 via-cyan-500/5 to-transparent pointer-events-none"></div>

        <!-- Dispenser Nozzle & Animated Tumbler Graphic -->
        <div class="relative w-64 h-64 mx-auto flex flex-col items-center justify-between py-2">
            
            <!-- Dispenser Nozzle with UV Emitter -->
            <div class="w-20 h-8 rounded-b-xl bg-slate-800 border-2 border-slate-600 relative z-20 flex items-center justify-center shadow-lg">
                <div id="uv-emitter" class="w-12 h-3 rounded-full bg-purple-500 shadow-[0_0_20px_#a855f7] animate-pulse"></div>
            </div>

            <!-- Water Stream (Animated SVG / Div) -->
            <div id="water-stream" class="w-3 h-32 bg-gradient-to-b from-purple-400 via-cyan-300 to-sky-400 rounded-full shadow-[0_0_15px_#38bdf8] opacity-90 transition-all duration-500 animate-pulse"></div>

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
                Langkah 1: Mensterilkan Nozzle dengan Sinar UV-C...
            </div>
            
            <div class="text-4xl sm:text-5xl font-black text-white font-mono flex items-center justify-center gap-1">
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
                <span>ESP32 Relays & Flow Sensor Streaming</span>
            </span>
            <button onclick="instantComplete()" class="text-cyan-400 hover:text-cyan-300 font-medium underline">
                Simulasikan Selesai Instan
            </button>
        </div>

    </div>

</div>
@endsection

@section('scripts')
<script>
    const invoiceId = "{{ $transaksi->invoiceId }}";
    const targetVolume = {{ (int) $transaksi->volume_ml }};
    const waterType = "{{ $transaksi->water_type }}";

    let currentVolume = 0;
    const volumeEl = document.getElementById('volume-counter');
    const fillEl = document.getElementById('liquid-fill');
    const progressEl = document.getElementById('progress-bar');
    const stepIndEl = document.getElementById('step-indicator');
    const stepDescEl = document.getElementById('step-desc');

    // Animasi Dispenser Bertahap
    // 1. Pre-UV Sterilization (3 detik)
    setTimeout(() => {
        stepIndEl.textContent = `Langkah 2: Membuka Katup Solenoid Air ${waterType}...`;
        stepDescEl.textContent = 'Aliran air sedang dituang secara presisi melalui sensor flow meter.';

        // 2. Simulasi Aliran Air Mengisi Tumbler
        const pourInterval = setInterval(() => {
            currentVolume += Math.floor(targetVolume / 30);
            if (currentVolume >= targetVolume) {
                currentVolume = targetVolume;
                clearInterval(pourInterval);

                // 3. Post-UV Sterilization
                stepIndEl.textContent = 'Langkah 3: Sterilisasi Akhir Nozzle UV...';
                stepDescEl.textContent = 'Memastikan nozzle bebas droplet dan higienis untuk pengguna berikutnya.';
                document.getElementById('water-stream').style.opacity = '0';

                setTimeout(() => {
                    completeDispense();
                }, 1800);
            }

            volumeEl.textContent = currentVolume;
            const pct = Math.min(100, Math.round((currentVolume / targetVolume) * 100));
            fillEl.style.height = pct + '%';
            progressEl.style.width = pct + '%';
        }, 120);

    }, 2500);

    async function completeDispense() {
        stepIndEl.textContent = 'Pengisian Selesai! Menyiapkan Struk Digital...';
        stepIndEl.className = 'text-xs font-bold uppercase tracking-wider text-emerald-400';

        try {
            const res = await fetch(`/api/simulate-dispense-complete/${invoiceId}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            });
            const data = await res.json();
            if (data.receiptUrl) {
                setTimeout(() => {
                    window.location.href = data.receiptUrl;
                }, 1000);
            }
        } catch (e) {
            window.location.href = `/kiosk/receipt/${invoiceId}`;
        }
    }

    function instantComplete() {
        currentVolume = targetVolume;
        volumeEl.textContent = currentVolume;
        fillEl.style.height = '100%';
        progressEl.style.width = '100%';
        completeDispense();
    }
</script>
@endsection
