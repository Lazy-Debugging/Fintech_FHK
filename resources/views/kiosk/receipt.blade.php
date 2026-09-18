@extends('layouts.kiosk_layout')

@section('title', 'Struk Digital Pembayaran - Fresh Hydration Kios')

@section('content')
<div class="w-full max-w-lg mx-auto my-auto py-2 sm:py-4">

    <!-- Digital Receipt Card -->
    <div class="glass-panel rounded-2xl sm:rounded-3xl p-4 sm:p-8 space-y-5 sm:space-y-6 relative overflow-hidden border border-emerald-500/30 shadow-2xl shadow-emerald-950/40">
        
        <!-- Header Success Icon -->
        <div class="text-center space-y-2">
            <div class="w-16 h-16 rounded-full bg-emerald-500/20 border border-emerald-500/40 flex items-center justify-center text-emerald-400 text-3xl mx-auto shadow-lg shadow-emerald-500/30 animate-bounce">
                <i class="fa-solid fa-check"></i>
            </div>
            <h2 class="text-2xl font-black text-white">
                Terima Kasih, Hidrasi Selesai!
            </h2>
            <p class="text-xs text-slate-400">
                Air minum Anda telah higienis dan siap dinikmati.
            </p>
        </div>

        <!-- Receipt Body Details Box -->
        <div class="rounded-2xl bg-slate-900/90 border border-slate-800 p-5 space-y-4 font-mono text-xs">
            
            <div class="flex flex-col min-[420px]:flex-row min-[420px]:items-center justify-between gap-2 border-b border-slate-800 pb-3">
                <span class="text-slate-400">Status Transaksi:</span>
                <span class="px-2.5 py-0.5 rounded-full bg-emerald-500/20 text-emerald-300 font-bold border border-emerald-500/30">
                    LUNAS • COMPLETED
                </span>
            </div>

            <div class="space-y-2 text-slate-300">
                <div class="flex flex-col min-[420px]:flex-row min-[420px]:justify-between gap-1">
                    <span class="text-slate-500">ID Referensi:</span>
                    <span class="font-bold text-white">{{ $transaksi->referenceId }}</span>
                </div>
                <div class="flex flex-col min-[420px]:flex-row min-[420px]:justify-between gap-1">
                    <span class="text-slate-500">Nomor Invoice AiYO:</span>
                    <span class="text-slate-400">{{ substr($transaksi->invoiceId, 0, 16) }}...</span>
                </div>
                <div class="flex flex-col min-[420px]:flex-row min-[420px]:justify-between gap-1">
                    <span class="text-slate-500">Waktu Pembayaran:</span>
                    <span>{{ $transaksi->created_at->format('d M Y, H:i:s') }} WIB</span>
                </div>
                <div class="flex flex-col min-[420px]:flex-row min-[420px]:justify-between gap-1">
                    <span class="text-slate-500">Lokasi Kios:</span>
                    <span>{{ $transaksi->kiosk->name ?? 'FHK Stasiun Gambir' }}</span>
                </div>
            </div>

            <div class="border-t border-dashed border-slate-800 pt-3 space-y-2">
                <div class="flex flex-col min-[420px]:flex-row min-[420px]:justify-between min-[420px]:items-center gap-1 text-sm font-sans">
                    <span class="font-bold text-white">Air Minum {{ $transaksi->water_type }} ({{ $transaksi->volume_ml }} ml)</span>
                    <span class="font-black text-cyan-400">Rp {{ number_format($transaksi->payAmount, 0, ',', '.') }}</span>
                </div>
                <div class="flex items-center gap-1.5 text-[11px] text-purple-400 font-sans">
                    <i class="fa-solid fa-certificate"></i>
                    <span>Tersertifikasi Sterilisasi UV-C ESP32 99.9%</span>
                </div>
            </div>

            <div class="border-t border-slate-800 pt-3 flex flex-col min-[420px]:flex-row min-[420px]:justify-between min-[420px]:items-center gap-1">
                <span class="text-slate-400 font-sans font-bold">Total Pembayaran (AiYO QRIS):</span>
                <span class="text-base font-black text-white font-sans">
                    Rp {{ number_format($transaksi->payAmount, 0, ',', '.') }}
                </span>
            </div>

        </div>

        <!-- QR Code to Save Receipt to Mobile Smartphone -->
        <div class="p-4 rounded-2xl bg-slate-900/50 border border-slate-800 flex flex-col min-[420px]:flex-row items-center gap-4">
            <div class="p-2 bg-white rounded-xl shrink-0">
                <canvas id="receipt-qr" class="w-20 h-20"></canvas>
            </div>
            <div class="text-left space-y-1">
                <div class="text-xs font-bold text-white">Simpan Struk ke HP Anda</div>
                <p class="text-[11px] text-slate-400">
                    Arahkan kamera smartphone ke QR code ini untuk menyimpan e-receipt ini secara digital tanpa kertas.
                </p>
            </div>
        </div>

        <!-- Action Buttons & Auto Redirect -->
        <div class="space-y-3 pt-2">
            <a href="{{ route('kiosk.home') }}"
               class="w-full py-4 px-6 rounded-2xl bg-gradient-to-r from-cyan-500 to-blue-600 hover:from-cyan-400 hover:to-blue-500 text-slate-950 font-black text-sm shadow-xl shadow-cyan-500/25 transition active:scale-98 flex items-center justify-center gap-2">
                <i class="fa-solid fa-house"></i>
                <span>Selesai & Kembali ke Layar Utama</span>
            </a>

            <div class="text-center text-[11px] text-slate-500">
                Layar akan otomatis kembali ke menu utama dalam <span id="auto-return-timer" class="font-bold text-slate-400">30</span> detik.
            </div>
        </div>

    </div>

</div>
@endsection

@section('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrious/4.0.2/qrious.min.js"></script>
<script>
    // Generate QR for Receipt URL
    new QRious({
        element: document.getElementById('receipt-qr'),
        value: window.location.href,
        size: 100,
        level: 'M'
    });

    // Auto-Return to Home Screen Countdown
    let timeLeft = 30;
    const timerEl = document.getElementById('auto-return-timer');
    const autoInterval = setInterval(() => {
        timeLeft--;
        if (timerEl) timerEl.textContent = timeLeft;
        if (timeLeft <= 0) {
            clearInterval(autoInterval);
            window.location.href = "{{ route('kiosk.home') }}";
        }
    }, 1000);
</script>
@endsection
