@extends('layouts.kiosk_layout')

@section('title', 'Scan AiYO QRIS - Fresh Hydration Kios')

@section('content')
<div class="w-full max-w-xl mx-auto my-auto py-4">

    <div class="glass-panel rounded-3xl p-6 sm:p-8 space-y-6 relative overflow-hidden border border-cyan-500/30 shadow-2xl shadow-cyan-950/50">
        
        <!-- Top Back Navigation & Invoice Info -->
        <div class="flex items-center justify-between border-b border-slate-800 pb-4">
            <a href="{{ route('kiosk.home') }}" class="px-3 py-1.5 rounded-xl bg-slate-800/80 hover:bg-slate-700 text-slate-300 text-xs font-semibold flex items-center gap-1.5 transition">
                <i class="fa-solid fa-arrow-left"></i>
                <span>Batal / Kembali</span>
            </a>
            <div class="text-right">
                <span class="text-[10px] text-slate-400 block font-mono">REF: {{ $transaksi->referenceId }}</span>
                <span class="text-xs font-bold text-cyan-400">AiYO Dynamic QRIS</span>
            </div>
        </div>

        <!-- Order Summary Header -->
        <div class="text-center space-y-1">
            <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-cyan-950/80 border border-cyan-800 text-cyan-300 text-xs font-semibold">
                <i class="fa-solid {{ $transaksi->water_type === 'COLD' ? 'fa-snowflake' : 'fa-droplet' }}"></i>
                <span>Air Minum {{ $transaksi->water_type }} • {{ $transaksi->volume_ml }} ml</span>
            </div>
            <div class="text-3xl font-black text-white">
                Rp {{ number_format($transaksi->payAmount, 0, ',', '.') }}
            </div>
            <p class="text-xs text-slate-400">
                Pindai kode QRIS di bawah menggunakan BCA, Mandiri, GoPay, OVO, ShopeePay, atau DANA.
            </p>
        </div>

        <!-- QR Code Dynamic Container -->
        <div class="flex flex-col items-center justify-center p-6 rounded-2xl bg-white text-slate-900 shadow-inner relative group">
            
            <!-- QRIS Brand Banner Header -->
            <div class="w-full flex items-center justify-between mb-3 px-1 border-b border-slate-200 pb-2">
                <div class="flex items-center gap-1.5">
                    <span class="font-black text-xs tracking-widest text-red-600">QRIS</span>
                    <span class="text-[10px] text-slate-500 font-medium">Standar Pembayaran Nasional</span>
                </div>
                <div class="text-[10px] font-bold text-cyan-600 bg-cyan-50 px-2 py-0.5 rounded">
                    AiYO Pay
                </div>
            </div>

            <!-- Canvas QR Code -->
            <div id="qrcode-wrapper" class="p-2 bg-white rounded-xl flex items-center justify-center min-h-[220px]">
                <canvas id="qrcode-canvas" class="w-56 h-56"></canvas>
            </div>

            <!-- Pulse Radar Animation Bar -->
            <div class="w-full mt-3 pt-2 border-t border-slate-100 flex items-center justify-between text-[11px] text-slate-500">
                <span class="flex items-center gap-1.5">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-ping"></span>
                    <span>Menunggu scan pembayaran...</span>
                </span>
                <span id="countdown-timer" class="font-mono font-bold text-slate-700">14:59</span>
            </div>
        </div>

        <!-- Polling & Simulation Status -->
        <div class="space-y-3 pt-2">
            <div class="flex items-center justify-center gap-2 text-xs text-slate-400">
                <i class="fa-solid fa-arrows-rotate fa-spin text-cyan-400"></i>
                <span id="poll-status-text">Memeriksa status pembayaran otomatis setiap 2 detik</span>
            </div>

            <!-- Quick Simulation Button for Demo / Examiner -->
            <div class="p-3.5 rounded-xl bg-slate-900/80 border border-slate-800 text-center space-y-2">
                <div class="text-[11px] text-slate-400">
                    <i class="fa-solid fa-flask-vial text-amber-400 mr-1"></i>
                    <strong>Fitur Pengujian Cepat:</strong> Simulasikan pembayaran tanpa memotong saldo bank nyata
                </div>
                <button onclick="simulatePayment()" id="btn-simulate"
                        class="w-full py-2.5 px-4 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white font-bold text-xs shadow-lg shadow-emerald-950/40 transition active:scale-98 flex items-center justify-center gap-2">
                    <i class="fa-solid fa-circle-check"></i>
                    <span>Simulasikan Pembayaran Sukses (Testing Mode)</span>
                </button>
            </div>
        </div>

    </div>

</div>
@endsection

@section('scripts')
<!-- QRious Library for Crisp QR Rendering -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrious/4.0.2/qrious.min.js"></script>

<script>
    const invoiceId = "{{ $transaksi->invoiceId }}";
    const qrData = "{{ $transaksi->qr_content ?? $transaksi->invoice_url ?? $transaksi->referenceId }}";

    // Inisialisasi Canvas QR Code
    const qr = new QRious({
        element: document.getElementById('qrcode-canvas'),
        value: qrData,
        size: 240,
        level: 'M'
    });

    // Countdown Timer 15 menit
    let secondsLeft = 15 * 60;
    const timerEl = document.getElementById('countdown-timer');

    const countdownInterval = setInterval(() => {
        secondsLeft--;
        if (secondsLeft <= 0) {
            clearInterval(countdownInterval);
            clearInterval(pollInterval);
            alert('Waktu pembayaran telah habis. Anda akan dialihkan ke layar utama.');
            window.location.href = "{{ route('kiosk.home') }}";
            return;
        }

        const mins = Math.floor(secondsLeft / 60);
        const secs = secondsLeft % 60;
        timerEl.textContent = `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
    }, 1000);

    // Auto Polling Status Pembayaran AiYO
    let isPolling = true;
    const pollInterval = setInterval(async () => {
        if (!isPolling) return;

        try {
            const res = await fetch(`/api/kiosk/payment-status/${invoiceId}`);
            const data = await res.json();

            if (data.success && data.isPaid) {
                isPolling = false;
                clearInterval(pollInterval);
                clearInterval(countdownInterval);

                document.getElementById('poll-status-text').innerHTML = 
                    '<span class="text-emerald-400 font-bold"><i class="fa-solid fa-check-circle"></i> Pembayaran Terkonfirmasi! Mengaktifkan Dispenser UV...</span>';

                setTimeout(() => {
                    window.location.href = data.nextActionUrl || `/kiosk/dispensing/${invoiceId}`;
                }, 1000);
            }
        } catch (e) {
            console.warn('Gagal polling status:', e);
        }
    }, 2500);

    // Fungsi Simulasi Pembayaran Cepat untuk Pengujian
    async function simulatePayment() {
        const btn = document.getElementById('btn-simulate');
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> <span>Mengonfirmasi...</span>';

        try {
            const res = await fetch(`/api/kiosk/simulate-paid/${invoiceId}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            });
            const data = await res.json();

            if (data.success) {
                isPolling = false;
                clearInterval(pollInterval);
                clearInterval(countdownInterval);
                window.location.href = data.nextActionUrl || `/kiosk/dispensing/${invoiceId}`;
            } else {
                alert('Gagal simulasi pembayaran');
                btn.disabled = false;
            }
        } catch (e) {
            alert('Terjadi kesalahan');
            btn.disabled = false;
        }
    }
</script>
@endsection
