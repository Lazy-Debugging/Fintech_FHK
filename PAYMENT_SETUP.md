# Setup QRIS AiYO dan callback lokal

## Perbaikan yang sudah diterapkan

- Invoice sekarang meminta metode `QRIS` dengan `bankCode` `503`, sesuai materi kuliah.
- Aplikasi tidak lagi membuat string QRIS sendiri. Payload QR hanya dipakai bila benar-benar diberikan oleh AiYO.
- Jika respons hanya memiliki `invoiceURL`, layar kios menampilkan tautan dan QR untuk membuka halaman pembayaran resmi AiYO. QR itu bukan QRIS dan tidak diberi label sebagai QRIS.
- Callback tersedia pada `POST /api/aiyo/callback` dan `POST /callback`.

## Konfigurasi kredensial

Simpan kredensial yang diberikan AiYO hanya di `.env` (jangan di `config/aiyo.php`, README, atau Git):

```env
AIYO_HOST=https://api-bills-invoice.aiyo.id
AIYO_USERNAME=...
AIYO_PASSWORD=...
AIYO_BILL_MASTER_ID=...
AIYO_API_KEY=...
AIYO_API_SECRET=...
```

Lalu jalankan:

```powershell
php artisan config:clear
php artisan migrate
php artisan serve
```

Jika port `8000` tidak tersedia, jalankan `php -S 127.0.0.1:18080 -t public` dari folder proyek dan gunakan port `18080` pada perintah ngrok.

## Ngrok untuk callback lokal

FileZilla tidak membuat komputer lokal dapat diakses internet; ia hanya dipakai untuk upload ke hosting. Untuk pengujian di Laragon gunakan ngrok:

```powershell
ngrok http 8000
```

Untuk server pada port `18080`, gantilah menjadi `ngrok http 18080`.

Salin URL HTTPS yang muncul, lalu daftarkan pada dashboard AiYO sebagai:

```text
https://URL-NGROK-ANDA.ngrok-free.app/api/aiyo/callback
```

Materi juga mencantumkan URL kelas `/app/fhk/callback/`; route `POST /callback` disediakan untuk deployment aplikasi pada path tersebut. Jika dashboard AiYO sudah mengunci URL kelas itu, unggah aplikasi ke server yang melayani domain/path tersebut; ngrok tidak dapat menggantikan URL yang sudah dikunci.

Sesudah callback diterima, aplikasi memverifikasi ulang status invoice ke AiYO sebelum mengubah transaksi menjadi `PAID`; layar kios tetap melakukan polling sebagai cadangan. Periksa log penerimaan callback melalui `storage/logs/laravel.log`.

Jika mata kuliah Anda menetapkan URL callback `https://mesinbayar.com/app/fhk/callback/`, pakai URL yang ditetapkan dosen/dashboard tersebut. Ngrok hanya cocok apabila provider mengizinkan Anda mengganti URL callback ke URL ngrok.

## Menguji callback secara langsung

Gunakan invoice yang sudah dibuat dan dibayar. Dari PowerShell, kirim notifikasi uji ke server lokal:

```powershell
$body = @{ invoiceId = 'INVOICE_ID_DARI_AIYO'; invoiceStatus = 'PAID' } | ConvertTo-Json
Invoke-RestMethod -Method Post -Uri 'http://127.0.0.1:18080/callback' -ContentType 'application/json' -Body $body
```

Pantau penerimaannya pada terminal lain:

```powershell
Get-Content .\storage\logs\laravel.log -Wait
```

Respons `received: true` membuktikan endpoint menerima callback. Status database hanya berubah menjadi `PAID` jika pengecekan ulang ke AiYO juga menyatakan invoice telah dibayar. Untuk callback asli dari AiYO, gunakan URL HTTPS publik yang sudah didaftarkan pada dashboard AiYO, lalu lakukan pembayaran invoice tersebut.
