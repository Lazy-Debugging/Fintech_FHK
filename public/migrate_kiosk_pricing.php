<?php
/**
 * ============================================================
 *  Web-based Migration: kiosk_pricing
 * ============================================================
 *  Jalankan file ini dari browser untuk membuat tabel
 *  `kiosk_pricing` beserta data default-nya.
 *
 *  URL:  https://domain.com/migrate_kiosk_pricing.php
 *
 *  PENTING: Hapus file ini setelah migrasi berhasil!
 * ============================================================
 */

// ─── Proteksi akses (ganti token jika perlu) ────────────────
$SECRET_TOKEN = 'fhk-migrate-2026';

if (($_GET['token'] ?? '') !== $SECRET_TOKEN) {
    http_response_code(403);
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>403 Forbidden</title></head>';
    echo '<body style="font-family:Inter,sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;background:#0f172a;color:#f8fafc;">';
    echo '<div style="text-align:center"><h1 style="font-size:4rem;margin:0;">🔒 403</h1><p style="color:#94a3b8;">Akses ditolak. Token tidak valid.</p>';
    echo '<p style="color:#64748b;font-size:0.85rem;">Gunakan: <code style="background:#1e293b;padding:2px 8px;border-radius:4px;">?token=' . htmlspecialchars($SECRET_TOKEN) . '</code></p>';
    echo '</div></body></html>';
    exit;
}

// ─── Bootstrap Laravel ──────────────────────────────────────
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->handle(Illuminate\Http\Request::capture());

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// ─── Styling ────────────────────────────────────────────────
$css = <<<'CSS'
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Inter','Segoe UI',system-ui,sans-serif;background:#0f172a;color:#f8fafc;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:2rem}
.card{background:linear-gradient(135deg,#1e293b,#0f172a);border:1px solid #334155;border-radius:16px;padding:2.5rem;max-width:640px;width:100%;box-shadow:0 25px 50px rgba(0,0,0,.5)}
h1{font-size:1.5rem;font-weight:700;margin-bottom:.25rem;background:linear-gradient(135deg,#38bdf8,#818cf8);-webkit-background-clip:text;-webkit-text-fill-color:transparent}
.sub{color:#94a3b8;font-size:.9rem;margin-bottom:1.5rem}
.step{display:flex;align-items:flex-start;gap:.75rem;padding:.6rem 0;border-bottom:1px solid #1e293b}
.step:last-child{border-bottom:none}
.icon{width:24px;height:24px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.8rem;flex-shrink:0;margin-top:2px}
.ok{background:#065f46;color:#34d399}.err{background:#7f1d1d;color:#f87171}.skip{background:#3730a3;color:#a5b4fc}
.msg{font-size:.875rem;line-height:1.5}
.msg code{background:#1e293b;padding:1px 6px;border-radius:4px;font-size:.8rem;color:#7dd3fc}
.warn{margin-top:1.5rem;padding:1rem;background:rgba(250,204,21,.08);border:1px solid rgba(250,204,21,.2);border-radius:10px;color:#fde68a;font-size:.8rem;text-align:center}
.data-table{margin-top:1rem;width:100%;border-collapse:collapse;font-size:.8rem}
.data-table th{text-align:left;padding:6px 8px;background:#1e293b;color:#94a3b8;font-weight:500;border-bottom:1px solid #334155}
.data-table td{padding:6px 8px;border-bottom:1px solid #1e293b;color:#e2e8f0}
.data-table tr:hover td{background:#1e293b66}
CSS;

// ─── Mulai output ───────────────────────────────────────────
echo "<!DOCTYPE html><html lang='id'><head><meta charset='utf-8'><meta name='viewport' content='width=device-width,initial-scale=1'>";
echo "<title>Migrate: kiosk_pricing</title><style>{$css}</style></head><body><div class='card'>";
echo "<h1>🗄️ Migration Runner</h1><p class='sub'>Tabel <code>kiosk_pricing</code> &mdash; Fresh Hydration Kios</p>";

$steps = [];
$hasError = false;

// ─── STEP 1: Cek apakah tabel sudah ada ────────────────────
if (Schema::hasTable('kiosk_pricing')) {
    $count = DB::table('kiosk_pricing')->count();
    $steps[] = ['skip', '⏭️', "Tabel <code>kiosk_pricing</code> sudah ada ({$count} data). Migrasi di-skip."];
} else {
    // ─── STEP 2: Buat tabel ─────────────────────────────────
    try {
        Schema::create('kiosk_pricing', function ($table) {
            $table->id();
            $table->string('kiosk_id', 30)->nullable()->index();
            $table->enum('water_type', ['COLD', 'NORMAL']);
            $table->unsignedInteger('volume_ml');
            $table->unsignedInteger('price');
            $table->boolean('is_active')->default(true);
            $table->string('updated_by', 100)->nullable();
            $table->timestamps();
            $table->unique(['kiosk_id', 'water_type', 'volume_ml']);
        });
        $steps[] = ['ok', '✅', "Tabel <code>kiosk_pricing</code> berhasil dibuat."];
    } catch (\Throwable $e) {
        $steps[] = ['err', '❌', "Gagal membuat tabel: <code>" . htmlspecialchars($e->getMessage()) . "</code>"];
        $hasError = true;
    }

    // ─── STEP 3: Isi data default ───────────────────────────
    if (!$hasError) {
        $defaults = [
            ['kiosk_id' => null, 'water_type' => 'COLD',   'volume_ml' => 250,  'price' => 2000],
            ['kiosk_id' => null, 'water_type' => 'COLD',   'volume_ml' => 500,  'price' => 3500],
            ['kiosk_id' => null, 'water_type' => 'COLD',   'volume_ml' => 1000, 'price' => 6000],
            ['kiosk_id' => null, 'water_type' => 'NORMAL', 'volume_ml' => 250,  'price' => 1500],
            ['kiosk_id' => null, 'water_type' => 'NORMAL', 'volume_ml' => 500,  'price' => 2500],
            ['kiosk_id' => null, 'water_type' => 'NORMAL', 'volume_ml' => 1000, 'price' => 4500],
        ];

        try {
            foreach ($defaults as $row) {
                DB::table('kiosk_pricing')->insert(array_merge($row, [
                    'is_active'  => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]));
            }
            $steps[] = ['ok', '✅', "Berhasil mengisi <strong>6 data harga</strong> default (COLD & NORMAL)."];
        } catch (\Throwable $e) {
            $steps[] = ['err', '❌', "Gagal insert data: <code>" . htmlspecialchars($e->getMessage()) . "</code>"];
            $hasError = true;
        }
    }
}

// ─── Render steps ───────────────────────────────────────────
foreach ($steps as [$type, $icon, $msg]) {
    echo "<div class='step'><div class='icon {$type}'>{$icon}</div><div class='msg'>{$msg}</div></div>";
}

// ─── Tampilkan data yang ada ────────────────────────────────
if (Schema::hasTable('kiosk_pricing')) {
    $rows = DB::table('kiosk_pricing')->orderBy('water_type')->orderBy('volume_ml')->get();
    if ($rows->count() > 0) {
        echo "<table class='data-table'><thead><tr><th>ID</th><th>Kiosk</th><th>Tipe Air</th><th>Volume</th><th>Harga</th><th>Aktif</th></tr></thead><tbody>";
        foreach ($rows as $r) {
            $kiosk = $r->kiosk_id ?? '<em style="color:#64748b">Global</em>';
            $active = $r->is_active ? '✅' : '❌';
            $price = 'Rp ' . number_format($r->price, 0, ',', '.');
            echo "<tr><td>{$r->id}</td><td>{$kiosk}</td><td>{$r->water_type}</td><td>{$r->volume_ml}ml</td><td>{$price}</td><td>{$active}</td></tr>";
        }
        echo "</tbody></table>";
    }
}

// ─── Peringatan ─────────────────────────────────────────────
echo "<div class='warn'>⚠️ <strong>PENTING:</strong> Hapus file ini setelah migrasi berhasil untuk keamanan!<br><code>rm public/migrate_kiosk_pricing.php</code></div>";
echo "</div></body></html>";
