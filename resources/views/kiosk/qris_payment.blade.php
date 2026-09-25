@extends('layouts.kiosk_layout')

@section('title', 'Pembayaran QRIS - Fresh Hydration Kios')

@php
    // Deteksi apakah qr_content adalah QRIS EMV string asli atau URL halaman AiYO
    $rawQr       = $transaksi->qr_content ?? null;
    $invoiceUrl  = $transaksi->invoice_url ?? null;

    // QRIS EMV string resmi selalu diawali "00020101" (format standar QRIS Indonesia / EMVCo)
    $isRealQris  = $rawQr && str_starts_with($rawQr, '0002');

    // Jika bukan QRIS string asli, gunakan invoiceURL AiYO untuk ditampilkan via iframe
    $aiYoPageUrl = $isRealQris ? null : ($invoiceUrl ?? $rawQr);

    // Pastikan URL invoice AiYO valid
    $hasAiyoPage = $aiYoPageUrl && filter_var($aiYoPageUrl, FILTER_VALIDATE_URL);
@endphp

@section('content')
<div class="w-full max-w-7xl mx-auto space-y-6 my-auto py-4 px-2 sm:px-4">

    <!-- Header Status Pembayaran -->
    <div class="glass-panel rounded-3xl p-6 text-center space-y-3 relative overflow-hidden">
        <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-cyan-500/10 border border-cyan-500/30 text-cyan-300 text-xs font-extrabold uppercase tracking-wider animate-pulse">
            <span class="w-2.5 h-2.5 rounded-full bg-cyan-400"></span>
            Menunggu Pembayaran QRIS
        </div>
        <h2 class="text-2xl sm:text-3xl font-black text-white tracking-tight">
            Scan QRIS untuk Pembayaran
        </h2>
        <p class="text-xs sm:text-sm text-slate-400 max-w-md mx-auto">
            Gunakan aplikasi e-Wallet (GoPay, OVO, Dana, ShopeePay) atau m-Banking Anda untuk melakukan pemindaian.
        </p>
    </div>

    <!-- Container Utama QRIS & Invoice Details -->
    <div class="grid grid-cols-1 md:grid-cols-12 gap-6 items-stretch">

        <!-- Sisi Kiri: AiYO Payment Page Panel (Diperbesar Full 8/12 Kolom) -->
        <div class="md:col-span-8 lg:col-span-8 glass-panel rounded-3xl p-6 flex flex-col items-center justify-center text-center space-y-4 border border-cyan-500/30 shadow-2xl relative">

            <div class="w-full flex items-center justify-between text-xs font-bold text-slate-400 border-b border-slate-800/80 pb-3">
                <div class="flex items-center gap-2">
                    <i class="fa-solid fa-qrcode text-cyan-400 text-base"></i>
                    <span class="text-white text-sm font-black">
                        @if($isRealQris)
                            QRIS Dinamis Resmi AiYO
                        @else
                            Halaman Pembayaran AiYO (Diperbesar)
                        @endif
                    </span>
                </div>
                @if($hasAiyoPage && !$isRealQris)
                <a href="{{ $aiYoPageUrl }}" target="_blank" rel="noopener"
                   class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg bg-cyan-500/10 hover:bg-cyan-500/20 border border-cyan-500/30 text-cyan-300 text-xs font-semibold transition">
                    <i class="fa-solid fa-up-right-from-square"></i> Tab Baru
                </a>
                @endif
            </div>

            @if($isRealQris)
                {{-- ============================================ --}}
                {{-- MODE A: AiYO mengembalikan QRIS EMV string asli --}}
                {{-- Render QR code langsung dari string QRIS tersebut --}}
                {{-- ============================================ --}}
                <div class="relative group p-6 bg-white rounded-3xl shadow-2xl shadow-cyan-500/20 border-4 border-slate-900 transition transform hover:scale-105 my-4">
                    <div id="qrcode-box" class="w-64 h-64 flex items-center justify-center rounded-lg bg-white p-1"></div>
                </div>

                <!-- Countdown Timer -->
                <div class="space-y-1">
                    <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Batas Waktu Pembayaran</div>
                    <div id="qris-timer" class="text-3xl font-black font-mono text-cyan-400">15:00</div>
                </div>

                <div class="text-xs text-slate-400 font-semibold pt-3 border-t border-slate-800/80 w-full">
                    Mendukung QRIS, GoPay, OVO, DANA, ShopeePay, BCA, Mandiri, BRI, BNI
                </div>

            @elseif($hasAiyoPage)
                {{-- ============================================ --}}
                {{-- MODE B: Embed halaman pembayaran AiYO via iframe (Tinggi 680px - Ukuran Full) --}}
                {{-- QR code yang tampil adalah QR resmi dari AiYO --}}
                {{-- ============================================ --}}
                <div class="w-full rounded-2xl overflow-hidden border-2 border-cyan-500/40 shadow-2xl shadow-cyan-500/10 relative" style="height: 680px;">
                    {{-- Loading placeholder --}}
                    <div id="iframe-loader" class="absolute inset-0 flex flex-col items-center justify-center bg-slate-900/95 z-10 gap-3">
                        <i class="fa-solid fa-circle-notch fa-spin text-4xl text-cyan-400"></i>
                        <div class="text-sm text-slate-300 font-bold">Memuat Halaman Pembayaran AiYO...</div>
                        <div class="text-xs text-slate-500">Mohon tunggu sebentar</div>
                    </div>
                    <iframe
                        id="aiyo-payment-iframe"
                        src="{{ $aiYoPageUrl }}"
                        class="w-full h-full border-0 bg-white"
                        allow="payment"
                        sandbox="allow-scripts allow-same-origin allow-forms allow-popups allow-top-navigation"
                        onload="document.getElementById('iframe-loader').style.display='none';"
                        title="Halaman Pembayaran AiYO QRIS"
                    ></iframe>
                </div>

                <div class="text-xs text-slate-400 font-semibold pt-1 border-t border-slate-800/80 w-full flex items-center justify-center gap-2">
                    <i class="fa-solid fa-shield-halved text-emerald-400"></i>
                    Pembayaran terenskripsi aman melalui Gateway AiYO Resmi
                </div>

            @else
                {{-- ============================================ --}}
                {{-- MODE C: Fallback — tidak ada data QR sama sekali --}}
                {{-- ============================================ --}}
                <div class="w-64 h-64 flex flex-col items-center justify-center rounded-2xl bg-slate-900/80 border-2 border-rose-500/30 text-rose-400 gap-3 my-6">
                    <i class="fa-solid fa-triangle-exclamation text-4xl"></i>
                    <div class="text-xs font-semibold text-rose-300 text-center px-4">QR tidak tersedia. Silakan buat pesanan baru.</div>
                </div>
                <a href="{{ route('kiosk.home') }}" class="text-xs text-cyan-400 hover:underline">
                    ← Kembali ke halaman kios
                </a>
            @endif

        </div>

        <!-- Sisi Kanan: Detail Rincian Invoice (Sidebar 4/12 Kolom) -->
        <div class="md:col-span-4 lg:col-span-4 glass-panel rounded-3xl p-6 flex flex-col justify-between space-y-6 border border-slate-800">

            <div class="space-y-5">
                <h3 class="text-sm font-bold uppercase tracking-wider text-slate-400 flex items-center gap-2 border-b border-slate-800 pb-3">
                    <i class="fa-solid fa-receipt text-cyan-400"></i> Rincian Tagihan Invoice
                </h3>

                <!-- Data Pelanggan -->
                <div class="space-y-3.5 text-xs">
                    <div class="flex justify-between items-center border-b border-slate-800/50 pb-2">
                        <span class="text-slate-400 font-semibold">Nama Pelanggan:</span>
                        <span class="font-black text-white text-right max-w-[160px] truncate">{{ $transaksi->userName }}</span>
                    </div>

                    <div class="flex justify-between items-center border-b border-slate-800/50 pb-2">
                        <span class="text-slate-400 font-semibold">Email:</span>
                        <span class="font-bold text-slate-300 text-right max-w-[160px] truncate">{{ $transaksi->userEmail }}</span>
                    </div>

                    <div class="flex justify-between items-center border-b border-slate-800/50 pb-2">
                        <span class="text-slate-400 font-semibold">No. Telepon:</span>
                        <span class="font-bold text-slate-300 font-mono">{{ $transaksi->userPhone }}</span>
                    </div>

                    <div class="flex justify-between items-center border-b border-slate-800/50 pb-2">
                        <span class="text-slate-400 font-semibold">Invoice ID:</span>
                        <span class="font-bold font-mono text-cyan-400 text-right max-w-[160px] truncate">{{ $transaksi->invoiceId }}</span>
                    </div>

                    <div class="flex justify-between items-center border-b border-slate-800/50 pb-2">
                        <span class="text-slate-400 font-semibold">Pesanan Air:</span>
                        <span class="font-bold text-emerald-400">Refill {{ $transaksi->water_type }} {{ $transaksi->volume_ml }}ml</span>
                    </div>

                    <div class="flex justify-between items-center pb-1">
                        <span class="text-slate-400 font-semibold">Stasiun Kios:</span>
                        <span class="font-bold text-slate-300">{{ $transaksi->kiosk?->name ?? 'FHK Kiosk' }}</span>
                    </div>
                </div>

                <!-- Total Tagihan -->
                <div class="p-5 rounded-2xl bg-gradient-to-br from-slate-900 via-slate-900/90 to-cyan-950/40 border border-cyan-500/30 flex flex-col justify-between items-start gap-1 shadow-lg mt-2">
                    <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Total Tagihan Pembayaran</span>
                    <span class="text-3xl font-black text-cyan-400 tracking-tight">Rp {{ number_format($transaksi->payAmount, 0, ',', '.') }}</span>
                </div>

                @if($hasAiyoPage && !$isRealQris)
                <div class="p-3.5 rounded-xl bg-blue-500/10 border border-blue-500/30 text-blue-300 text-xs leading-relaxed space-y-1">
                    <div class="font-bold text-blue-400 flex items-center gap-1.5">
                        <i class="fa-solid fa-circle-info"></i> Petunjuk Pembayaran:
                    </div>
                    <p class="text-slate-300 text-[11px]">
                        Scan QR di dalam layar AiYO (sebelah kiri) menggunakan e-wallet (GoPay, OVO, DANA, ShopeePay) atau m-Banking Anda.
                    </p>
                </div>
                @endif
            </div>

            <!-- Area Status Realtime -->
            <div class="space-y-3 pt-2">
                <div id="payment-status-box" class="p-3.5 rounded-xl bg-slate-900/80 border border-slate-800 text-center text-xs text-slate-400 font-semibold flex items-center justify-center gap-2.5">
                    <i class="fa-solid fa-circle-notch fa-spin text-cyan-400 text-sm"></i> <span>Memeriksa status pembayaran otomatis...</span>
                </div>

                <button type="button" onclick="manualCheckPayment(this)" id="btn-manual-check" class="w-full py-3 px-4 rounded-xl bg-gradient-to-r from-cyan-500 to-blue-600 hover:from-cyan-400 hover:to-blue-500 text-slate-950 font-black text-xs shadow-lg shadow-cyan-500/20 transition flex items-center justify-center gap-2 cursor-pointer">
                    <i class="fa-solid fa-arrows-rotate"></i>
                    <span>Saya Sudah Bayar (Periksa Status)</span>
                </button>

                @if(app()->environment(['local', 'testing']))
                <button onclick="simulateDemoSuccess()" class="w-full py-2.5 rounded-xl bg-gradient-to-r from-emerald-500/20 to-teal-500/20 hover:from-emerald-500/30 hover:to-teal-500/30 border border-emerald-500/40 text-emerald-300 text-xs font-bold transition flex items-center justify-center gap-2">
                    <i class="fa-solid fa-vial"></i> Simulasi Pembayaran Lunas (Demo Test)
                </button>
                @endif
            </div>

        </div>
    </div>

</div>

<!-- Success Overlay Modal -->
<div id="successOverlay" class="fixed inset-0 bg-slate-950/90 backdrop-blur-lg z-50 hidden flex flex-col items-center justify-center p-6 text-center space-y-4">
    <div class="w-20 h-20 rounded-full bg-emerald-500/20 border-2 border-emerald-500 text-emerald-400 flex items-center justify-center text-4xl shadow-2xl shadow-emerald-500/40 animate-bounce">
        <i class="fa-solid fa-check"></i>
    </div>
    <h2 class="text-2xl sm:text-3xl font-black text-white">Pembayaran Berhasil!</h2>
    <p class="text-xs sm:text-sm text-slate-300 max-w-sm">
        Pembayaran QRIS Anda telah dikonfirmasi. Mengalihkan Anda secara otomatis...
    </p>
    <div class="text-cyan-400 text-xs font-mono font-bold">
        <i class="fa-solid fa-spinner fa-spin"></i> Memuat Halaman...
    </div>
</div>
@endsection

@section('scripts')
@if($isRealQris)
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
@endif
<script>
    const invoiceId     = "{{ $transaksi->invoiceId }}";
    const rawQrContent  = @json($transaksi->qr_content ?? $transaksi->invoice_url ?? $transaksi->invoiceId);
    const isRealQris    = {{ $isRealQris ? 'true' : 'false' }};
    // url() Laravel otomatis menghasilkan URL yang benar dengan prefix subfolder (e.g. /fhk/) di live server
    const checkStatusBaseUrl = "{{ url('api/kiosk/payment-status') }}/";
    let timeLeft        = 900; // 15 menit
    let isRedirecting   = false;

    // ─── Render QR code lokal (hanya jika AiYO mengembalikan QRIS EMV string asli) ───
    if (isRealQris) {
        document.addEventListener("DOMContentLoaded", function() {
            const box = document.getElementById("qrcode-box");
            if (box && rawQrContent) {
                try {
                    if (typeof QRCode !== 'undefined') {
                        new QRCode(box, {
                            text: rawQrContent,
                            width: 216,
                            height: 216,
                            colorDark  : "#0a0f1d",
                            colorLight : "#ffffff",
                            correctLevel: QRCode.CorrectLevel.H  // High error correction untuk QRIS
                        });
                    } else {
                        box.innerHTML = `<img src="https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=${encodeURIComponent(rawQrContent)}" class="w-full h-full object-contain rounded-lg">`;
                    }
                } catch(e) {
                    box.innerHTML = `<img src="https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=${encodeURIComponent(rawQrContent)}" class="w-full h-full object-contain rounded-lg">`;
                }
            }
        });

        // Countdown timer (hanya untuk mode QRIS EMV langsung)
        const timerInterval = setInterval(() => {
            timeLeft--;
            const minutes = Math.floor(timeLeft / 60);
            const seconds = timeLeft % 60;
            const el = document.getElementById('qris-timer');
            if (el) el.textContent = `${String(minutes).padStart(2,'0')}:${String(seconds).padStart(2,'0')}`;
            if (timeLeft <= 0) {
                clearInterval(timerInterval);
                const statusBox = document.getElementById('payment-status-box');
                if (statusBox) statusBox.innerHTML = '<span class="text-rose-400 font-bold">Waktu Pembayaran Habis. Silakan buat pesanan baru.</span>';
            }
        }, 1000);
    }

    // ─── Auto-polling status pembayaran setiap 2.5 detik ───
    const pollInterval = setInterval(checkStatus, 2500);

    function checkStatus() {
        if (isRedirecting) return;

        const url = `${checkStatusBaseUrl}${encodeURIComponent(invoiceId)}`;
        fetch(url)
            .then(res => {
                if (!res.ok) {
                    console.warn('[FHK] checkStatus HTTP ' + res.status + ' untuk URL: ' + url);
                    return null;
                }
                return res.json();
            })
            .then(data => {
                if (!data) return;
                if (data.isPaid && !isRedirecting) {
                    isRedirecting = true;
                    clearInterval(pollInterval);

                    const statusBox = document.getElementById('payment-status-box');
                    if (statusBox) {
                        statusBox.innerHTML = '<span class="text-emerald-400 font-bold flex items-center justify-center gap-2"><i class="fa-solid fa-circle-check"></i> Pembayaran Berhasil Dikonfirmasi! Mengalihkan...</span>';
                    }

                    // Tampilkan overlay sukses
                    const overlay = document.getElementById('successOverlay');
                    if (overlay) overlay.classList.remove('hidden');

                    // Redirect ke halaman berikutnya
                    setTimeout(() => {
                        window.location.href = data.nextActionUrl || "{{ route('profile') }}";
                    }, 1200);
                } else if (data.status === 'EXPIRED' || data.status === 'CANCELLED' || data.status === 'FAILED') {
                    clearInterval(pollInterval);
                    const statusBox = document.getElementById('payment-status-box');
                    if (statusBox) statusBox.innerHTML = `<span class="text-rose-400 font-bold">Transaksi ${data.status === 'EXPIRED' ? 'kedaluwarsa' : 'dibatalkan'}. Silakan buat pesanan baru.</span>`;
                }
            })
            .catch(err => console.warn('[FHK] checkStatus error:', err));
    }

    // ─── Manual Check Status Pembayaran ───
    function manualCheckPayment(btn) {
        if (isRedirecting) return;
        const originalText = btn ? btn.innerHTML : '';
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Memeriksa status...';
        }

        const url = `${checkStatusBaseUrl}${encodeURIComponent(invoiceId)}`;
        fetch(url)
            .then(res => res.json())
            .then(data => {
                if (data && data.isPaid) {
                    isRedirecting = true;
                    clearInterval(pollInterval);
                    const statusBox = document.getElementById('payment-status-box');
                    if (statusBox) {
                        statusBox.innerHTML = '<span class="text-emerald-400 font-bold flex items-center justify-center gap-2"><i class="fa-solid fa-circle-check"></i> Pembayaran Berhasil Dikonfirmasi! Mengalihkan...</span>';
                    }
                    const overlay = document.getElementById('successOverlay');
                    if (overlay) overlay.classList.remove('hidden');
                    setTimeout(() => {
                        window.location.href = data.nextActionUrl || "{{ route('profile') }}";
                    }, 1000);
                } else {
                    alert('Pembayaran belum terkonfirmasi oleh Gateway AiYO. Jika baru saja transfer, mohon tunggu 5-10 detik lalu coba klik kembali.');
                    if (btn) {
                        btn.disabled = false;
                        btn.innerHTML = originalText;
                    }
                }
            })
            .catch(err => {
                console.warn('[FHK] manualCheck error:', err);
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = originalText;
                }
            });
    }

    // ─── Demo Simulasi Pembayaran ───
    function simulateDemoSuccess() {
        fetch(`/orders/${invoiceId}/redeem-scan`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json'
            },
            body: JSON.stringify({ kiosk_qr: 'FHK-JAKARTA-01' })
        })
        .then(() => checkStatus())
        .catch(() => checkStatus());
    }
</script>
@endsection
