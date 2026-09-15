# Fresh Hydration Kios (FHK) - Web PWA & IoT ESP32

[![Laravel](https://img.shields.io/badge/Laravel-12.x-FF2D20?style=flat&logo=laravel)](https://laravel.com)
[![PHP](https://img.shields.io/badge/PHP-8.2%2B-777BB4?style=flat&logo=php)](https://php.net)
[![PWA](https://img.shields.io/badge/PWA-Enabled-5A0FC8?style=flat&logo=pwa)](https://web.dev/progressive-web-apps/)
[![ESP32](https://img.shields.io/badge/Hardware-ESP32%20IoT-E7352C?style=flat&logo=espressif)](https://espressif.com)
[![AiYO](https://img.shields.io/badge/Payment-AiYO%20QRIS%20Dynamic-06B6D4?style=flat)](https://aiyo.id)

**Fresh Hydration Kios (FHK)** adalah stasiun dispenser air minum publik higienis pintar berbasis Progressive Web App (PWA) dengan sterilisasi lampu UV-C otomatis dan mikrokontroler ESP32. Pengguna dapat memilih suhu air (Dingin / Normal) dan volume (250ml / 500ml / 1000ml) pada layar sentuh kios, membayar menggunakan AiYO QRIS Dinamis, dan menerima struk digital langsung di layar atau ponsel mereka.

---

## 🌟 Fitur Utama

1. **Frontend Layar Sentuh Kios (PWA)**:
   - Antarmuka *Dark Glassmorphism* modern dengan tipografi *Plus Jakarta Sans*.
   - Pilihan suhu air: **Cold (~7.5°C)** dan **Normal (~25°C)**.
   - Pilihan volume air: **250 ml** (Cup), **500 ml** (Tumbler), dan **1000 ml** (Botol 1L).
   - Layar QRIS dinamis dengan countdown waktu 15 menit & verifikasi *auto-polling*.
   - Animasi penuangan air dan sterilisasi UV chamber realtime.
   - Struk digital interaktif dengan QR Code untuk disimpan ke smartphone pengunjung.
   - Siap mode *Standalone / Kiosk Fullscreen* melalui `manifest.json` dan `sw.js`.

2. **Integrasi AiYO Bills Invoice (DBI) Gateway**:
   - `App\Services\AiyoPaymentService`: Service class terstruktur dengan manajemen OAuth token caching (50 menit).
   - Generator tanda tangan kriptografis **HMAC-SHA256** (`x-aiyo-key` & `x-aiyo-signature`).
   - Validasi status invoice secara realtime.
   - Mode pengujian simulasi cepat untuk demonstrasi.

3. **Integrasi Perangkat Keras IoT ESP32**:
   - **Relay UV-C Sterilizer (GPIO 16)**: Siklus sterilisasi nozzle otomatis sebelum dan sesudah penuangan air.
   - **Solenoid Valve Air Dingin (GPIO 17)** & **Solenoid Valve Normal (GPIO 18)**.
   - **Water Flow Sensor YF-S201 (GPIO 19)**: Akurasi takaran air dengan kalkulasi interupsi pulsa air.
   - **Ultrasonic Fluid Level Sensor HC-SR04 / JSN-SR04T (GPIO 21 & 22)**: Pengukuran ketinggian air tangki penampungan utama.
   - Endpoint komunikasi REST API: `/api/iot/kiosk/{id}/command`, `/dispense-complete`, `/telemetry`.

4. **Dashboard Pemantauan & Maintenance**:
   - Indikator ketinggian tangki air grafis silinder (*Cylindrical Tank Gauge*).
   - Pelacakan masa pakai 4 elemen filter: *Sediment Filter*, *Carbon Block*, *Ultrafiltration Membrane*, dan *Lampu UV-C Germicidal*.
   - Tombol penggantian/reset filter baru.
   - Penjadwalan sterilisasi UV otomatis harian (*ESP32 Scheduled Routine*).
   - Simulator hardware interaktif berbasis web (`/admin/simulator`).

---

## 🔌 Pinout Hardware ESP32

| Komponen Hardware | Pin GPIO ESP32 | Fungsi |
| :--- | :--- | :--- |
| **Relay UV Sterilizer** | `GPIO 16` | Mengaktifkan modul lampu radiasi kuman UV-C |
| **Solenoid Valve Dingin** | `GPIO 17` | Membuka jalur air dingin dari pendingin chiller |
| **Solenoid Valve Normal** | `GPIO 18` | Membuka jalur air suhu ruangan |
| **Flow Meter YF-S201** | `GPIO 19` (Interrupt) | Menghitung volume air masuk (±450 pulsa/liter) |
| **Ultrasonic Trigger** | `GPIO 21` | Memicu sinyal pulsa ultrasonik ke tangki |
| **Ultrasonic Echo** | `GPIO 22` | Menerima pantulan jarak permukaan air |

---

## ⚙️ Cara Menjalankan Aplikasi

### 1. Prasyarat
- PHP >= 8.2 (dengan ekstensi `pdo_sqlite`, `curl`, `mbstring`, `fileinfo`)
- Composer
- Node.js & NPM (opsional)

### 2. Instalasi
```bash
# Clone repository
git clone https://github.com/Lazy-Debugging/Fintech_FHK.git
cd Fintech_FHK

# Install dependensi PHP
composer install

# Salin file konfigurasi environment
cp .env.example .env

# Generate Application Key
php artisan key:generate

# Jalankan migrasi dan seeder database
php artisan migrate --seed

# Jalankan development server
php artisan serve
```

Aplikasi dapat diakses melalui browser:
- **Layar Kios PWA**: `http://127.0.0.1:8000/`
- **Admin Dashboard**: `http://127.0.0.1:8000/admin`
- **ESP32 Simulator**: `http://127.0.0.1:8000/admin/simulator`

---

## 🔒 Konfigurasi AiYO Payment Gateway

Kredensial AiYO dikonfigurasi pada file `.env`:
```env
AIYO_HOST="https://api-bills-invoice.aiyo.id"
AIYO_USERNAME="FRESH_HYDRATION_KIOS"
AIYO_PASSWORD="pass_7A9uubvhF0XQZ7gePewlItKt3Uzg2I"
AIYO_BILL_MASTER_ID="uxGSWGOqpeqLaG5Qn1DH"
AIYO_API_KEY="key_SxcKMQ8cMbckjRMLvNCy1hNqUMGSo4"
AIYO_API_SECRET="secret_WU3Ba45FwWE9sDe4uxUarvM4P6KMB4"
DEFAULT_KIOSK_ID="FHK-JAKARTA-01"
KIOSK_API_SECRET="fhk_esp32_secret_token_2026"
```

---

## 📄 Lisensi

Proyek ini dilisensikan di bawah lisensi [MIT](LICENSE).
