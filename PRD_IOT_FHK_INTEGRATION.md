# PRODUCT REQUIREMENTS DOCUMENT (PRD)
## Integrasi IoT ESP32-S3 CAM & Backend Laravel (Fresh Hydration Kiosk)

- **Proyek:** Fresh Hydration Kiosk (FHK)
- **Modul:** Integrasi Smart Water Dispenser IoT dengan Sistem Fintech Laravel
- **Target Hardware:** ESP32-S3 CAM (GC2145, HC-SR04, YF-S401, LCD I2C 16x2, Relay HW-307, Active Buzzer)
- **Target Backend:** Laravel 10.x (`Fintech_FHK`)
- **Protokol Utama:** MQTT (Port 1883) + Fallback HTTP REST

---

## 1. Latar Belakang & Tujuan (Problem Statement & Goals)

### 1.1 Latar Belakang
Firmware ESP32-S3 saat ini bekerja dalam mode **stand-alone free refill**: pompa otomatis aktif saat gelas diletakkan di jarak 9–15 cm tanpa validasi apakah pengguna telah memesan atau membayar. Sementara di sisi web Laravel, telah tersedia sistem pemesanan air minum berbasis volume (250 mL, 500 mL, 1000 mL) dengan pembayaran QRIS (AiYO Gateway).

### 1.2 Tujuan
1. **Transaction-Driven Dispenser:** Pompa air hanya akan aktif jika ada instruksi resmi dari server Laravel berdasarkan order yang sudah berstatus `PAID` / `QUEUED`.
2. **Precision Pouring by Volume:** Penuangan air dikontrol presisi berdasarkan volume yang dipesan menggunakan kalkulasi pulsa flow sensor YF-S401.
3. **Multi-layer Safety Guard:** Sensor ultrasonik HC-SR04 beralih fungsi menjadi sensor verifikasi keberadaan gelas dan *cut-off* otomatis jika air sudah mencapai batas bibir gelas (<= 5 cm).
4. **Real-time Bi-directional Feedback:** Status penuangan air, volume yang sudah keluar, dan estimasi waktu selesai dapat dipantau langsung di layar Kios / HP pelanggan secara real-time melalui MQTT.

---

## 2. Arsitektur Hardware & Pinout ESP32-S3 CAM

| Komponen | Pin GPIO | Mode / Catatan Penting |
| :--- | :--- | :--- |
| **LCD 16x2 SDA** | GPIO 1 | I2C Software (Bit-banging), Open-Drain |
| **LCD 16x2 SCL** | GPIO 2 | I2C Software (Bit-banging), Open-Drain |
| **HC-SR04 TRIG** | GPIO 14 | Output pulsa trigger ultrasonic |
| **HC-SR04 ECHO** | GPIO 21 | Input interrupt microsecond timer *(Wajib Voltage Divider 5V -> 3.3V)* |
| **Relay Pompa** | GPIO 47 | Active-LOW (LOW = ON, HIGH = OFF) |
| **Flow Sensor (YF-S401)** | GPIO 41 | Input interrupt counter *(Wajib Voltage Divider 5V -> 3.3V)* |
| **Active Buzzer** | GPIO 42 | Active-HIGH (HIGH = BEEP, LOW = MUTE) |
| **GC2145 Camera** | GPIO 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 15, 16, 17, 18 | Dedikasi kamera GC2145 (Core 0, MJPEG Stream Port 81) |

---

## 3. Topologi Jaringan & Arsitektur MQTT

```
 [Pelanggan / Layar Kios]
           |
      HTTP / Web
           v
   [Laravel Backend] <=======> [MQTT Broker: Mosquitto]
   (AiYO QRIS, Order)               (Port 1883)
                                         ^
                                         | WiFi (OMBRA COFFEE_5G)
                                         v
                            [ESP32-S3 Smart Dispenser]
                           /            |            \
                   [HC-SR04]       [YF-S401]       [Relay/Pump]
```

- **Broker Host:** IP Komputer / Server Laragon (default port `1883`)
- **Autentikasi:** Anonymous / Kiosk Secret Token
- **Client ID ESP32:** `FHK-ESP32-KIOSK-01`
- **Client ID Laravel:** `FHK-LARAVEL-BACKEND`

---

## 4. Spesifikasi Kontrak Komunikasi MQTT

### 4.1 Struktur Topik (Topic Structure)
Semua topik diawali dengan prefix: `fhk/kiosk/{kiosk_id}/` (contoh: `fhk/kiosk/KIOSK-01/`).

| No | Topik | Publisher | Subscriber | QoS | Deskripsi |
| :-- | :--- | :--- | :--- | :--- | :--- |
| 1 | `fhk/kiosk/{id}/command` | Laravel | ESP32 | 1 | Perintah penuangan air (`DISPENSE` / `ABORT`) |
| 2 | `fhk/kiosk/{id}/status` | ESP32 | Laravel / Frontend | 0 | Progres penuangan real-time (tiap 500 ms) |
| 3 | `fhk/kiosk/{id}/complete` | ESP32 | Laravel | 1 | Laporan akhir penuangan tuntas/gagal |
| 4 | `fhk/kiosk/{id}/telemetry` | ESP32 | Laravel | 0 | Heartbeat status hardware & sensor berkala |

---

### 4.2 Spesifikasi Payload Data (JSON Contract)

#### A. Perintah Penuangan (`command`)
Dipublish oleh Laravel saat order lunas:
```json
{
  "command": "DISPENSE",
  "job_id": "FHK26092512001",
  "water_type": "NORMAL",
  "volume_ml": 500,
  "max_cup_distance_cm": 15.0,
  "min_cup_distance_cm": 9.0,
  "stop_distance_cm": 5.0,
  "timeout_sec": 60
}
```

#### B. Status & Progres Real-time (`status`)
Dipublish oleh ESP32 setiap 500 ms saat pengisian:
```json
{
  "job_id": "FHK26092512001",
  "state": "FILLING",
  "dispensed_ml": 250,
  "target_ml": 500,
  "progress_pct": 50.0,
  "flow_lpm": 1.20,
  "distance_cm": 8.5,
  "pump": true
}
```

#### C. Laporan Selesai (`complete`)
Dipublish oleh ESP32 saat pengisian tuntas atau terjadi pembatalan pengaman:
```json
{
  "job_id": "FHK26092512001",
  "status": "SUCCESS",
  "dispensed_ml": 502,
  "target_ml": 500,
  "duration_ms": 23500,
  "fault_code": null,
  "message": "Penuangan tuntas."
}
```
*Catatan fault code jika terjadi kendala:*
- `CUP_REMOVED` : Gelas diangkat sebelum selesai.
- `FULL_DISTANCE` : Air sudah mencapai 5 cm dari sensor sebelum volume target habis.
- `NO_FLOW` : Pompa menyala tapi tidak ada aliran air terdeteksi (pompa macet / air habis).
- `TIMEOUT` : Waktu maksimum pengisian terlampaui.

#### D. Telemetri & Heartbeat (`telemetry`)
Dipublish oleh ESP32 setiap 15 detik jika IDLE:
```json
{
  "kiosk_id": "KIOSK-01",
  "state": "IDLE",
  "wifi_rssi": -62,
  "uptime_sec": 3600,
  "camera_ready": true,
  "distance_cm": 25.4
}
```

---

## 5. State Machine Firmware ESP32-S3

1. **`ST_IDLE` (Siaga):**
   - Layar LCD: `"FHK KIOSK SIAP" / "Silakan Pesan"`
   - Pompa: **OFF**
   - Mendengarkan pesan di topik `fhk/kiosk/{id}/command`.

2. **`ST_AWAITING_CUP` (Menunggu Gelas):**
   - Terpicu saat menerima perintah `DISPENSE` dari Laravel.
   - Layar LCD: `"Order: 500 mL" / "Letakkan Gelas"`
   - Buzzer: 2x bip pendek.
   - Sensor HC-SR04 memverifikasi gelas di rentang 9–15 cm selama 500 ms.
   - Jika dalam 60 detik gelas tidak diletakkan &rarr; kirim timeout ke server dan kembali ke `ST_IDLE`.

3. **`ST_FILLING` (Penuangan Air):**
   - Relay: **ON** (Pompa aktif).
   - Layar LCD: `"Isi: 250/500 mL" / "D: 8.5cm Penuh"`
   - Sensor YF-S401 mengakumulasi pulsa aliran air.
   - Setiap 500 ms kirim data progres ke MQTT `status`.
   - **Kondisi Berhenti:**
     - Akumulasi mL >= Target mL &rarr; transisi ke `ST_DONE`.
     - Jarak air <= 5 cm (2x pembacaan beruntun) &rarr; transisi ke `ST_DONE`.
     - Gelas terangkat (Jarak > 20 cm) &rarr; transisi ke `ST_FAULT` (Safety Cut-off).
     - Tidak ada aliran air selama 5 detik &rarr; transisi ke `ST_FAULT` (`NO_FLOW`).

4. **`ST_DONE` (Selesai):**
   - Relay: **OFF** (Pompa mati seketika).
   - Tunggu 2.5 detik untuk menghitung sisa tetesan air (*settling volume*).
   - Buzzer: 3x bip panjang tanda selesai.
   - Layar LCD: `"Selesai! 500 mL" / "Ambil Gelas Anda"`
   - Kirim laporan ke MQTT `complete`.
   - Menunggu gelas diangkat (> 18 cm), lalu kembali ke `ST_IDLE`.

5. **`ST_FAULT` (Pengaman Terpicu):**
   - Relay: **OFF**.
   - Layar LCD: `"STOP: ERR_CODE" / "Ambil Gelas"`
   - Kirim laporan status kegagalan ke MQTT `complete`.
   - Otomatis reset ke `ST_IDLE` setelah gelas disingkirkan.

---

## 6. Arsitektur Sisi Backend Laravel

### 6.1 Package Dependency
- `php-mqtt/client` versi `^2.0` (kompatibel penuh dengan PHP 8.1+ dan Laravel 10).

### 6.2 File & Komponen yang Akan Dibuat / Diperbarui:
1. **Konfigurasi Lingkungan (`.env` & `config/mqtt.php`):**
   - Parameter koneksi broker MQTT (`MQTT_HOST`, `MQTT_PORT`, `MQTT_AUTH_USER`, `MQTT_AUTH_PASSWORD`).
2. **Service MQTT (`App\Services\MqttKioskService.php`):**
   - Method `dispatchDispenseJob($transaksi)` untuk publish JSON `command`.
3. **Trigger Otomatis di Controller:**
   - [OrderController.php](file:///c:/laragon/www/fintechlaravel/Fintech_FHK/app/Http/Controllers/Kiosk/OrderController.php) / [AiyoCallbackController.php](file:///c:/laragon/www/fintechlaravel/Fintech_FHK/app/Http/Controllers/Payment/AiyoCallbackController.php):
   - Begitu invoice lunas (`PAID`), langsung panggil `MqttKioskService::dispatchDispenseJob($transaksi)`.
4. **Artisan Daemon Listener (`php artisan kiosk:mqtt-listen`):**
   - Command background yang bertugas *subscribe* topik `complete` dan `telemetry`.
   - Saat menerima laporan `complete`, otomatis update status transaksi di database menjadi `COMPLETED` dan kurangi stok tangki air kiosk.

---

## 7. Rencana Kerja Eksekusi (Implementation Steps)

- [ ] **Langkah 1: Setup Backend Laravel**
  - Pasang `php-mqtt/client` via Composer.
  - Buat `config/mqtt.php` dan `MqttKioskService.php`.
  - Sambungkan trigger penuangan air pada callback pembayaran AiYO & fungsi scan redeem.
  - Buat Artisan daemon `kiosk:mqtt-listen`.

- [ ] **Langkah 2: Update Firmware ESP32-S3**
  - Sertakan library `PubSubClient.h` pada firmware.
  - Tambahkan konfigurasi IP Broker MQTT & Task MQTT di Core 0.
  - Sesuaikan state machine penuangan air berbasis instruksi order.
  - Uji proteksi tumpah (HC-SR04 <= 5 cm) dan sensor lepas.

- [ ] **Langkah 3: Integrasi End-to-End & Uji Coba**
  - Buat pesanan test di Web Kiosk FHK.
  - Lakukan simulasi pembayaran QRIS.
  - Verifikasi pesan MQTT diterima ESP32, pompa menyala dengan volume yang sesuai, dan status web otomatis berubah ke *COMPLETED*.

