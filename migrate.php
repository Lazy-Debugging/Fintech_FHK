<?php
/**
 * Script Migrasi Web Live - Fresh Hydration Kios (FHK)
 * Digunakan untuk menjalankan artisan migrate dari browser di Live Server.
 * 
 * Akses via Browser:
 * https://app.mesinbayar.com/fhk/migrate.php?key=fhk2026
 */

define('LARAVEL_START', microtime(true));

// Keamanan: Cek Secret Key (bisa disesuaikan jika perlu)
$validKey = 'fhk2026';
$providedKey = $_GET['key'] ?? $_POST['key'] ?? '';

if ($providedKey !== $validKey) {
    header('HTTP/1.1 403 Forbidden');
    echo "<!DOCTYPE html>
    <html>
    <head><title>403 Forbidden - FHK Migration</title></head>
    <body style='font-family: system-ui, sans-serif; background: #0f172a; color: #f8fafc; padding: 40px; text-align: center;'>
        <div style='max-width: 500px; margin: 0 auto; background: #1e293b; padding: 30px; rounded: 16px; border: 1px solid #334155;'>
            <h2 style='color: #ef4444;'>🔒 Akses Ditolak</h2>
            <p style='color: #94a3b8; font-size: 14px;'>Silakan masukkan parameter secret key di URL.</p>
            <code style='background: #0f172a; padding: 8px 12px; border-radius: 6px; color: #38bdf8;'>migrate.php?key=fhk2026</code>
        </div>
    </body>
    </html>";
    exit;
}

// Bootstrapping Laravel
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Database Migration - Fresh Hydration Kios</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body { background-color: #0b1120; color: #f1f5f9; font-family: system-ui, sans-serif; }
    </style>
</head>
<body class="min-h-screen p-4 sm:p-8 flex items-center justify-center">
    <div class="max-w-2xl w-full bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-2xl space-y-6">
        
        <!-- Header -->
        <div class="flex items-center gap-3 border-b border-slate-800 pb-4">
            <div class="w-12 h-12 rounded-xl bg-cyan-500/20 text-cyan-400 border border-cyan-500/30 flex items-center justify-center text-2xl font-bold">
                <i class="fa-solid fa-database"></i>
            </div>
            <div>
                <h1 class="text-xl font-extrabold text-white">Live Server Database Migration</h1>
                <p class="text-xs text-slate-400">Fresh Hydration Kios (FHK) Automated Database Setup</p>
            </div>
        </div>

        <!-- Progress Output -->
        <div class="space-y-4">
            <?php
            try {
                // 1. Run Migration
                Artisan::call('migrate', ['--force' => true]);
                $outputMigrate = Artisan::output();

                // 2. Clear Views
                Artisan::call('view:clear');
                $outputView = Artisan::output();

                // 3. Clear Cache
                Artisan::call('cache:clear');
                $outputCache = Artisan::output();

                // Check kiosk_pricing table status
                $tableExists = Schema::hasTable('kiosk_pricing');
                $pricingCount = $tableExists ? DB::table('kiosk_pricing')->count() : 0;

                echo "<div class='p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-300 text-sm font-semibold flex items-center gap-2'>
                        <i class='fa-solid fa-circle-check text-lg'></i>
                        <span>Migrasi Database Berhasil Dijalankan!</span>
                      </div>";

                echo "<div class='space-y-2'>
                        <label class='text-xs font-bold text-slate-400 uppercase tracking-wider'>Status Tabel kiosk_pricing:</label>
                        <div class='p-3 rounded-lg bg-slate-950 border border-slate-800 text-xs font-mono text-cyan-300 flex items-center justify-between'>
                            <span>Tabel 'kiosk_pricing'</span>
                            <span class='px-2 py-0.5 rounded bg-cyan-500/20 text-cyan-300 font-bold'>
                                " . ($tableExists ? "TERSEDIA ({$pricingCount} baris)" : "BELUM TERSEDIA") . "
                            </span>
                        </div>
                      </div>";

                echo "<div class='space-y-2'>
                        <label class='text-xs font-bold text-slate-400 uppercase tracking-wider'>Log Output Artisan Migrate:</label>
                        <pre class='p-4 rounded-xl bg-slate-950 border border-slate-800 text-xs font-mono text-slate-300 overflow-x-auto whitespace-pre-wrap'>" . htmlspecialchars($outputMigrate) . "</pre>
                      </div>";

                echo "<div class='space-y-2'>
                        <label class='text-xs font-bold text-slate-400 uppercase tracking-wider'>Log Clear Cache & Views:</label>
                        <pre class='p-4 rounded-xl bg-slate-950 border border-slate-800 text-xs font-mono text-slate-400 overflow-x-auto whitespace-pre-wrap'>" . htmlspecialchars($outputView . $outputCache) . "</pre>
                      </div>";

            } catch (\Throwable $e) {
                echo "<div class='p-4 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-300 text-sm font-semibold space-y-2'>
                        <div class='flex items-center gap-2 text-rose-400 font-bold'>
                            <i class='fa-solid fa-circle-xmark text-lg'></i>
                            <span>Gagal Menjalankan Migrasi!</span>
                        </div>
                        <p class='text-xs font-mono text-rose-200 break-words'>" . htmlspecialchars($e->getMessage()) . "</p>
                      </div>";
            }
            ?>
        </div>

        <!-- Footer Action -->
        <div class="border-t border-slate-800 pt-4 flex flex-col sm:flex-row items-center justify-between gap-3">
            <span class="text-xs text-slate-500">FHK Web Live Deployment Assistant</span>
            <a href="admin/pricing" class="px-4 py-2 rounded-xl bg-cyan-500 hover:bg-cyan-400 text-slate-950 text-xs font-extrabold transition flex items-center gap-2">
                <i class="fa-solid fa-tags"></i>
                <span>Buka Pengaturan Harga Admin</span>
            </a>
        </div>

    </div>
</body>
</html>
