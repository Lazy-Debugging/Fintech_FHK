<?php
/**
 * Script Migrasi Web Standalone & Aman - Fresh Hydration Kios (FHK)
 * Berjalan langsung via PDO / Laravel untuk memastikan tabel kiosk_pricing dibuat.
 * 
 * URL Akses:
 * https://app.mesinbayar.com/fhk/migrate.php?key=fhk2026
 */

$validKey = 'fhk2026';
$providedKey = $_GET['key'] ?? $_POST['key'] ?? '';

if ($providedKey !== $validKey) {
    header('HTTP/1.1 403 Forbidden');
    echo "<!DOCTYPE html><html><body style='background:#0f172a;color:#f8fafc;font-family:sans-serif;text-align:center;padding:50px;'>
    <h2>🔒 Akses Ditolak</h2><p>Gunakan parameter: <code>migrate.php?key=fhk2026</code></p></body></html>";
    exit;
}

$steps = [];
$dbSuccess = false;

// 1. Coba koneksi langsung ke database.sqlite atau MySQL dari config/env
$dbPath = __DIR__ . '/database/database.sqlite';
$pdo = null;

if (file_exists($dbPath)) {
    try {
        $pdo = new PDO("sqlite:" . $dbPath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $steps[] = ['ok', '✅', 'Terhubung ke database SQLite: ' . $dbPath];
    } catch (\Throwable $e) {
        $steps[] = ['warn', '⚠️', 'Gagal konek SQLite langsung: ' . $e->getMessage()];
    }
}

// 2. Buat tabel kiosk_pricing langsung via SQL
if ($pdo) {
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS kiosk_pricing (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            kiosk_id VARCHAR(30) NULL,
            water_type VARCHAR(20) NOT NULL,
            volume_ml INTEGER NOT NULL,
            price INTEGER NOT NULL,
            is_active INTEGER DEFAULT 1,
            updated_by VARCHAR(100) NULL,
            created_at DATETIME NULL,
            updated_at DATETIME NULL,
            UNIQUE (kiosk_id, water_type, volume_ml)
        )");
        $steps[] = ['ok', '✅', "Tabel 'kiosk_pricing' berhasil diverifikasi / dibuat."];

        // Cek data default
        $countStmt = $pdo->query("SELECT COUNT(*) as total FROM kiosk_pricing");
        $count = (int) $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

        if ($count === 0) {
            $defaults = [
                ['kiosk_id' => null, 'water_type' => 'COLD',   'volume_ml' => 250,  'price' => 2000],
                ['kiosk_id' => null, 'water_type' => 'COLD',   'volume_ml' => 500,  'price' => 3500],
                ['kiosk_id' => null, 'water_type' => 'COLD',   'volume_ml' => 1000, 'price' => 6000],
                ['kiosk_id' => null, 'water_type' => 'NORMAL', 'volume_ml' => 250,  'price' => 1500],
                ['kiosk_id' => null, 'water_type' => 'NORMAL', 'volume_ml' => 500,  'price' => 2500],
                ['kiosk_id' => null, 'water_type' => 'NORMAL', 'volume_ml' => 1000, 'price' => 4500],
            ];
            $insert = $pdo->prepare("INSERT OR IGNORE INTO kiosk_pricing (kiosk_id, water_type, volume_ml, price, is_active, updated_by, created_at, updated_at) VALUES (:kiosk_id, :water_type, :volume_ml, :price, 1, 'AutoMigrate', datetime('now'), datetime('now'))");
            foreach ($defaults as $row) {
                $insert->execute($row);
            }
            $steps[] = ['ok', '✅', "6 harga default FHK berhasil dimasukkan ke tabel 'kiosk_pricing'."];
        } else {
            $steps[] = ['info', 'ℹ️', "Tabel 'kiosk_pricing' sudah berisi {$count} baris data."];
        }
        $dbSuccess = true;
    } catch (\Throwable $e) {
        $steps[] = ['err', '❌', 'Error saat mengeksekusi SQL: ' . $e->getMessage()];
    }
}

// 3. Hapus cache view compiled secara langsung dari filesystem
$viewFiles = glob(__DIR__ . '/storage/framework/views/*.php');
$deletedViews = 0;
if ($viewFiles) {
    foreach ($viewFiles as $f) {
        if (@unlink($f)) $deletedViews++;
    }
}
$steps[] = ['ok', '✅', "Berhasil membersihkan {$deletedViews} file cache Blade View."];

// Reset OPcache jika aktif
if (function_exists('opcache_reset')) {
    @opcache_reset();
    $steps[] = ['ok', '✅', "PHP OPcache server berhasil di-reset."];
}

// 4. Coba bootstrap Laravel jika tersedia
try {
    if (file_exists(__DIR__ . '/vendor/autoload.php') && file_exists(__DIR__ . '/bootstrap/app.php')) {
        require __DIR__ . '/vendor/autoload.php';
        $app = require_once __DIR__ . '/bootstrap/app.php';
        $kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);
        $kernel->bootstrap();

        \Illuminate\Support\Facades\Artisan::call('view:clear');
        \Illuminate\Support\Facades\Artisan::call('cache:clear');
        $steps[] = ['ok', '✅', 'Artisan view:clear & cache:clear berhasil dijalankan.'];
    }
} catch (\Throwable $e) {
    $steps[] = ['info', 'ℹ️', 'Laravel Bootstrap info: ' . $e->getMessage()];
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Migration & Setup - FHK</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>body { background-color: #0b1120; color: #f1f5f9; font-family: system-ui, sans-serif; }</style>
</head>
<body class="min-h-screen p-4 sm:p-8 flex items-center justify-center">
    <div class="max-w-xl w-full bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-2xl space-y-6">
        <div class="flex items-center gap-3 border-b border-slate-800 pb-4">
            <div class="w-12 h-12 rounded-xl bg-cyan-500/20 text-cyan-400 border border-cyan-500/30 flex items-center justify-center text-2xl font-bold">
                <i class="fa-solid fa-database"></i>
            </div>
            <div>
                <h1 class="text-lg font-extrabold text-white">Database Migration & Pricing Setup</h1>
                <p class="text-xs text-slate-400">Fresh Hydration Kios (FHK)</p>
            </div>
        </div>

        <div class="space-y-3">
            <?php foreach ($steps as [$type, $icon, $msg]): ?>
                <div class="p-3 rounded-xl <?= $type === 'ok' ? 'bg-emerald-500/10 border border-emerald-500/30 text-emerald-300' : ($type === 'err' ? 'bg-rose-500/10 border border-rose-500/30 text-rose-300' : 'bg-slate-800 text-slate-300') ?> text-xs font-mono flex items-center gap-2">
                    <span><?= $icon ?></span>
                    <span><?= htmlspecialchars($msg) ?></span>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="pt-4 border-t border-slate-800 flex justify-between items-center">
            <a href="admin/pricing" class="px-4 py-2 rounded-xl bg-cyan-500 hover:bg-cyan-400 text-slate-950 text-xs font-bold transition">
                <i class="fa-solid fa-tags mr-1"></i> Buka Pengaturan Harga Admin
            </a>
            <a href="kiosk" class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-white text-xs font-bold transition">
                <i class="fa-solid fa-desktop mr-1"></i> Layar Kios
            </a>
        </div>
    </div>
</body>
</html>
