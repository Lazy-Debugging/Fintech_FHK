@extends('layouts.admin_layout')

@section('title', 'Skema & Diagram Wiring IoT ESP32 - Fresh Hydration Kios')

@section('content')
<div class="space-y-6">

    <!-- Header Section -->
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <a href="{{ route('admin.dashboard') }}" class="text-xs text-slate-400 hover:text-cyan-400 transition flex items-center gap-1">
                    <i class="fa-solid fa-arrow-left"></i> Kembali ke Dashboard
                </a>
            </div>
            <h2 class="text-xl sm:text-2xl font-black text-white tracking-tight mt-1">
                ⚡ Skema Diagram Wiring Hardware IoT (ESP32-S3)
            </h2>
            <p class="text-xs text-slate-400 mt-1">
                Panduan resmi pemetaan pin GPIO mikrokontroler ESP32-S3 CAM 40-Pin untuk sensor, relay pompa, buzzer, dan LCD.
            </p>
        </div>

        <div class="flex items-center gap-2">
            <a href="{{ route('admin.simulator') }}" class="px-4 py-2.5 rounded-xl bg-purple-900/40 hover:bg-purple-800/50 text-purple-200 border border-purple-700/50 text-xs font-bold transition flex items-center gap-2">
                <i class="fa-solid fa-microchip text-purple-400"></i>
                <span>Buka Simulator ESP32</span>
            </a>
        </div>
    </div>

    <!-- Quick Pinout Table Card -->
    <div class="admin-card rounded-2xl p-5 space-y-4">
        <h3 class="font-extrabold text-white text-base flex items-center gap-2">
            <i class="fa-solid fa-list-check text-cyan-400"></i>
            <span>Tabel Konfigurasi Pinout GPIO ESP32-S3 Firmware v8</span>
        </h3>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs font-mono">
                <thead>
                    <tr class="border-b border-slate-800 text-slate-400 font-sans">
                        <th class="py-2.5 px-3">Komponen</th>
                        <th class="py-2.5 px-3">Pin Komponen</th>
                        <th class="py-2.5 px-3">Pin ESP32-S3</th>
                        <th class="py-2.5 px-3">Tegangan Daya</th>
                        <th class="py-2.5 px-3 font-sans">Keterangan / Fungsi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60 text-slate-300">
                    <!-- Ultrasonic HC-SR04 -->
                    <tr>
                        <td class="py-3 px-3 font-sans font-bold text-cyan-400" rowspan="2">HC-SR04 (Ultrasonic)</td>
                        <td class="py-2 px-3">TRIG</td>
                        <td class="py-2 px-3 font-bold text-white bg-slate-900/80 rounded">GPIO 1</td>
                        <td class="py-2 px-3 text-amber-400" rowspan="2">5V (VCC) & GND</td>
                        <td class="py-2 px-3 font-sans text-slate-400" rowspan="2">Deteksi keberadaan cangkir (jarak 12.0 - 22.0 cm) & proteksi stop (18.5 cm)</td>
                    </tr>
                    <tr>
                        <td class="py-2 px-3">ECHO</td>
                        <td class="py-2 px-3 font-bold text-white bg-slate-900/80 rounded">GPIO 2</td>
                    </tr>

                    <!-- Flow Sensor YF-S401 -->
                    <tr>
                        <td class="py-3 px-3 font-sans font-bold text-emerald-400">YF-S401 (Flow Sensor)</td>
                        <td class="py-2 px-3">Yellow (Signal)</td>
                        <td class="py-2 px-3 font-bold text-white bg-slate-900/80 rounded">GPIO 3</td>
                        <td class="py-2 px-3 text-amber-400">5V & GND</td>
                        <td class="py-2 px-3 font-sans text-slate-400">Penghitung pulsa aliran air (Interrupt RISING, 5880 pulsa/Liter)</td>
                    </tr>

                    <!-- Relay HW-307 / Pump -->
                    <tr>
                        <td class="py-3 px-3 font-sans font-bold text-purple-400">Relay HW-307 (Pompa Air)</td>
                        <td class="py-2 px-3">IN (Signal)</td>
                        <td class="py-2 px-3 font-bold text-white bg-slate-900/80 rounded">GPIO 21</td>
                        <td class="py-2 px-3 text-amber-400">5V & GND</td>
                        <td class="py-2 px-3 font-sans text-slate-400">Saklar on/off pompa air (Active-LOW: LOW=Menyala, HIGH=Mati)</td>
                    </tr>

                    <!-- Active Buzzer -->
                    <tr>
                        <td class="py-3 px-3 font-sans font-bold text-yellow-400">Buzzer Aktif</td>
                        <td class="py-2 px-3">Positive (+)</td>
                        <td class="py-2 px-3 font-bold text-white bg-slate-900/80 rounded">GPIO 47</td>
                        <td class="py-2 px-3 text-amber-400">GND</td>
                        <td class="py-2 px-3 font-sans text-slate-400">Indikator suara saat order diterima, peringatan gelas, dan selesai</td>
                    </tr>

                    <!-- LCD 16x2 I2C -->
                    <tr>
                        <td class="py-3 px-3 font-sans font-bold text-sky-400" rowspan="2">LCD 16x2 (I2C Adapter)</td>
                        <td class="py-2 px-3">SDA</td>
                        <td class="py-2 px-3 font-bold text-white bg-slate-900/80 rounded">GPIO 41</td>
                        <td class="py-2 px-3 text-amber-400" rowspan="2">5V & GND</td>
                        <td class="py-2 px-3 font-sans text-slate-400" rowspan="2">Layar status penuangan air (Alamat I2C: 0x27)</td>
                    </tr>
                    <tr>
                        <td class="py-2 px-3">SCL</td>
                        <td class="py-2 px-3 font-bold text-white bg-slate-900/80 rounded">GPIO 42</td>
                    </tr>

                    <!-- Camera GC2145 -->
                    <tr>
                        <td class="py-3 px-3 font-sans font-bold text-pink-400">Kamera GC2145 (Onboard)</td>
                        <td class="py-2 px-3">D0 - D7, XCLK, PCLK, VSYNC, HREF, SIOD, SIOC</td>
                        <td class="py-2 px-3 font-bold text-white bg-slate-900/80 rounded">GPIO 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 15, 16, 17, 18</td>
                        <td class="py-2 px-3 text-amber-400">3.3V</td>
                        <td class="py-2 px-3 font-sans text-slate-400">Kamera live stream video & scan QR token</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Power & Electrical Guidelines -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="admin-card rounded-2xl p-5 space-y-3">
            <h4 class="font-bold text-white text-sm flex items-center gap-2">
                <i class="fa-solid fa-plug text-amber-400"></i>
                <span>Panduan Catu Daya (Power Supply)</span>
            </h4>
            <ul class="text-xs text-slate-300 space-y-2 list-disc list-inside">
                <li>Gunakan adaptor minimal <b>5V 2A</b> atau <b>5V 3A</b> yang stabil untuk menyalakan modul ESP32-S3.</li>
                <li>Pompa air 12V DC wajib menggunakan adaptor 12V terpisah dengan <b>Common Ground (GND dihubungkan bersama)</b> dengan ESP32.</li>
                <li>Hindari mengambil daya 5V pompa langsung dari pin output ESP32 untuk mencegah drop tegangan (*brownout* / restart `rst:0x1`).</li>
            </ul>
        </div>

        <div class="admin-card rounded-2xl p-5 space-y-3">
            <h4 class="font-bold text-white text-sm flex items-center gap-2">
                <i class="fa-solid fa-network-wired text-cyan-400"></i>
                <span>Protokol Komunikasi IoT</span>
            </h4>
            <ul class="text-xs text-slate-300 space-y-2 list-disc list-inside">
                <li><b>HTTP Polling Port 80:</b> ESP32 memanggil <code>/api/iot/kiosk/{kioskId}/command</code> setiap 2 detik.</li>
                <li><b>Laporan Selesai:</b> ESP32 memanggil <code>/api/iot/kiosk/{kioskId}/dispense-complete</code> via HTTP POST.</li>
                <li><b>Kecepatan Kamera:</b> Streaming 25+ FPS menggunakan JPEG quality 20 via port 81/web server.</li>
            </ul>
        </div>
    </div>

</div>
@endsection
