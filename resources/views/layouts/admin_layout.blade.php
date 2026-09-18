<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Admin & Maintenance Dashboard - Fresh Hydration Kios')</title>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- Tailwind CDN -->
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
                            dark: '#0a0f1d',
                            panel: '#111827',
                            cyan: '#06b6d4',
                            uv: '#8b5cf6'
                        }
                    }
                }
            }
        }
    </script>

    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: #0b1120;
            color: #f1f5f9;
        }
        .admin-card {
            background: #111827;
            border: 1px solid #1f2937;
        }
    </style>
    @yield('styles')
</head>
<body class="min-h-screen flex flex-col">

    <!-- Navbar -->
    <nav class="border-b border-slate-800 bg-slate-950/80 backdrop-blur-md sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-3 sm:px-4 md:px-6 lg:px-8 py-3 sm:py-4 flex items-center justify-between gap-3">
        <div class="flex items-center space-x-3 min-w-0">
            <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-cyan-500 to-blue-600 flex items-center justify-center text-white font-bold shadow-md shadow-cyan-500/20">
                <i class="fa-solid fa-droplet text-lg"></i>
            </div>
            <div>
                <h1 class="font-extrabold text-white text-sm sm:text-base tracking-tight truncate">FHK IoT & Maintenance Monitor</h1>
                <p class="hidden sm:block text-[11px] text-slate-400">Pusat Kendali Dispenser, Filter UV & AiYO Gateway</p>
            </div>
        </div>

        <button type="button" id="admin-menu-toggle" aria-controls="admin-menu" aria-expanded="false" class="md:hidden shrink-0 p-2 rounded-xl text-slate-300 hover:bg-slate-800" aria-label="Buka menu administrasi">
            <i class="fa-solid fa-bars text-lg"></i>
        </button>

        <div class="hidden md:flex items-center space-x-2 text-xs font-semibold">
            <a href="{{ route('admin.dashboard') }}" class="px-3 py-2 rounded-xl {{ request()->routeIs('admin.dashboard') ? 'bg-cyan-500/20 text-cyan-300 border border-cyan-500/30' : 'text-slate-400 hover:text-white' }} flex items-center gap-2">
                <i class="fa-solid fa-chart-pie"></i>
                <span>Monitoring Dashboard</span>
            </a>

            <a href="{{ route('admin.simulator') }}" class="px-3 py-2 rounded-xl {{ request()->routeIs('admin.simulator') ? 'bg-purple-500/20 text-purple-300 border border-purple-500/30' : 'text-slate-400 hover:text-white' }} flex items-center gap-2">
                <i class="fa-solid fa-microchip"></i>
                <span>ESP32 Hardware Simulator</span>
            </a>

            <a href="{{ route('admin.vouchers.index') }}" class="px-3 py-2 rounded-xl {{ request()->routeIs('admin.vouchers.*') ? 'bg-amber-500/20 text-amber-300 border border-amber-500/30' : 'text-slate-400 hover:text-white' }} flex items-center gap-2">
                <i class="fa-solid fa-ticket"></i>
                <span>Voucher</span>
            </a>

            <a href="{{ route('kiosk.home') }}" target="_blank" class="px-4 py-2 rounded-xl bg-gradient-to-r from-cyan-500 to-blue-600 hover:from-cyan-400 hover:to-blue-500 text-slate-950 font-bold transition flex items-center gap-2 shadow-md shadow-cyan-500/20">
                <i class="fa-solid fa-desktop"></i>
                <span>Buka Layar Kios PWA</span>
            </a>
        </div>
        </div>

        <div id="admin-menu" class="hidden md:hidden max-w-7xl mx-auto px-3 sm:px-4 pb-3 space-y-2 text-xs font-semibold">
            <a href="{{ route('admin.dashboard') }}" class="px-3 py-2.5 rounded-xl {{ request()->routeIs('admin.dashboard') ? 'bg-cyan-500/20 text-cyan-300 border border-cyan-500/30' : 'bg-slate-900 text-slate-300 border border-slate-800' }} flex items-center gap-2">
                <i class="fa-solid fa-chart-pie"></i><span>Monitoring Dashboard</span>
            </a>
            <a href="{{ route('admin.simulator') }}" class="px-3 py-2.5 rounded-xl {{ request()->routeIs('admin.simulator') ? 'bg-purple-500/20 text-purple-300 border border-purple-500/30' : 'bg-slate-900 text-slate-300 border border-slate-800' }} flex items-center gap-2">
                <i class="fa-solid fa-microchip"></i><span>ESP32 Hardware Simulator</span>
            </a>
            <a href="{{ route('admin.vouchers.index') }}" class="px-3 py-2.5 rounded-xl {{ request()->routeIs('admin.vouchers.*') ? 'bg-amber-500/20 text-amber-300 border border-amber-500/30' : 'bg-slate-900 text-slate-300 border border-slate-800' }} flex items-center gap-2">
                <i class="fa-solid fa-ticket"></i><span>Voucher</span>
            </a>
            <a href="{{ route('kiosk.home') }}" target="_blank" class="px-3 py-2.5 rounded-xl bg-cyan-500 text-slate-950 font-bold flex items-center gap-2">
                <i class="fa-solid fa-desktop"></i><span>Buka Layar Kios PWA</span>
            </a>
        </div>
    </nav>

    <!-- Main Body Content -->
    <main class="flex-1 max-w-7xl w-full mx-auto px-3 sm:px-4 md:px-6 lg:px-8 py-5 sm:py-8">
        @yield('content')
    </main>

    <!-- Footer -->
    <footer class="border-t border-slate-800/80 bg-slate-950/50 py-4 px-3 sm:px-6 text-center text-[11px] sm:text-xs text-slate-500">
        Fresh Hydration Kios (FHK) Management System • Terhubung dengan AiYO Bills Invoice Gateway
    </footer>

    <script>
        const adminMenuToggle = document.getElementById('admin-menu-toggle');
        const adminMenu = document.getElementById('admin-menu');
        adminMenuToggle?.addEventListener('click', () => {
            const isOpen = !adminMenu.classList.toggle('hidden');
            adminMenuToggle.setAttribute('aria-expanded', String(isOpen));
        });
    </script>
    @yield('scripts')
</body>
</html>
