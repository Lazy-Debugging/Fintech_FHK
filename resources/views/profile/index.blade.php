@extends('layouts.kiosk_layout')

@section('title', 'Profil Saya - Fresh Hydration Kios')

@section('content')
<div class="w-full max-w-3xl mx-auto space-y-5 sm:space-y-6 my-auto py-2">

    @if(session('success'))
        <div class="p-4 rounded-2xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm font-bold flex items-center gap-3 shadow-lg">
            <i class="fa-solid fa-circle-check text-lg"></i>
            <div>{{ session('success') }}</div>
        </div>
    @endif

    @if(session('error'))
        <div class="p-4 rounded-2xl bg-rose-500/10 border border-rose-500/30 text-rose-400 text-sm font-bold flex items-center gap-3 shadow-lg">
            <i class="fa-solid fa-triangle-exclamation text-lg"></i>
            <div>{{ session('error') }}</div>
        </div>
    @endif

    <!-- Profile Header Card -->
    <div class="glass-panel rounded-2xl sm:rounded-3xl p-4 sm:p-6 flex items-center gap-4">
        <div class="w-14 h-14 rounded-2xl bg-gradient-to-tr from-cyan-500 to-blue-600 flex items-center justify-center text-white text-2xl font-black shadow-lg shadow-cyan-500/30 shrink-0">
            {{ $user ? \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($user->name, 0, 1)) : 'T' }}
        </div>
        <div class="min-w-0">
            <h2 class="text-lg sm:text-xl font-black text-white truncate">{{ $user?->name ?? 'Pengunjung Tamu' }}</h2>
            <p class="text-xs text-slate-400 truncate">{{ $user?->email ?? 'Masuk dengan Google untuk menyimpan riwayat & memakai voucher.' }}</p>
        </div>
        @unless($user)
            <a href="{{ route('login') }}" class="ml-auto shrink-0 px-4 py-2 rounded-xl bg-gradient-to-r from-cyan-500 to-blue-600 text-slate-950 text-xs font-bold hover:from-cyan-400 hover:to-blue-500 transition">
                Masuk
            </a>
        @endunless
    </div>

    <!-- Riwayat Transaksi -->
    <div class="glass-panel rounded-2xl sm:rounded-3xl p-4 sm:p-6 space-y-4">
        <div class="flex items-center justify-between">
            <h3 class="text-sm font-bold uppercase tracking-wider text-slate-400 flex items-center gap-2">
                <i class="fa-solid fa-clock-rotate-left text-cyan-400"></i> Riwayat Transaksi
            </h3>
            <span class="text-xs text-slate-500">Klik 'Redeem' di Kios saat air siap</span>
        </div>

        @forelse($transactions as $tx)
            <div class="p-4 rounded-2xl bg-slate-900/60 border border-slate-800/80 space-y-3">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="text-base font-black text-white truncate">
                            {{ $tx->kiosk?->name ?? $tx->kiosk_id ?? 'Kios FHK' }}
                        </div>
                        <div class="text-xs text-cyan-400 font-semibold mt-0.5">
                            Refill {{ $tx->water_type ?? 'Air' }} {{ $tx->volume_ml }}ml
                        </div>
                        <div class="text-[11px] text-slate-500 font-mono mt-1">
                            ID: {{ $tx->invoiceId }} &middot; {{ $tx->created_at->format('d M Y, H:i') }}
                        </div>
                    </div>
                    
                    <div class="text-right shrink-0">
                        <div class="text-base font-black text-white">Rp {{ number_format($tx->payAmount, 0, ',', '.') }}</div>
                        
                        <!-- Status Badges -->
                        @if($tx->status === 'PENDING')
                            <span class="inline-block mt-1 text-[11px] font-extrabold px-2.5 py-0.5 rounded-full bg-amber-500/20 border border-amber-500/40 text-amber-300">
                                ⏳ BELUM DIBAYAR
                            </span>
                        @elseif(in_array($tx->status, ['PAID', 'AWAITING_KIOSK_SCAN']))
                            <span class="inline-block mt-1 text-[11px] font-extrabold px-2.5 py-0.5 rounded-full bg-emerald-500/20 border border-emerald-500/40 text-emerald-300">
                                ✅ SUDAH DIBAYAR
                            </span>
                        @elseif($tx->status === 'DISPENSING')
                            <span class="inline-block mt-1 text-[11px] font-extrabold px-2.5 py-0.5 rounded-full bg-cyan-500/20 border border-cyan-500/40 text-cyan-300 animate-pulse">
                                💧 SEDANG DITUANG
                            </span>
                        @elseif($tx->status === 'COMPLETED')
                            <span class="inline-block mt-1 text-[11px] font-extrabold px-2.5 py-0.5 rounded-full bg-blue-500/20 border border-blue-500/40 text-blue-300">
                                ✨ SELESAI
                            </span>
                        @else
                            <span class="inline-block mt-1 text-[11px] font-extrabold px-2.5 py-0.5 rounded-full bg-rose-500/20 border border-rose-500/40 text-rose-300">
                                ❌ {{ $tx->status }}
                            </span>
                        @endif
                    </div>
                </div>

                <!-- Tombol Aksi Berdasarkan Status -->
                <div class="pt-2 border-t border-slate-800/60 flex items-center justify-end gap-2 flex-wrap">
                    @if($tx->status === 'PENDING')
                        <!-- Tombol Batalkan -->
                        <form action="{{ route('orders.cancel', $tx->invoiceId) }}" method="POST" onsubmit="return confirm('Yakin ingin membatalkan transaksi ini?')">
                            @csrf
                            <button type="submit" class="px-3.5 py-1.5 rounded-xl bg-rose-500/10 hover:bg-rose-500/20 border border-rose-500/30 text-rose-300 text-xs font-bold transition flex items-center gap-1.5">
                                <i class="fa-solid fa-xmark"></i> Batalkan
                            </button>
                        </form>

                        <!-- Tombol Bayar Sekarang -->
                        @php
                            $payUrl = (!empty($tx->invoice_url) && str_contains($tx->invoice_url, 'aiyo.id'))
                                ? $tx->invoice_url
                                : route('kiosk.qris', ['invoiceId' => $tx->invoiceId]);
                        @endphp
                        <a href="{{ $payUrl }}" target="_blank" class="px-4 py-1.5 rounded-xl bg-gradient-to-r from-amber-500 to-orange-500 hover:from-amber-400 hover:to-orange-400 text-slate-950 text-xs font-extrabold shadow-lg shadow-amber-500/20 transition flex items-center gap-1.5">
                            <i class="fa-solid fa-qrcode"></i> Bayar Sekarang &rarr;
                        </a>
                    @elseif(in_array($tx->status, ['PAID', 'AWAITING_KIOSK_SCAN']))
                        <!-- Tombol Redeem di Kios (Buka Kamera Scan QR) -->
                        <button onclick="startQrRedeem('{{ $tx->invoiceId }}')" class="px-4 py-1.5 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-400 hover:to-teal-400 text-slate-950 text-xs font-extrabold shadow-lg shadow-emerald-500/30 transition flex items-center gap-1.5">
                            <i class="fa-solid fa-camera"></i> Scan QR Kios (Redeem)
                        </button>
                    @elseif($tx->status === 'COMPLETED')
                        <a href="{{ route('kiosk.receipt', ['invoiceId' => $tx->invoiceId]) }}" class="px-3.5 py-1.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-cyan-400 text-xs font-bold transition flex items-center gap-1.5">
                            <i class="fa-solid fa-receipt"></i> Lihat Struk
                        </a>
                    @endif
                </div>
            </div>
        @empty
            <div class="text-center py-8 text-slate-500 space-y-2">
                <i class="fa-solid fa-receipt text-3xl opacity-40"></i>
                <p class="text-xs">Belum ada riwayat transaksi.</p>
            </div>
        @endforelse

        @if($transactions->hasPages())
            <div class="pt-2">{{ $transactions->links() }}</div>
        @endif
    </div>

    <!-- Voucher -->
    <div class="glass-panel rounded-2xl sm:rounded-3xl p-4 sm:p-6 space-y-4">
        <h3 class="text-sm font-bold uppercase tracking-wider text-slate-400 flex items-center gap-2">
            <i class="fa-solid fa-ticket text-amber-400"></i> Voucher Tersedia
        </h3>

        @if($user)
            @forelse($vouchers as $voucher)
                <div class="flex items-center justify-between gap-3 p-3 rounded-xl bg-amber-950/20 border border-amber-800/40">
                    <div class="min-w-0">
                        <div class="text-sm font-bold text-amber-300 font-mono">{{ $voucher->code }}</div>
                        <div class="text-[11px] text-slate-400 truncate">{{ $voucher->name }}</div>
                    </div>
                    <div class="text-xs font-black text-white shrink-0">
                        {{ $voucher->discount_type === 'percent' ? $voucher->discount_value.'%' : 'Rp '.number_format($voucher->discount_value, 0, ',', '.') }}
                    </div>
                </div>
            @empty
                <p class="text-xs text-slate-500 text-center py-6">Belum ada voucher aktif saat ini.</p>
            @endforelse
        @else
            <p class="text-xs text-slate-500 text-center py-6">Masuk dengan Google untuk melihat dan memakai voucher.</p>
        @endif
    </div>

</div>

<!-- Modal Kamera QR Scanner -->
<div id="qrScannerModal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-md z-50 hidden flex items-center justify-center p-4">
    <div class="glass-panel w-full max-w-md rounded-3xl p-5 space-y-4 text-center border border-cyan-500/30 shadow-2xl">
        <div class="flex items-center justify-between border-b border-slate-800 pb-3">
            <h3 class="text-base font-black text-white flex items-center gap-2">
                <i class="fa-solid fa-camera text-cyan-400"></i> Scan QR Code Kios
            </h3>
            <button onclick="closeQrScanner()" class="w-8 h-8 rounded-full bg-slate-800 text-slate-400 hover:text-white flex items-center justify-center">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <p class="text-xs text-slate-300">
            Arahkan kamera HP Anda ke **QR Code Kios** yang tampil di layar antarmuka Kios untuk memulai penuangan air.
        </p>

        <!-- Preview Kamera -->
        <div class="relative w-full aspect-square rounded-2xl overflow-hidden bg-slate-900 border border-slate-800 flex items-center justify-center">
            <div id="qr-reader" class="w-full h-full"></div>
            <div id="scanner-loading" class="absolute inset-0 flex flex-col items-center justify-center bg-slate-950/80 text-cyan-400 gap-2">
                <i class="fa-solid fa-spinner fa-spin text-3xl"></i>
                <span class="text-xs font-bold text-slate-300">Membuka Kamera HP...</span>
            </div>
        </div>

        <div id="scan-status" class="text-xs font-bold text-slate-400">
            Status: Menunggu pemindaian...
        </div>

        <button onclick="closeQrScanner()" class="w-full py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-bold transition">
            Tutup Kamera
        </button>
    </div>
</div>

<!-- Library HTML5 QR Code Scanner -->
<script src="https://unpkg.com/html5-qrcode"></script>

<script>
let html5QrCode = null;
let currentRedeemInvoiceId = null;

function startQrRedeem(invoiceId) {
    currentRedeemInvoiceId = invoiceId;
    const modal = document.getElementById('qrScannerModal');
    const loading = document.getElementById('scanner-loading');
    const statusEl = document.getElementById('scan-status');
    
    modal.classList.remove('hidden');
    loading.classList.remove('hidden');
    statusEl.innerHTML = '<span class="text-slate-400">Menyiapkan kamera...</span>';

    if (html5QrCode) {
        html5QrCode.stop().catch(() => {}).then(() => initScanner());
    } else {
        initScanner();
    }
}

function initScanner() {
    html5QrCode = new Html5Qrcode("qr-reader");
    const config = { fps: 10, qrbox: { width: 250, height: 250 } };

    html5QrCode.start(
        { facingMode: "environment" },
        config,
        onScanSuccess,
        onScanFailure
    ).then(() => {
        document.getElementById('scanner-loading').classList.add('hidden');
        document.getElementById('scan-status').innerHTML = '<span class="text-cyan-400 font-bold">Kamera Siap! Scan QR di layar Kios.</span>';
    }).catch(err => {
        document.getElementById('scanner-loading').classList.add('hidden');
        document.getElementById('scan-status').innerHTML = '<span class="text-rose-400 font-bold">Gagal membuka kamera: ' + err + '</span>';
    });
}

function onScanSuccess(decodedText, decodedResult) {
    if (!currentRedeemInvoiceId) return;

    const statusEl = document.getElementById('scan-status');
    statusEl.innerHTML = '<span class="text-amber-400 font-bold"><i class="fa-solid fa-spinner fa-spin"></i> Memverifikasi QR Kios...</span>';

    // Matikan kamera setelah berhasil scan
    if (html5QrCode) {
        html5QrCode.stop().catch(() => {});
    }

    // Kirim data scan QR ke server
    fetch(`/orders/${currentRedeemInvoiceId}/redeem-scan`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json'
        },
        body: JSON.stringify({
            kiosk_qr: decodedText
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            statusEl.innerHTML = '<span class="text-emerald-400 font-black">🎉 ' + data.message + '</span>';
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            statusEl.innerHTML = '<span class="text-rose-400 font-bold">❌ ' + (data.message || 'Gagal memverifikasi QR Kios.') + '</span>';
        }
    })
    .catch(err => {
        statusEl.innerHTML = '<span class="text-rose-400 font-bold">❌ Kesalahan jaringan. Coba lagi.</span>';
    });
}

function onScanFailure(error) {
    // Abaikan kegagalan scan frame biasa
}

function closeQrScanner() {
    if (html5QrCode) {
        html5QrCode.stop().catch(() => {});
    }
    document.getElementById('qrScannerModal').classList.add('hidden');
}
</script>
@endsection
