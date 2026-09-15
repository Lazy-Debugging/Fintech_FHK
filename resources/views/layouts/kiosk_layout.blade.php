<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#06b6d4">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Fresh Hydration Kios (FHK) - UV Purified Dispenser')</title>

    <!-- PWA Manifest -->
    <link rel="manifest" href="/manifest.json">
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
    <header class="w-full border-b border-slate-800/80 bg-slate-950/60 backdrop-blur-md px-6 py-4 flex items-center justify-between sticky top-0 z-50">
        <div class="flex items-center space-x-4">
            <div class="w-11 h-11 rounded-2xl bg-gradient-to-tr from-cyan-500 to-blue-600 flex items-center justify-center text-white shadow-lg shadow-cyan-500/30">
                <i class="fa-solid fa-droplet text-xl animate-bounce"></i>
            </div>
            <div>
                <h1 class="text-lg font-extrabold tracking-tight bg-clip-text text-transparent bg-gradient-to-r from-cyan-400 via-sky-300 to-white">
                    FRESH HYDRATION KIOS
                </h1>
                <p class="text-xs text-slate-400 flex items-center gap-1.5">
                    <span class="inline-block w-2 h-2 rounded-full bg-emerald-400 animate-ping"></span>
                    <span class="font-medium text-slate-300">ESP32 IoT Online</span>
                    <span class="text-slate-600">•</span>
                    <span>Stasiun Gambir #01</span>
                </p>
            </div>
        </div>

        <!-- Telemetry Pill Badges -->
        <div class="flex items-center space-x-3 text-xs">
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

            <!-- Quick Link to Dashboard & Simulator -->
            <a href="{{ route('admin.dashboard') }}" class="px-3 py-1.5 rounded-full bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white border border-slate-700 transition flex items-center gap-1.5">
                <i class="fa-solid fa-sliders text-xs"></i>
                <span class="hidden sm:inline">Admin / Simulator</span>
            </a>
        </div>
    </header>

    <!-- Main Dynamic Content Area -->
    <main class="flex-1 max-w-6xl w-full mx-auto px-4 py-6 flex flex-col justify-center">
        @yield('content')
    </main>

    <!-- Footer Bar -->
    <footer class="w-full border-t border-slate-800/60 bg-slate-950/40 px-6 py-3 text-center text-xs text-slate-500 flex flex-col sm:flex-row items-center justify-between gap-2">
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
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/sw.js')
                    .then(reg => console.log('PWA Service Worker registered:', reg.scope))
                    .catch(err => console.log('Service Worker registration failed:', err));
            });
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
