<?php
$html = file_get_contents('https://app.mesinbayar.com/fhk/update_iot.php');
if (!$html) {
    echo "Could not fetch update_iot.php\n";
    exit;
}

preg_match('/Tes Koneksi MQTT Live:[^<]+/u', $html, $mMqtt);
echo "MQTT Status: " . ($mMqtt[0] ?? 'NOT FOUND') . "\n";

preg_match('/Cache Laravel Dibersihkan:[^<]+/u', $html, $mCache);
echo "Cache Status: " . ($mCache[0] ?? 'No cache cleared') . "\n";

preg_match('/Status Database:<\/strong> ([^<]+)/u', $html, $mDb);
echo "DB Status: " . ($mDb[1] ?? 'NOT FOUND') . "\n";

preg_match_all('/📁 ([^<]+)<\/span>\s*<span class="([^"]+)">\s*([^<]+)/u', $html, $mFiles, PREG_SET_ORDER);
echo "Files Extracted:\n";
foreach ($mFiles as $f) {
    echo " - " . $f[1] . " : " . trim($f[3]) . "\n";
}
