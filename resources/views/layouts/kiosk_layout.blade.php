<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#06b6d4">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Fresh Hydration Kios (FHK) - UV Purified Dispenser')</title>

    <!-- PWA Manifest -->
    <link rel="manifest" href="{{ rtrim(request()->getBaseUrl(), '/') }}/manifest.webmanifest">
    <link rel="apple-touch-icon" href="{{ rtrim(request()->getBaseUrl(), '/') }}/icons/icon-192.svg">
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>💧</text></svg>">

    <!-- Fonts: Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- FontAwesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- Tailwind CSS CDN for Kiosk Screen Styling -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Plus Jakarta Sans', 'sans-serif'],
                    },
                    colors: {
                        brand: {
                            cyan: '#06B6D4',
                            blue: '#0284C7',
                            dark: '#0A0F1D',
                            card: '#111827',
                            accent: '#38BDF8',
                            uv: '#8B5CF6'
                        }
                    },
                    animation: {
                        'pulse-glow': 'pulseGlow 2.5s infinite ease-in-out',
                        'flow': 'flow 3s infinite linear',
                    },
                    keyframes: {
                        pulseGlow: {
                            '0%, 100%': { boxShadow: '0 0 25px rgba(6, 182, 212, 0.35)' },
                            '50%': { boxShadow: '0 0 50px rgba(139, 92, 246, 0.65)' },
                        }
                    }
                }
            }
        }
    </script>

    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: radial-gradient(circle at 50% 0%, #172554 0%, #0a0f1d 70%, #030712 100%);
            color: #F8FAFC;
            min-height: 100vh;
            user-select: none;
            -webkit-user-select: none;
            overflow-x: hidden;
        }

        .glass-panel {
            background: rgba(17, 24, 39, 0.7);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            box-shadow: 0 20px 40px -15px rgba(0, 0, 0, 0.5);
        }

        .glass-glow-cyan {
            border: 1px solid rgba(6, 182, 212, 0.3);
            box-shadow: 0 10px 30px -10px rgba(6, 182, 212, 0.4);
        }

        .glass-glow-uv {
            border: 1px solid rgba(139, 92, 246, 0.4);
            box-shadow: 0 10px 30px -10px rgba(139, 92, 246, 0.5);
        }

        #pwa-install-button[hidden], #pwa-update-button[hidden] { display: none; }

        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 6px;
        }
        ::-webkit-scrollbar-track {
            background: #0b1120;
        }
        ::-webkit-scrollbar-thumb {
            background: #1e293b;
            border-radius: 9999px;
        }
    </style>

    @yield('styles')
</head>
<body class="flex flex-col justify-between min-h-screen">

    <!-- Top Kiosk Header Status Bar -->
    <header class="w-full border-b border-slate-800/80 bg-slate-950/60 backdrop-blur-md px-3 sm:px-4 md:px-6 py-3 sm:py-4 flex items-center justify-between gap-3 sticky top-0 z-50">
        <div class="flex items-center space-x-2 sm:space-x-4 min-w-0">
            <div class="w-11 h-11 rounded-2xl bg-gradient-to-tr from-cyan-500 to-blue-600 flex items-center justify-center text-white shadow-lg shadow-cyan-500/30">
                <i class="fa-solid fa-droplet text-xl animate-bounce"></i>
            </div>
            <div>
                <h1 class="text-sm sm:text-lg font-extrabold tracking-tight bg-clip-text text-transparent bg-gradient-to-r from-cyan-400 via-sky-300 to-white">
                    FRESH HYDRATION KIOS
                </h1>
                <p class="hidden sm:flex text-xs text-slate-400 items-center gap-1.5">
                    <span class="inline-block w-2 h-2 rounded-full bg-emerald-400 animate-ping"></span>
                    <span class="font-medium text-slate-300">ESP32 IoT Online</span>
                    <span class="text-slate-600">•</span>
                    <span>Stasiun Gambir #01</span>
                </p>
            </div>
        </div>

        <!-- Telemetry Pill Badges -->
        <div class="flex items-center space-x-2 sm:space-x-3 text-xs shrink-0">
            <div class="hidden md:flex items-center gap-2 px-3 py-1.5 rounded-full bg-purple-950/50 border border-purple-800/60 text-purple-300">
                <i class="fa-solid fa-shield-virus text-purple-400"></i>
                <span>UV-C Purifier: <strong class="text-white">Active (99.9%)</strong></span>
            </div>

            <div class="hidden sm:flex items-center gap-2 px-3 py-1.5 rounded-full bg-cyan-950/50 border border-cyan-800/60 text-cyan-300">
                <i class="fa-solid fa-temperature-three-quarters text-cyan-400"></i>
                <span>Cold: <strong class="text-white">7.5°C</strong></span>
            </div>

            <div class="flex items-center gap-2 px-3 py-1.5 rounded-full bg-slate-800/80 border border-slate-700 text-slate-300">
                <i class="fa-solid fa-clock text-slate-400"></i>
                <span id="kiosk-clock" class="font-mono font-semibold">00:00:00</span>
            </div>

            <span id="connection-status" class="hidden min-[420px]:inline-flex items-center gap-1 text-emerald-400" aria-live="polite" title="Koneksi aktif">
                <i class="fa-solid fa-wifi"></i>
            </span>

            <button type="button" id="pwa-install-button" hidden class="px-3 py-1.5 rounded-full bg-cyan-500 hover:bg-cyan-400 text-slate-950 font-bold transition flex items-center gap-1.5" aria-label="Pasang aplikasi kiosk">
                <i class="fa-solid fa-download"></i>
                <span class="hidden min-[420px]:inline">Pasang</span>
            </button>

            <button type="button" id="pwa-update-button" hidden class="px-3 py-1.5 rounded-full bg-amber-400 hover:bg-amber-300 text-slate-950 font-bold transition flex items-center gap-1.5" aria-label="Muat pembaruan aplikasi">
                <i class="fa-solid fa-rotate"></i>
                <span class="hidden min-[420px]:inline">Perbarui</span>
            </button>

        </div>
    </header>

    <!-- Secondary Nav: Kios / Profil / Login -->
    <nav class="w-full border-b border-slate-800/60 bg-slate-950/40 px-3 sm:px-4 md:px-6 py-2 flex items-center justify-between gap-3 sticky top-[64px] sm:top-[72px] z-40">
        <div class="flex items-center gap-1.5 text-xs font-bold">
            <a href="{{ route('home') }}" class="px-3 py-1.5 rounded-xl flex items-center gap-1.5 transition {{ request()->routeIs('home') || request()->routeIs('kiosk.*') ? 'bg-cyan-500/20 text-cyan-300 border border-cyan-500/30' : 'text-slate-400 hover:text-white' }}">
                <i class="fa-solid fa-droplet"></i><span>Kios</span>
            </a>
            <a href="{{ route('profile') }}" class="px-3 py-1.5 rounded-xl flex items-center gap-1.5 transition {{ request()->routeIs('profile') ? 'bg-cyan-500/20 text-cyan-300 border border-cyan-500/30' : 'text-slate-400 hover:text-white' }}">
                <i class="fa-solid fa-user"></i><span>Profil</span>
            </a>
        </div>

        <div class="text-xs font-bold">
            @auth
                <div class="flex items-center gap-2">
                    <span class="hidden sm:inline text-slate-400">Halo, <strong class="text-slate-200">{{ Auth::user()->name }}</strong></span>
                    <form method="post" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="px-3 py-1.5 rounded-xl bg-slate-800/80 border border-slate-700 text-slate-300 hover:bg-slate-700 transition flex items-center gap-1.5">
                            <i class="fa-solid fa-right-from-bracket"></i><span class="hidden min-[420px]:inline">Keluar</span>
                        </button>
                    </form>
                </div>
            @else
                <a href="{{ route('login') }}" class="px-3 py-1.5 rounded-xl bg-gradient-to-r from-cyan-500 to-blue-600 text-slate-950 hover:from-cyan-400 hover:to-blue-500 transition flex items-center gap-1.5">
                    <i class="fa-solid fa-right-to-bracket"></i><span>Masuk</span>
                </a>
            @endauth
        </div>
    </nav>

    <!-- Main Dynamic Content Area -->
    <main class="flex-1 max-w-6xl w-full mx-auto px-3 sm:px-4 md:px-6 py-4 sm:py-6 flex flex-col justify-center">
        @yield('content')
    </main>

    <!-- Footer Bar -->
    <footer class="w-full border-t border-slate-800/60 bg-slate-950/40 px-3 sm:px-6 py-3 text-center text-[11px] sm:text-xs text-slate-500 flex flex-col sm:flex-row items-center justify-between gap-2">
        <div class="flex items-center gap-2">
            <i class="fa-solid fa-qrcode text-cyan-400"></i>
            <span>Didukung oleh <strong class="text-slate-300">AiYO QRIS Dynamic Payment Gateway</strong></span>
        </div>
        <div>
            <span>Sistem Otomasi UV Dispenser ESP32 • &copy; 2026 Fresh Hydration Kios</span>
        </div>
    </footer>

    <!-- Service Worker Script Registration -->
    <script>
        const pwaBaseUrl = "{{ rtrim(request()->getBaseUrl(), '/') }}";
        const pwaServiceWorkerUrl = `${pwaBaseUrl}/sw.js`;
        let deferredInstallPrompt;

        const installButton = document.getElementById('pwa-install-button');
        const updateButton = document.getElementById('pwa-update-button');
        const connectionStatus = document.getElementById('connection-status');

        window.addEventListener('beforeinstallprompt', (event) => {
            event.preventDefault();
            deferredInstallPrompt = event;
            installButton.hidden = false;
        });

        installButton?.addEventListener('click', async () => {
            if (!deferredInstallPrompt) return;

            deferredInstallPrompt.prompt();
            await deferredInstallPrompt.userChoice;
            deferredInstallPrompt = null;
            installButton.hidden = true;
        });

        window.addEventListener('appinstalled', () => {
            deferredInstallPrompt = null;
            installButton.hidden = true;
        });

        function updateConnectionStatus() {
            const online = navigator.onLine;
            connectionStatus?.classList.toggle('text-emerald-400', online);
            connectionStatus?.classList.toggle('text-amber-400', !online);
            connectionStatus?.querySelector('i')?.classList.toggle('fa-wifi', online);
            connectionStatus?.querySelector('i')?.classList.toggle('fa-wifi-slash', !online);
            connectionStatus?.setAttribute('title', online ? 'Koneksi aktif' : 'Mode offline');
        }

        window.addEventListener('online', updateConnectionStatus);
        window.addEventListener('offline', updateConnectionStatus);
        updateConnectionStatus();

        // Bersihkan cache Service Worker lama agar browser selalu memuat kode terbaru dari server
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.getRegistrations().then((registrations) => {
                for (let registration of registrations) {
                    registration.unregister();
                }
            });
            if ('caches' in window) {
                caches.keys().then((names) => {
                    for (let name of names) {
                        caches.delete(name);
                    }
                });
            }
        }

        // Realtime Clock Updater
        function updateClock() {
            const now = new Date();
            const timeStr = now.toLocaleTimeString('id-ID', { hour12: false });
            const clockEl = document.getElementById('kiosk-clock');
            if (clockEl) clockEl.textContent = timeStr;
        }
        setInterval(updateClock, 1000);
        updateClock();
    </script>

    @yield('scripts')
</body>
</html>
