<?php
/**
 * Script Sinkronisasi Otomatis File Kios AiYO dari GitHub ke Server cPanel
 * Memperbarui file controller, views, dan routing secara otomatis tanpa perlu navigasi manual FileZilla.
 */

$repoRawBase = "https://raw.githubusercontent.com/Lazy-Debugging/Fintech_FHK/main/";

$filesToSync = [
    "respon.php",
    "resources/views/kiosk/index.blade.php",
    "resources/views/kiosk/qris_payment.blade.php",
    "app/Http/Controllers/Kiosk/OrderController.php",
    "app/Http/Controllers/Kiosk/KioskScreenController.php",
    "routes/web.php"
];

$results = [];
$allSuccess = true;

foreach ($filesToSync as $relativePath) {
    $remoteUrl = $repoRawBase . $relativePath . "?v=" . time();
    $localPath = __DIR__ . '/' . $relativePath;

    $ch = curl_init($remoteUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    $content = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200 && !empty($content)) {
        $dir = dirname($localPath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        $written = @file_put_contents($localPath, $content);
        if ($written !== false) {
            $results[] = [
                'file'    => $relativePath,
                'status'  => 'OK',
                'size'    => strlen($content),
                'message' => 'Berhasil disinkronkan dari GitHub'
            ];
        } else {
            $allSuccess = false;
            $results[] = [
                'file'    => $relativePath,
                'status'  => 'ERROR',
                'size'    => 0,
                'message' => 'Gagal menulis ke path lokal server (Periksa permission)'
            ];
        }
    } else {
        $allSuccess = false;
        $results[] = [
            'file'    => $relativePath,
            'status'  => 'FAILED',
            'size'    => 0,
            'message' => "Gagal mengunduh dari GitHub (HTTP $httpCode)"
        ];
    }
}

// Bersihkan cache compiled blade views di storage/framework/views
$viewCacheDir = __DIR__ . '/storage/framework/views';
$clearedViews = 0;
if (is_dir($viewCacheDir)) {
    foreach (glob($viewCacheDir . '/*.php') as $viewFile) {
        @unlink($viewFile);
        $clearedViews++;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sinkronisasi AiYO Kiosk Gateway</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #0f172a; color: #f8fafc; padding: 30px 15px; margin: 0; }
        .container { max-width: 680px; margin: 0 auto; background: #1e293b; border-radius: 16px; border: 1px solid #334155; padding: 25px; box-shadow: 0 10px 25px rgba(0,0,0,0.5); }
        h1 { font-size: 20px; color: #38bdf8; margin-top: 0; }
        .status-badge { display: inline-block; padding: 4px 10px; border-radius: 6px; font-weight: bold; font-size: 11px; }
        .badge-ok { background: #065f46; color: #34d399; }
        .badge-fail { background: #991b1b; color: #f87171; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; font-size: 13px; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #334155; }
        th { color: #94a3b8; font-size: 11px; text-transform: uppercase; }
        .btn { display: inline-block; padding: 12px 24px; background: #0284c7; color: white; text-decoration: none; border-radius: 10px; font-weight: bold; font-size: 14px; margin-top: 20px; }
        .btn:hover { background: #0369a1; }
        .info-box { background: #0c4a6e; border-left: 4px solid #38bdf8; padding: 12px; border-radius: 8px; font-size: 13px; margin-top: 15px; }
    </style>
</head>
<body>
<div class="container">
    <h1>⚡ Sinkronisasi Pembaruan Kios AiYO</h1>
    <p style="font-size: 14px; color: #94a3b8;">
        Script ini memperbarui controller dan template blade Kios agar terhubung <strong>100% langsung ke AiYO Bills Invoice Gateway</strong> tanpa QRIS buatan sendiri.
    </p>

    <table>
        <thead>
            <tr>
                <th>File</th>
                <th>Status</th>
                <th>Ukuran</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($results as $res): ?>
            <tr>
                <td><code><?= htmlspecialchars($res['file']) ?></code></td>
                <td>
                    <span class="status-badge <?= $res['status'] === 'OK' ? 'badge-ok' : 'badge-fail' ?>">
                        <?= $res['status'] ?>
                    </span>
                </td>
                <td><?= number_format($res['size']) ?> B</td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="info-box">
        🧹 <strong>Cache Blade Dibersihkan:</strong> <?= $clearedViews ?> file cache view lama di <code>storage/framework/views</code> telah dibersihkan secara otomatis.
    </div>

    <div style="text-align: center; margin-top: 25px;">
        <a href="kiosk" class="btn">🚀 Buka Halaman Kios (Test AiYO QRIS) &rarr;</a>
    </div>
</div>
</body>
</html>
