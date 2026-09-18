@extends('layouts.kiosk_layout')

@section('title', 'Profil Saya - Fresh Hydration Kios')

@section('content')
<div class="w-full max-w-3xl mx-auto space-y-5 sm:space-y-6 my-auto py-2">

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
        <h3 class="text-sm font-bold uppercase tracking-wider text-slate-400 flex items-center gap-2">
            <i class="fa-solid fa-clock-rotate-left text-cyan-400"></i> Riwayat Transaksi
        </h3>

        @forelse($transactions as $tx)
            <div class="flex items-center justify-between gap-3 p-3 rounded-xl bg-slate-900/50 border border-slate-800/60">
                <div class="min-w-0">
                    <div class="text-sm font-bold text-white truncate">{{ $tx->kiosk?->name ?? $tx->kiosk_id }}</div>
                    <div class="text-[11px] text-slate-500 font-mono">{{ $tx->referenceId }} &middot; {{ $tx->created_at->format('d M Y, H:i') }}</div>
                </div>
                <div class="text-right shrink-0">
                    <div class="text-sm font-black text-cyan-400">Rp {{ number_format($tx->payAmount, 0, ',', '.') }}</div>
                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-slate-800 border border-slate-700 text-slate-300">{{ $tx->status }}</span>
                </div>
            </div>
        @empty
            <p class="text-xs text-slate-500 text-center py-6">Belum ada riwayat transaksi.</p>
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
@endsection
