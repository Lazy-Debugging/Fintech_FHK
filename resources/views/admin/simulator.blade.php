@extends('layouts.admin_layout')

@section('title', 'ESP32 Microcontroller Simulator & Firmware - FHK')

@section('content')
<div class="space-y-5 sm:space-y-8">

    <!-- Header -->
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
            <h2 class="text-xl sm:text-2xl font-black text-white tracking-tight flex items-start sm:items-center gap-3">
                <i class="fa-solid fa-microchip text-purple-400"></i>
                <span>ESP32 Hardware IoT Simulator & Firmware</span>
            </h2>
            <p class="text-xs text-slate-400 mt-1">
                Uji interaksi perangkat keras (Relay UV, Solenoid Valve Normal/Cold, Flow Meter YF-S201, Ultrasonic HC-SR04) langsung dari browser.
            </p>
        </div>

        <div class="flex items-center gap-2">
            <span class="px-3 py-1 rounded-full bg-slate-800 text-slate-300 text-xs font-mono">
                Target Kios: <strong class="text-cyan-400">{{ $kiosk->id }}</strong>
            </span>
        </div>
    </div>

    <!-- Simulator Interactive Workspace -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        <!-- Left: Virtual ESP32 Hardware Board -->
        <div class="admin-card rounded-2xl p-4 sm:p-6 space-y-6">
            <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                <h3 class="font-extrabold text-white text-sm flex items-center gap-2">
                    <i class="fa-solid fa-server text-cyan-400"></i>
                    <span>Papan Mikrokontroler Virtual</span>
                </h3>
                <span id="esp-status-badge" class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-800 text-slate-400">
                    STANDBY
                </span>
            </div>

            <!-- Virtual Pinout & Relays Display -->
            <div class="space-y-4">
                
                <!-- Relay UV Sterilizer -->
                <div class="p-3.5 rounded-xl bg-slate-900/80 border border-slate-800 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div id="relay-uv-icon" class="w-9 h-9 rounded-lg bg-slate-800 flex items-center justify-center text-slate-500 text-base">
                            <i class="fa-solid fa-radiation"></i>
                        </div>
                        <div>
                            <div class="font-bold text-white text-xs">Relay UV Sterilizer (GPIO 16)</div>
                            <div class="text-[10px] text-slate-400">Sterilisasi Pre & Post Penuangan</div>
                        </div>
                    </div>
                    <span id="relay-uv-state" class="text-xs font-mono font-bold text-slate-500">OFF</span>
                </div>

                <!-- Valve Air Dingin -->
                <div class="p-3.5 rounded-xl bg-slate-900/80 border border-slate-800 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div id="valve-cold-icon" class="w-9 h-9 rounded-lg bg-slate-800 flex items-center justify-center text-slate-500 text-base">
                            <i class="fa-solid fa-snowflake"></i>
                        </div>
                        <div>
                            <div class="font-bold text-white text-xs">Solenoid Valve Dingin (GPIO 17)</div>
                            <div class="text-[10px] text-slate-400">Aliran Jalur Chiller</div>
                        </div>
                    </div>
                    <span id="valve-cold-state" class="text-xs font-mono font-bold text-slate-500">CLOSED</span>
                </div>

                <!-- Valve Air Normal -->
                <div class="p-3.5 rounded-xl bg-slate-900/80 border border-slate-800 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div id="valve-normal-icon" class="w-9 h-9 rounded-lg bg-slate-800 flex items-center justify-center text-slate-500 text-base">
                            <i class="fa-solid fa-droplet"></i>
                        </div>
                        <div>
                            <div class="font-bold text-white text-xs">Solenoid Valve Normal (GPIO 18)</div>
                            <div class="text-[10px] text-slate-400">Aliran Suhu Ruangan</div>
                        </div>
                    </div>
                    <span id="valve-normal-state" class="text-xs font-mono font-bold text-slate-500">CLOSED</span>
                </div>

                <!-- Flow Sensor Pulses Counter -->
                <div class="p-3.5 rounded-xl bg-slate-900/80 border border-slate-800 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-lg bg-slate-800 flex items-center justify-center text-cyan-400 text-base">
                            <i class="fa-solid fa-gauge-high"></i>
                        </div>
                        <div>
                            <div class="font-bold text-white text-xs">Flow Sensor YF-S201 (GPIO 19)</div>
                            <div class="text-[10px] text-slate-400">Interupsi Pulsa Air Masuk</div>
                        </div>
                    </div>
                    <span id="flow-pulse-count" class="text-xs font-mono font-bold text-cyan-400">0 Pulsa</span>
                </div>

            </div>

            <!-- Polling Action Button -->
            <div class="space-y-2 pt-2">
                <button onclick="pollEsp32Command()" id="btn-poll-cmd"
                        class="w-full py-3 rounded-xl bg-cyan-600 hover:bg-cyan-500 text-slate-950 font-bold text-xs shadow-lg shadow-cyan-950/50 transition flex items-center justify-center gap-2">
                    <i class="fa-solid fa-cloud-arrow-down"></i>
                    <span>Tarik Perintah Dispense (GET /command)</span>
                </button>
            </div>
        </div>

        <!-- Middle: Ultrasonic Water Level Telemetry Simulator -->
        <div class="admin-card rounded-2xl p-4 sm:p-6 space-y-6">
            <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                <h3 class="font-extrabold text-white text-sm flex items-center gap-2">
                    <i class="fa-solid fa-wave-square text-cyan-400"></i>
                    <span>Simulator Sensor Ultrasonik Tangki</span>
                </h3>
                <span class="text-[10px] text-slate-400">HC-SR04 (GPIO 21 & 22)</span>
            </div>

            <p class="text-xs text-slate-400">
                Geser pengatur di bawah untuk mensimulasikan ketinggian air di dalam tangki kios, lalu kirimkan telemetri ke server.
            </p>

            <div class="space-y-4">
                <div class="flex justify-between items-center text-xs">
                    <span class="text-slate-400">Level Air Tangki:</span>
                    <span class="text-lg font-bold font-mono text-cyan-400" id="slider-val-text">{{ $kiosk->current_water_level_pct }}%</span>
                </div>

                <input type="range" id="water-level-slider" min="0" max="100" value="{{ $kiosk->current_water_level_pct }}"
                       oninput="onSliderChange(this.value)"
                       class="w-full accent-cyan-400 cursor-pointer">

                <div class="grid grid-cols-2 gap-3 text-xs bg-slate-900/60 p-3 rounded-xl border border-slate-800">
                    <div>
                        <div class="text-[10px] text-slate-400">Jarak Pantul Sensor:</div>
                        <div class="font-mono font-bold text-white" id="dist-cm-text">12.5 cm</div>
                    </div>
                    <div>
                        <div class="text-[10px] text-slate-400">Estimasi Volume:</div>
                        <div class="font-mono font-bold text-white" id="vol-liter-text">44.2 L</div>
                    </div>
                </div>

                <button onclick="sendTelemetry()" id="btn-send-telemetry"
                        class="w-full py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 font-bold text-xs transition flex items-center justify-center gap-2">
                    <i class="fa-solid fa-paper-plane text-cyan-400"></i>
                    <span>Kirim Telemetri ke Backend</span>
                </button>
            </div>

            <!-- Terminal Log Window -->
            <div class="space-y-1">
                <div class="text-[11px] text-slate-400 font-bold">Log Komunikasi Serial ESP32:</div>
                <div id="terminal-box" class="h-36 bg-slate-950 rounded-xl p-3 font-mono text-[10px] text-emerald-400 overflow-y-auto border border-slate-800/80 space-y-0.5">
                    <div>[SYS] ESP32 Boot OK. WiFi connected to 'FHK-Kiosk-AP'.</div>
                    <div>[SYS] Ready to poll backend commands.</div>
                </div>
            </div>
        </div>

        <!-- Right: Arduino / ESP32 C++ Code Reference -->
        <div class="admin-card rounded-2xl p-4 sm:p-6 space-y-4 flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                    <h3 class="font-extrabold text-white text-sm flex items-center gap-2">
                        <i class="fa-solid fa-code text-cyan-400"></i>
                        <span>Firmware ESP32 (Arduino C++)</span>
                    </h3>
                    <span class="text-[10px] text-slate-400">REST Client</span>
                </div>
                <p class="text-xs text-slate-400 mt-2">
                    Kode siap flash ke board ESP32 (menggunakan Arduino IDE / PlatformIO):
                </p>
            </div>

            <div class="bg-slate-950 p-3 rounded-xl border border-slate-800/80 overflow-x-auto text-[10px] font-mono text-slate-300 max-h-72">
<pre><code>#include &lt;WiFi.h&gt;
#include &lt;HTTPClient.h&gt;
#include &lt;ArduinoJson.h&gt;

const char* ssid = "FHK_WIFI";
const char* pass = "kios_password";
const char* serverUrl = "http://192.168.1.10:8000";
const char* kioskId   = "{{ $kiosk->id }}";
const char* secretKey = "{{ $kiosk->api_secret_token }}";

#define PIN_RELAY_UV    16
#define PIN_VALVE_COLD  17
#define PIN_VALVE_NORM  18
#define PIN_FLOW_SENSOR 19

volatile int pulseCount = 0;
void IRAM_ATTR flowPulseISR() { pulseCount++; }

void setup() {
  Serial.begin(115200);
  pinMode(PIN_RELAY_UV, OUTPUT);
  pinMode(PIN_VALVE_COLD, OUTPUT);
  pinMode(PIN_VALVE_NORM, OUTPUT);
  pinMode(PIN_FLOW_SENSOR, INPUT_PULLUP);
  attachInterrupt(digitalPinToInterrupt(PIN_FLOW_SENSOR), flowPulseISR, RISING);
  WiFi.begin(ssid, pass);
}

void loop() {
  // Polling /api/iot/kiosk/{id}/command setiap 2 detik
  delay(2000);
}</code></pre>
            </div>

            <button onclick="copyFirmware()" class="w-full py-2.5 rounded-xl bg-purple-900/40 hover:bg-purple-800/50 text-purple-200 border border-purple-700/60 font-bold text-xs transition flex items-center justify-center gap-2">
                <i class="fa-regular fa-copy"></i>
                <span>Salin Template Firmware ESP32</span>
            </button>
        </div>

    </div>

</div>
@endsection

@section('scripts')
<script>
    const kioskId = "{{ $kiosk->id }}";
    const secret = "{{ $kiosk->api_secret_token }}";

    function logTerminal(msg) {
        const box = document.getElementById('terminal-box');
        const line = document.createElement('div');
        const now = new Date().toLocaleTimeString('id-ID');
        line.textContent = `[${now}] ${msg}`;
        box.appendChild(line);
        box.scrollTop = box.scrollHeight;
    }

    function onSliderChange(val) {
        document.getElementById('slider-val-text').textContent = val + '%';
        const dist = ((100 - val) * 0.4).toFixed(1);
        document.getElementById('dist-cm-text').textContent = dist + ' cm';
        const liters = (({{ $kiosk->tank_capacity_liters }} * val) / 100).toFixed(1);
        document.getElementById('vol-liter-text').textContent = liters + ' L';
    }

    async function sendTelemetry() {
        const pct = parseFloat(document.getElementById('water-level-slider').value);
        const cm = parseFloat(((100 - pct) * 0.4).toFixed(1));

        logTerminal(`Mengirim telemetri: level=${pct}%, distance=${cm}cm`);

        try {
            const res = await fetch(`/api/iot/kiosk/${kioskId}/telemetry`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({
                    water_level_cm: cm,
                    water_level_pct: pct,
                    temperature_celsius: 7.5,
                    uv_lamp_active: false,
                    event_type: 'SIMULATOR_TELEMETRY'
                })
            });

            const data = await res.json();
            logTerminal(`Telemetri tersimpan! Status kiosk: ${data.current_kiosk_st}`);
        } catch (e) {
            logTerminal('Error saat mengirim telemetri.');
        }
    }

    async function pollEsp32Command() {
        logTerminal(`Polling: GET /api/iot/kiosk/${kioskId}/command`);
        const badge = document.getElementById('esp-status-badge');

        try {
            const res = await fetch(`/api/iot/kiosk/${kioskId}/command?token=${secret}`);
            const data = await res.json();

            if (data.status === 'DISPENSE') {
                badge.className = 'px-2 py-0.5 rounded text-[10px] font-bold bg-purple-500/20 text-purple-300';
                badge.textContent = 'DISPENSING';
                logTerminal(`>>> PERINTAH DISPENSE DITERIMA! Job: ${data.job_id}, Suhu: ${data.water_type}, Vol: ${data.volume_ml}ml`);

                executeHardwareCycle(data);
            } else {
                badge.className = 'px-2 py-0.5 rounded text-[10px] font-bold bg-slate-800 text-slate-400';
                badge.textContent = 'IDLE';
                logTerminal(`Respons: ${data.message || 'IDLE'}`);
            }
        } catch (e) {
            logTerminal('Gagal melakukan polling ke server.');
        }
    }

    function executeHardwareCycle(job) {
        const uvIcon = document.getElementById('relay-uv-icon');
        const uvState = document.getElementById('relay-uv-state');
        const coldIcon = document.getElementById('valve-cold-icon');
        const coldState = document.getElementById('valve-cold-state');
        const normIcon = document.getElementById('valve-normal-icon');
        const normState = document.getElementById('valve-normal-state');
        const pulseEl = document.getElementById('flow-pulse-count');

        // 1. Pre-UV ON
        logTerminal('ESP32: Menyalakan Relay UV Sterilizer (Pre-flush)...');
        uvIcon.className = 'w-9 h-9 rounded-lg bg-purple-500/20 text-purple-400 flex items-center justify-center text-base shadow-lg shadow-purple-500/30';
        uvState.className = 'text-xs font-mono font-bold text-purple-400';
        uvState.textContent = 'ACTIVE (ON)';

        setTimeout(() => {
            // 2. Open Solenoid Valve
            if (job.water_type === 'COLD') {
                logTerminal('ESP32: Membuka Solenoid Valve Dingin (GPIO 17)...');
                coldIcon.className = 'w-9 h-9 rounded-lg bg-cyan-500/20 text-cyan-400 flex items-center justify-center text-base shadow-lg shadow-cyan-500/30';
                coldState.className = 'text-xs font-mono font-bold text-cyan-400';
                coldState.textContent = 'OPEN';
            } else {
                logTerminal('ESP32: Membuka Solenoid Valve Normal (GPIO 18)...');
                normIcon.className = 'w-9 h-9 rounded-lg bg-emerald-500/20 text-emerald-400 flex items-center justify-center text-base shadow-lg shadow-emerald-500/30';
                normState.className = 'text-xs font-mono font-bold text-emerald-400';
                normState.textContent = 'OPEN';
            }

            // Simulasi Pulsa Flow Sensor
            let pulses = 0;
            const targetPulses = Math.floor((job.volume_ml / 1000) * 450);
            const pulseInterval = setInterval(() => {
                pulses += 15;
                pulseEl.textContent = pulses + ' Pulsa';
                if (pulses >= targetPulses) {
                    clearInterval(pulseInterval);

                    // Tutup Valve
                    logTerminal('ESP32: Volume tercapai, menutup Solenoid Valve.');
                    coldState.textContent = 'CLOSED';
                    coldState.className = 'text-xs font-mono font-bold text-slate-500';
                    coldIcon.className = 'w-9 h-9 rounded-lg bg-slate-800 flex items-center justify-center text-slate-500 text-base';
                    normState.textContent = 'CLOSED';
                    normState.className = 'text-xs font-mono font-bold text-slate-500';
                    normIcon.className = 'w-9 h-9 rounded-lg bg-slate-800 flex items-center justify-center text-slate-500 text-base';

                    // Post-UV
                    setTimeout(() => {
                        logTerminal('ESP32: Siklus UV selesai, mematikan Relay UV.');
                        uvState.textContent = 'OFF';
                        uvState.className = 'text-xs font-mono font-bold text-slate-500';
                        uvIcon.className = 'w-9 h-9 rounded-lg bg-slate-800 flex items-center justify-center text-slate-500 text-base';

                        // Kirim Laporan Selesai
                        reportComplete(job.job_id, job.volume_ml);
                    }, 1500);
                }
            }, 80);

        }, 2000);
    }

    async function reportComplete(jobId, vol) {
        logTerminal(`ESP32: Mengirim laporan penuangan selesai ke server...`);
        try {
            const res = await fetch(`/api/iot/kiosk/${kioskId}/dispense-complete`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({
                    job_id: jobId,
                    dispensed_ml: vol,
                    uv_status: 'SUCCESS'
                })
            });
            const data = await res.json();
            logTerminal(`Server: ${data.message}. Sisa tangki: ${data.remaining_tank_pct}%`);
            document.getElementById('esp-status-badge').className = 'px-2 py-0.5 rounded text-[10px] font-bold bg-slate-800 text-slate-400';
            document.getElementById('esp-status-badge').textContent = 'STANDBY';
        } catch (e) {
            logTerminal('Gagal mengirim laporan selesai.');
        }
    }

    function copyFirmware() {
        navigator.clipboard.writeText(`// Firmware ESP32 FHK Kiosk Dispenser Client\n// Silakan gunakan source code di atas untuk flash ke board ESP32 fisik.`);
        alert('Template firmware siap pakai berhasil disalin ke clipboard!');
    }

    // Hitung jarak awal
    onSliderChange({{ $kiosk->current_water_level_pct }});
</script>
@endsection
