<?php
/**
 * update_vouchers.php
 * Skrip Instan 1-Klik untuk Memperbarui Tampilan Voucher & Membersihkan Cache Laravel
 * Akses: https://app.mesinbayar.com/fhk/update_vouchers.php
 */

header('Content-Type: text/html; charset=utf-8');

$baseDir = __DIR__;
$results = [];

// 1. Daftar file yang disinkronkan dari GitHub
$files = [
    'resources/views/admin/vouchers.blade.php',
    'app/Http/Controllers/Admin/VoucherController.php',
    'routes/web.php',
];

$rawBase = 'https://raw.githubusercontent.com/Lazy-Debugging/Fintech_FHK/main/';

foreach ($files as $relPath) {
    $targetPath = $baseDir . '/' . $relPath;
    $targetDir = dirname($targetPath);
    if (!is_dir($targetDir)) {
        @mkdir($targetDir, 0777, true);
    }

    $url = $rawBase . $relPath . '?v=' . time();
    $content = null;

    // Coba unduh via cURL
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        $content = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || empty($content)) {
            $content = null;
        }
    }

    // Fallback file_get_contents
    if (!$content) {
        $ctx = stream_context_create([
            'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
            'http' => ['timeout' => 15]
        ]);
        $content = @file_get_contents($url, false, $ctx);
    }

    if ($content && strlen($content) > 50) {
        $written = @file_put_contents($targetPath, $content);
        if ($written !== false) {
            $results[] = [
                'file' => $relPath,
                'status' => 'SUCCESS',
                'size' => strlen($content) . ' bytes',
                'msg' => 'Berhasil diperbarui dari GitHub.'
            ];
        } else {
            $results[] = [
                'file' => $relPath,
                'status' => 'FAILED',
                'size' => '0 bytes',
                'msg' => 'Gagal menulis file (cek izin folder/permission 755/777).'
            ];
        }
    } else {
        $results[] = [
            'file' => $relPath,
            'status' => 'WARNING',
            'size' => '0 bytes',
            'msg' => 'Tidak dapat mengunduh dari GitHub. Pastikan koneksi internet server aktif.'
        ];
    }
}

// 2. Bersihkan Cache View Laravel (storage/framework/views)
$cacheCleared = 0;
$viewCacheDir = $baseDir . '/storage/framework/views';
if (is_dir($viewCacheDir)) {
    $cacheFiles = glob($viewCacheDir . '/*');
    if ($cacheFiles) {
        foreach ($cacheFiles as $cf) {
            if (is_file($cf) && basename($cf) !== '.gitignore') {
                if (@unlink($cf)) {
                    $cacheCleared++;
                }
            }
        }
    }
}

// 3. Bersihkan cache bootstrap jika ada
$bootstrapCacheDir = $baseDir . '/bootstrap/cache';
if (is_dir($bootstrapCacheDir)) {
    $bFiles = ['routes-v7.php', 'config.php', 'services.php', 'packages.php'];
    foreach ($bFiles as $bf) {
        $bPath = $bootstrapCacheDir . '/' . $bf;
        if (file_exists($bPath)) {
            @unlink($bPath);
        }
    }
}

// Coba panggil Artisan jika framework terpasang
if (file_exists($baseDir . '/artisan')) {
    @exec('php artisan view:clear 2>&1');
    @exec('php artisan route:clear 2>&1');
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Tampilan Voucher FHK</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: #0b1120;
            color: #f1f5f9;
        }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4">
    <div class="max-w-xl w-full bg-slate-900 border border-slate-800 rounded-2xl p-6 sm:p-8 shadow-2xl space-y-6">
        
        <div class="flex items-center gap-4 border-b border-slate-800 pb-5">
            <div class="w-12 h-12 rounded-xl bg-cyan-500/20 text-cyan-400 flex items-center justify-center text-2xl border border-cyan-500/30">
                <i class="fa-solid fa-arrows-rotate"></i>
            </div>
            <div>
                <h1 class="text-xl font-black text-white">Sinkronisasi & Pembersihan Cache</h1>
                <p class="text-xs text-slate-400">Fresh Hydration Kios (FHK) - Admin Panel</p>
            </div>
        </div>

        <div class="space-y-3">
            <div class="text-xs font-bold uppercase tracking-wider text-slate-400">Status Sinkronisasi File:</div>
            
            @php @endphp
            <?php foreach ($results as $res): ?>
                <div class="flex items-center justify-between p-3 rounded-xl bg-slate-950/70 border border-slate-800/80 text-xs">
                    <div class="space-y-0.5">
                        <div class="font-mono font-bold text-slate-200"><?php echo htmlspecialchars($res['file']); ?></div>
                        <div class="text-[11px] text-slate-400"><?php echo htmlspecialchars($res['msg']); ?> (<?php echo $res['size']; ?>)</div>
                    </div>
                    <?php if ($res['status'] === 'SUCCESS'): ?>
                        <span class="px-2.5 py-1 rounded-md text-[10px] font-black bg-emerald-500/20 text-emerald-400 border border-emerald-500/30">
                            SUKSES
                        </span>
                    <?php else: ?>
                        <span class="px-2.5 py-1 rounded-md text-[10px] font-black bg-amber-500/20 text-amber-400 border border-amber-500/30">
                            <?php echo $res['status']; ?>
                        </span>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>

            <div class="flex items-center justify-between p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-xs text-emerald-300">
                <div class="flex items-center gap-2">
                    <i class="fa-solid fa-broom text-emerald-400"></i>
                    <span>Cache Blade View Dibersihkan:</span>
                </div>
                <span class="font-black"><?php echo $cacheCleared; ?> file cache dihapus</span>
            </div>
        </div>

        <div class="pt-2 flex flex-col sm:flex-row gap-3">
            <a href="admin/vouchers" class="flex-1 py-3 px-4 rounded-xl bg-gradient-to-r from-cyan-500 to-blue-600 hover:from-cyan-400 hover:to-blue-500 text-slate-950 font-black text-xs sm:text-sm text-center transition shadow-lg shadow-cyan-500/20">
                <i class="fa-solid fa-arrow-right mr-1"></i> Buka Halaman Voucher Admin
            </a>
            <a href="update_vouchers.php" class="py-3 px-4 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 font-bold text-xs text-center border border-slate-700 transition">
                <i class="fa-solid fa-rotate-right mr-1"></i> Jalankan Lagi
            </a>
        </div>

    </div>
</body>
</html>
