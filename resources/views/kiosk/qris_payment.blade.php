@php
    $targetUrl = (!empty($transaksi->invoice_url) && str_contains($transaksi->invoice_url, 'aiyo.id'))
        ? $transaksi->invoice_url
        : "https://bills-invoice.aiyo.id/bills/invoice/{$transaksi->invoiceId}?accessToken=" . urlencode($transaksi->aiyo_access_token);
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="refresh" content="0;url={{ $targetUrl }}">
    <title>Mengalihkan ke AiYO QRIS...</title>
    <script>
        window.location.replace(@json($targetUrl));
    </script>
</head>
<body style="background:#0a0f1d; color:#e2e8f0; font-family:sans-serif; text-align:center; padding:60px 20px;">
    <div style="max-width:400px; margin:0 auto; background:#111827; border:1px solid #1e293b; border-radius:16px; padding:30px;">
        <h3 style="color:#06b6d4; margin-top:0;">Membuka AiYO Payment Gateway...</h3>
        <p style="font-size:14px; color:#94a3b8;">Sedang mengalihkan Anda ke halaman pembayaran resmi AiYO.</p>
        <p style="margin-top:20px;">
            <a href="{{ $targetUrl }}" style="display:inline-block; padding:10px 20px; background:#06b6d4; color:#0a0f1d; font-weight:bold; border-radius:10px; text-decoration:none;">Buka Halaman AiYO &rarr;</a>
        </p>
    </div>
</body>
</html>
