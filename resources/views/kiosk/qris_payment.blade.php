@extends('layouts.kiosk_layout')

@section('title', 'Pembayaran AiYO Gateway - Fresh Hydration Kios')

@section('content')
@if(!empty($transaksi->invoice_url) && str_contains($transaksi->invoice_url, 'aiyo.id'))
<script>
    window.location.replace("{{ $transaksi->invoice_url }}");
</script>
@endif
<div class="w-full max-w-2xl mx-auto my-auto py-2 sm:py-4">

    <div class="glass-panel rounded-2xl sm:rounded-3xl p-4 sm:p-6 space-y-4 relative overflow-hidden border border-cyan-500/30 shadow-2xl shadow-cyan-950/50">
        
        <!-- Top Back Navigation & Invoice Info -->
        <div class="flex items-center justify-between border-b border-slate-800 pb-3">
            <a href="{{ route('kiosk.home') }}" class="px-3 py-1.5 rounded-xl bg-slate-800/80 hover:bg-slate-700 text-slate-300 text-xs font-semibold flex items-center gap-1.5 transition">
                <i class="fa-solid fa-arrow-left"></i>
                <span>Kembali ke Kios</span>
            </a>
            <div class="text-right">
                <span class="text-[10px] text-slate-400 block font-mono">REF: {{ $transaksi->referenceId }}</span>
                <span class="text-xs font-bold text-cyan-400 flex items-center gap-1 justify-end">
                    <i class="fa-solid fa-shield-halved text-emerald-400"></i>
                    <span>AiYO Official Gateway</span>
                </span>
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
                Tagihan resmi diterbitkan langsung oleh AiYO Payment Gateway.
            </p>
        </div>

        @if(!empty($transaksi->invoice_url) && str_contains($transaksi->invoice_url, 'aiyo.id'))
        <!-- Official AiYO Gateway Iframe Embed -->
        <div class="relative w-full rounded-2xl overflow-hidden bg-white shadow-2xl border border-slate-700" style="min-height: 650px;">
            <iframe id="aiyo-iframe"
                    src="{{ $transaksi->invoice_url }}" 
                    class="w-full h-[650px] border-0" 
                    title="AiYO Bills Invoice Official Gateway"
                    allow="payment; camera">
            </iframe>
        </div>

        <!-- Action Button to Open Fullscreen AiYO -->
        <div class="text-center space-y-2 pt-2">
            <a href="{{ $transaksi->invoice_url }}" target="_blank"
               class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-6 py-3 rounded-xl bg-gradient-to-r from-cyan-600 to-blue-600 hover:from-cyan-500 hover:to-blue-500 text-white font-bold text-sm shadow-lg shadow-cyan-950/40 transition active:scale-98">
                <i class="fa-solid fa-arrow-up-right-from-square"></i>
                <span>Buka Layar Penuh AiYO &rarr;</span>
            </a>
            <div class="flex items-center justify-center gap-2 text-xs text-slate-400">
                <i class="fa-solid fa-arrows-rotate fa-spin text-cyan-400"></i>
                <span id="poll-status-text">Memeriksa konfirmasi pembayaran dari AiYO otomatis...</span>
            </div>
        </div>
        @else
        <div class="text-center py-8 space-y-4">
            <div class="w-16 h-16 rounded-full bg-amber-500/20 text-amber-400 flex items-center justify-center mx-auto text-2xl">
                <i class="fa-solid fa-triangle-exclamation"></i>
            </div>
            <h3 class="text-lg font-bold text-white">Menghubungkan ke Gateway AiYO...</h3>
            <p class="text-xs text-slate-400">Sedang mengalihkan ke server pembayaran resmi.</p>
            <a href="{{ route('kiosk.home') }}" class="inline-block px-4 py-2 bg-slate-800 rounded-xl text-xs text-slate-300">Kembali</a>
        </div>
        @endif

    </div>

</div>
@endsection

@section('scripts')
<script>
    const invoiceId = "{{ $transaksi->invoiceId }}";
    const invoiceUrl = @json($transaksi->invoice_url);

    // Auto Polling Status Pembayaran AiYO ke server Kios
    let isPolling = true;
    const pollInterval = setInterval(async () => {
        if (!isPolling) return;

        try {
            const res = await fetch(`{{ url('api/kiosk/payment-status') }}/${invoiceId}`);
            const data = await res.json();

            if (data.success && data.isPaid) {
                isPolling = false;
                clearInterval(pollInterval);
                const statusEl = document.getElementById('poll-status-text');
                if (statusEl) statusEl.innerHTML = '<span class="text-emerald-400 font-bold">✅ Pembayaran terkonfirmasi! Menyiapkan air...</span>';
                
                // Alihkan ke layar pengisian air (dispensing)
                setTimeout(() => {
                    window.location.href = `{{ url('kiosk/dispensing') }}/${invoiceId}`;
                }, 1000);
            }
        } catch (err) {
            console.warn('Gagal mengecek status pembayaran:', err);
        }
    }, 2500);
</script>
@endsection
