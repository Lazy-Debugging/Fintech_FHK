/*
  FRESH HYDRATION KIOSK (FHK) - Smart Water Dispenser
  Board    : ESP32-S3 CAM 40-pin
  Sensor   : GC2145 camera, HC-SR04, YF-S401
  Output   : LCD I2C 16x2, relay HW-307 / JQC3F, pump, active buzzer
  Network  : Wi-Fi station + Fallback AP + MQTT Client + Local Dashboard + MJPEG stream

  v8 (MQTT Transaction-Driven):
    - Terintegrasi penuh dengan Backend Laravel Fintech FHK melalui MQTT Broker.
    - Menunggu perintah resmi (fhk/kiosk/{id}/command) dengan target volume (mL).
    - Status penuangan dilaporkan real-time tiap 500ms (fhk/kiosk/{id}/status).
    - Laporan selesai dan volume riil dikirim ke (fhk/kiosk/{id}/complete).
    - Proteksi keamanan: Anti-Tumpah (jarak <= 5 cm), Deteksi Gelas Diangkat,
      No-Flow Protection, dan Settle Volume (air sisa selang).
    - Dual Core: Core 0 untuk WiFi/MQTT/Camera, Core 1 untuk Sensor/Pump/LCD.
*/

#include <Arduino.h>
#include <WiFi.h>
#include <PubSubClient.h>
#include <HTTPClient.h>
#include "esp_camera.h"
#include "img_converters.h"
#include "esp_http_server.h"
#include "driver/gpio.h"
#include "esp_timer.h"
#include "esp_log.h"

// Cetak debug TANPA memblokir: dilewati jika buffer Serial penuh.
#define DBG(...) do { if (Serial.availableForWrite() >= 200) Serial.printf(__VA_ARGS__); } while (0)

// ============================================================
// 1. IDENTITAS KIOSK & KONFIGURASI MQTT / HTTP
// ============================================================

const char *KIOSK_ID           = "FHK-JAKARTA-01"; // Wajib cocok dengan DEFAULT_KIOSK_ID di Laravel
const char *MQTT_BROKER_IP     = "broker.emqx.io"; // Cloud MQTT Broker (Bisa diakses dari Internet & Live Server)
const int   MQTT_BROKER_PORT   = 1883;
const char *MQTT_USER          = "";               // Kosongkan jika broker tanpa autentikasi
const char *MQTT_PASS          = "";

// Server API Laravel Live (HTTP Polling & Reporting Fallback - Ringan & Cepat via Port 80)
const char *SERVER_BASE_URL    = "http://app.mesinbayar.com/fhk/api/iot/kiosk/FHK-JAKARTA-01";
const uint32_t HTTP_POLL_INTERVAL_MS = 2000; // Polling tiap 2 detik saat ST_IDLE
uint32_t lastHttpPollAt = 0;

// Topik MQTT
char TOPIC_COMMAND[64];
char TOPIC_STATUS[64];
char TOPIC_COMPLETE[64];
char TOPIC_TELEMETRY[64];

WiFiClient wifiMqttClient;
PubSubClient mqttClient(wifiMqttClient);

// ============================================================
// 2. GPIO ASSIGNMENT
// ============================================================

// LCD I2C 16x2 (Software Bit-banging)
static const int PIN_LCD_SDA = 1;
static const int PIN_LCD_SCL = 2;
static uint8_t lcdAddr = 0x27;

// HC-SR04
static const int PIN_TRIG = 14;
static const int PIN_ECHO = 21; // Wajib lewat voltage divider 5V -> 3.3V

// Relay / Pump
static const int PIN_RELAY = 47;
static const bool RELAY_ACTIVE_LOW = true; // OFF=HIGH, ON=LOW

// Water flow sensor YF-S401
static const int PIN_FLOW = 41; // Wajib lewat voltage divider 5V -> 3.3V
static const float FLOW_PULSES_PER_LITER = 5880.0f;
static const float FLOW_CAL_FACTOR = 1.0f;
static const uint32_t FLOW_MIN_PULSE_US = 800;

// Active buzzer
static const int PIN_BUZZER = 42;
static const bool BUZZER_ACTIVE_HIGH = true;

// ============================================================
// 3. CAMERA PIN MAP - GC2145
// ============================================================

#define CAM_PIN_PWDN    -1
#define CAM_PIN_RESET   -1
#define CAM_PIN_XCLK    15
#define CAM_PIN_SIOD    4
#define CAM_PIN_SIOC    5
#define CAM_PIN_Y9      16
#define CAM_PIN_Y8      17
#define CAM_PIN_Y7      18
#define CAM_PIN_Y6      12
#define CAM_PIN_Y5      10
#define CAM_PIN_Y4      8
#define CAM_PIN_Y3      9
#define CAM_PIN_Y2      11
#define CAM_PIN_VSYNC   6
#define CAM_PIN_HREF    7
#define CAM_PIN_PCLK    13

// ============================================================
// 4. WIFI ROUTER + FALLBACK ACCESS POINT
// ============================================================

// Wi-Fi 1 (Utama)
const char *WIFI_SSID_1 = "SIEGA";
const char *WIFI_PASSWORD_1 = "Siega@2025";

// Wi-Fi 2 (Cadangan / Baru - OMBRA COFFEE)
const char *WIFI_SSID_2 = "OMBRA COFFEE_4G";
const char *WIFI_PASSWORD_2 = "janganlupadikocok";

// Alias default untuk kompatibilitas
const char *WIFI_SSID = WIFI_SSID_1;
const char *WIFI_PASSWORD = WIFI_PASSWORD_1;

const char *FALLBACK_AP_SSID = "FHK-ESP32";
const char *FALLBACK_AP_PASSWORD = "Siega@2025";
IPAddress apIP(192, 168, 4, 1);
IPAddress apGateway(192, 168, 4, 1);
IPAddress apSubnet(255, 255, 255, 0);
bool usingFallbackAP = false;

// ============================================================
// 5. KALIBRASI GEOMETRI DISPENSER & AMBANG BATAS SENSOR
// ============================================================

// Dimensi Fisik Kios & Gelas (dalam satuan cm)
const float SENSOR_TO_BASE_CM      = 25.0f;  // Jarak sensor ultrasonik ke dasar tatakan gelas (25 cm)
const float CUP_HEIGHT_CM          = 10.0f;  // Tinggi fisik gelas (10 cm)
// Catatan Fisik: Bibir atas gelas berada pada jarak: 25.0 - 10.0 = 15.0 cm dari sensor!

// Jendela Deteksi Keberadaan Gelas di Tatakan:
// Gelas terdeteksi jika sensor membaca bibir/badan gelas antara 12.0 cm s/d 22.5 cm
// Gelas Anda yang terbaca di 16.9 - 17.5 cm akan LANGSUNG terdeteksi dan pompa menyala!
const float CUP_DETECT_MIN_CM      = 12.0f;  
const float CUP_DETECT_MAX_CM      = 22.5f;  
const uint32_t CUP_STABLE_MS       = 400;    // Jarak harus stabil 400 ms sebelum pompa menyala

// Proteksi Gelas Diangkat (Sensor membaca dasar tatakan kosong >= 24.0 cm)
const float CUP_REMOVED_CM         = 24.0f;  // Jarak >= 24 cm = gelas diangkat saat mengisi
const float REARM_DISTANCE_CM      = 24.0f;  // Jarak >= 24 cm = gelas sudah diambil setelah selesai
const uint32_t REARM_STABLE_MS     = 600;    // Gelas harus stabil diambil selama 600 ms sebelum reset ke IDLE

// Proteksi Darurat Anti-Luber (HANYA aktif jika air meluap melewati bibir gelas ke atas <= 11.0 cm)
const float OVERFLOW_DISTANCE_CM   = 11.0f;  
const uint8_t FULL_CONFIRM_COUNT   = 3;      // Konfirmasi 3x pembacaan berurutan
const uint32_t FAULT_MIN_SHOW_MS   = 2500;
const uint32_t INVALID_STOP_MS     = 2500;
const uint32_t CUP_WAIT_TIMEOUT_MS = 60000;  // 60 detik batas tunggu pelanggan meletakkan gelas

const uint32_t SENSOR_PERIOD_MS    = 40;     // Pengukuran ultrasonic tiap 40 ms
const uint32_t DISTANCE_STALE_MS   = 600;
const uint8_t  DIST_SAMPLES        = 3;      // Filter median 3 sampel
const float    SPEED_OF_SOUND_CM_PER_US = 0.0350f;
const uint32_t FLOW_LCD_INTERVAL_MS = 500;

const uint32_t MAX_PUMP_RUN_MS     = 60000;  // Maksimal 60 detik per pengisian
const uint32_t NO_FLOW_TIMEOUT_MS  = 6000;   // Proteksi kering jika 6 detik tanpa pulsa
const bool ENABLE_NO_FLOW_PROTECTION = true;

// ============================================================
// 6. GLOBAL STATE & FINITE STATE MACHINE (FSM)
// ============================================================

enum KioskState {
  ST_IDLE          = 0, // Siap, menunggu transaksi MQTT dari web
  ST_AWAITING_CUP  = 1, // Transaksi lunas diterima, menunggu gelas ditaruh
  ST_FILLING       = 2, // Pompa menyala menuang air sesuai volume pesanan
  ST_DONE          = 3, // Selesai, menunggu gelas diambil
  ST_FAULT         = 4  // Error / Pengaman terpicu
};

volatile uint8_t fillState = ST_IDLE;
httpd_handle_t controlHttpd = nullptr;
httpd_handle_t streamHttpd = nullptr;
bool cameraReady = false;

// Data transaksi aktif dari MQTT
char activeJobId[48]     = "";
char targetWaterType[16] = "NORMAL";
uint32_t targetVolumeMl  = 0;
uint32_t awaitingCupSince = 0;

volatile bool reqStart = false; // Dari dashboard manual
volatile bool reqStop  = false;
const char *statusMsg  = "Siap. Menunggu Pesanan.";
const char *faultShort = "";

uint32_t cupSeenSince = 0;
uint32_t removalSince = 0;
bool removalTimerActive = false;
uint32_t invalidSince = 0;
bool invalidTimerActive = false;
uint8_t fullCount = 0;
uint32_t sessionStartPulses = 0;

volatile bool pumpRunning = false;
uint32_t pumpStartedAt = 0;
uint32_t lastFlowPulseAt = 0;
uint32_t lastFlowPulseSeenBySafety = 0;

volatile float distanceCm = -1.0f;
volatile float flowLitersPerMinute = 0.0f;
volatile float totalLiters = 0.0f;
volatile float sessionLiters = 0.0f;

volatile uint32_t flowPulseCount = 0;
portMUX_TYPE flowMux = portMUX_INITIALIZER_UNLOCKED;

uint32_t lastFlowLcdAt = 0;
uint32_t lastFlowCalcAt = 0;
uint32_t lastPulseCountForRate = 0;
uint32_t lastMqttStatusAt = 0;
uint32_t lastTelemetryAt = 0;

volatile bool lcdReinitRequest = false;
uint32_t settleUntil = 0;
const uint32_t FLOW_SETTLE_MS = 2500;

uint32_t stateChangedAt = 0;
char lcdLine1[17] = "FHK SMART KIOSK";
char lcdLine2[17] = "Silakan Pesan";
portMUX_TYPE lcdMux = portMUX_INITIALIZER_UNLOCKED;

float levelRefCm = 999.0f;
const float LEVEL_PROGRESS_CM = 0.7f;

// Forward declaration
void stopFilling(uint8_t newState, const char *msg, const char *shortFault);

// ============================================================
// 7. SMALL HELPERS & BUZZER
// ============================================================

int relayOnLevel()  { return RELAY_ACTIVE_LOW ? LOW : HIGH; }
int relayOffLevel() { return RELAY_ACTIVE_LOW ? HIGH : LOW; }

bool buzzerIsOn = false;
uint32_t buzzerOffAt = 0;

void buzzerBeep(uint16_t durationMs = 70) {
  digitalWrite(PIN_BUZZER, BUZZER_ACTIVE_HIGH ? HIGH : LOW);
  buzzerIsOn = true;
  buzzerOffAt = millis() + durationMs;
}

void buzzerService() {
  if (buzzerIsOn && (int32_t)(millis() - buzzerOffAt) >= 0) {
    digitalWrite(PIN_BUZZER, BUZZER_ACTIVE_HIGH ? LOW : HIGH);
    buzzerIsOn = false;
  }
}

uint32_t getFlowPulseCount() {
  uint32_t count;
  portENTER_CRITICAL(&flowMux);
  count = flowPulseCount;
  portEXIT_CRITICAL(&flowMux);
  return count;
}

volatile uint32_t lastFlowPulseUs = 0;

void IRAM_ATTR flowPulseISR() {
  uint32_t t = (uint32_t)esp_timer_get_time();
  if ((uint32_t)(t - lastFlowPulseUs) < FLOW_MIN_PULSE_US) return;
  lastFlowPulseUs = t;
  portENTER_CRITICAL_ISR(&flowMux);
  flowPulseCount++;
  portEXIT_CRITICAL_ISR(&flowMux);
}

const char *stateName(uint8_t s) {
  switch (s) {
    case ST_IDLE:         return "IDLE";
    case ST_AWAITING_CUP: return "CUP?";
    case ST_FILLING:      return "FILL";
    case ST_DONE:         return "DONE";
    case ST_FAULT:        return "ERR";
  }
  return "?";
}

float currentSessionLiters() {
  return (float)(getFlowPulseCount() - sessionStartPulses) / FLOW_PULSES_PER_LITER * FLOW_CAL_FACTOR;
}

void setPump(bool turnOn, bool makeSound = true) {
  if (turnOn) {
    if (!pumpRunning) {
      pumpStartedAt = millis();
      lastFlowPulseAt = pumpStartedAt;
      lastFlowPulseSeenBySafety = getFlowPulseCount();
    }
    digitalWrite(PIN_RELAY, relayOnLevel());
    pumpRunning = true;
    DBG("PUMP: ON\n");
  } else {
    digitalWrite(PIN_RELAY, relayOffLevel());
    pumpRunning = false;
    DBG("PUMP: OFF\n");
  }

  lcdReinitRequest = true;
  if (makeSound) buzzerBeep(80);
}

// ============================================================
// 8. LCD 16x2 SOFTWARE I2C (ANTI NOISE & NON-BLOCKING)
// ============================================================

const uint8_t LCD_BIT_RS = 0x01;
const uint8_t LCD_BIT_EN = 0x04;
const uint8_t LCD_BIT_BL = 0x08;

static inline void swDelay() { delayMicroseconds(5); }

void swI2cStart() {
  digitalWrite(PIN_LCD_SDA, HIGH);
  digitalWrite(PIN_LCD_SCL, HIGH);
  swDelay();
  digitalWrite(PIN_LCD_SDA, LOW);
  swDelay();
  digitalWrite(PIN_LCD_SCL, LOW);
  swDelay();
}

void swI2cStop() {
  digitalWrite(PIN_LCD_SDA, LOW);
  swDelay();
  digitalWrite(PIN_LCD_SCL, HIGH);
  swDelay();
  digitalWrite(PIN_LCD_SDA, HIGH);
  swDelay();
}

bool swI2cWriteByte(uint8_t b) {
  for (int i = 7; i >= 0; i--) {
    digitalWrite(PIN_LCD_SDA, (b >> i) & 1);
    swDelay();
    digitalWrite(PIN_LCD_SCL, HIGH);
    swDelay();
    digitalWrite(PIN_LCD_SCL, LOW);
  }
  digitalWrite(PIN_LCD_SDA, HIGH);
  swDelay();
  digitalWrite(PIN_LCD_SCL, HIGH);
  swDelay();
  bool ack = (digitalRead(PIN_LCD_SDA) == LOW);
  digitalWrite(PIN_LCD_SCL, LOW);
  swDelay();
  return ack;
}

void lcdWrite4(uint8_t highNibble, uint8_t rs) {
  uint8_t d = (highNibble & 0xF0) | LCD_BIT_BL | rs;
  swI2cStart();
  swI2cWriteByte((uint8_t)(lcdAddr << 1));
  swI2cWriteByte(d | LCD_BIT_EN);
  swI2cWriteByte(d & (uint8_t)~LCD_BIT_EN);
  swI2cStop();
}

void lcdSend(uint8_t value, uint8_t rs) {
  lcdWrite4(value & 0xF0, rs);
  lcdWrite4((uint8_t)(value << 4), rs);
}

void lcdCommand(uint8_t c) { lcdSend(c, 0); }
bool lcdReady = false;

bool lcdProbe(uint8_t addr) {
  swI2cStart();
  bool ack = swI2cWriteByte((uint8_t)(addr << 1));
  swI2cStop();
  return ack;
}

void lcdHwInit() {
  lcdWrite4(0x30, 0); delay(5);
  lcdWrite4(0x30, 0); delay(2);
  lcdWrite4(0x30, 0); delay(2);
  lcdWrite4(0x20, 0); delay(2);
  lcdCommand(0x28);
  lcdCommand(0x0C);
  lcdCommand(0x06);
  lcdCommand(0x01);
  delay(3);
}

void lcdInit() {
  pinMode(PIN_LCD_SDA, OUTPUT_OPEN_DRAIN);
  pinMode(PIN_LCD_SCL, OUTPUT_OPEN_DRAIN);
  digitalWrite(PIN_LCD_SDA, HIGH);
  digitalWrite(PIN_LCD_SCL, HIGH);
  delay(50);

  lcdReady = lcdProbe(lcdAddr);
  if (!lcdReady) {
    for (uint8_t a = 0x20; a <= 0x3F; a++) {
      if (lcdProbe(a)) { lcdAddr = a; lcdReady = true; break; }
    }
  }
  lcdHwInit();
}

void lcdPrintLine(uint8_t row, const char *message) {
  char padded[17];
  memset(padded, ' ', 16);
  padded[16] = '\0';
  size_t len = strlen(message);
  if (len > 16) len = 16;
  memcpy(padded, message, len);
  lcdCommand(row == 0 ? 0x80 : 0xC0);
  for (int i = 0; i < 16; i++) lcdSend((uint8_t)padded[i], LCD_BIT_RS);
}

void setLcdText(const char *l1, const char *l2) {
  portENTER_CRITICAL(&lcdMux);
  strncpy(lcdLine1, l1, 16); lcdLine1[16] = '\0';
  strncpy(lcdLine2, l2, 16); lcdLine2[16] = '\0';
  portEXIT_CRITICAL(&lcdMux);
}

void lcdTask(void *param) {
  char last1[17] = "";
  char last2[17] = "";
  uint32_t lastForce = millis();
  for (;;) {
    if (lcdReinitRequest || millis() - lastForce >= 5000) {
      bool full = lcdReinitRequest;
      lcdReinitRequest = false;
      lastForce = millis();
      if (full) lcdHwInit();
      last1[0] = '\0';
      last2[0] = '\0';
    }
    char a[17];
    char b[17];
    portENTER_CRITICAL(&lcdMux);
    memcpy(a, lcdLine1, 17);
    memcpy(b, lcdLine2, 17);
    portEXIT_CRITICAL(&lcdMux);
    if (strcmp(a, last1) != 0) { lcdPrintLine(0, a); strcpy(last1, a); }
    if (strcmp(b, last2) != 0) { lcdPrintLine(1, b); strcpy(last2, b); }
    vTaskDelay(pdMS_TO_TICKS(200));
  }
}

// ============================================================
// 9. SENSOR JARAK INTERRUPT (CORE 1 - PRECISE)
// ============================================================

volatile uint32_t echoStartUs = 0;
volatile uint32_t echoDurationUs = 0;
volatile bool echoStarted = false;
volatile bool echoDone = false;

volatile float latestDistance = -1.0f;
volatile uint32_t latestDistanceAt = 0;
volatile uint32_t distanceSeq = 0;
uint32_t lastSeqProcessed = 0;
volatile float rawLast = -1.0f;
uint32_t lastLogicAt = 0;

void IRAM_ATTR echoISR() {
  uint32_t t = (uint32_t)esp_timer_get_time();
  if (gpio_get_level((gpio_num_t)PIN_ECHO)) {
    echoStartUs = t;
    echoStarted = true;
  } else if (echoStarted) {
    echoDurationUs = t - echoStartUs;
    echoStarted = false;
    echoDone = true;
  }
}

float measureOnce() {
  echoDone = false;
  echoStarted = false;

  digitalWrite(PIN_TRIG, LOW);
  delayMicroseconds(4);
  digitalWrite(PIN_TRIG, HIGH);
  delayMicroseconds(10);
  digitalWrite(PIN_TRIG, LOW);

  uint32_t t0 = millis();
  while (!echoDone) {
    if (millis() - t0 > 35) return -1.0f;
    vTaskDelay(1);
  }

  uint32_t dur = echoDurationUs;
  if (dur == 0 || dur > 30000UL) return -1.0f;

  float cm = ((float)dur * SPEED_OF_SOUND_CM_PER_US) / 2.0f;
  if (cm < 2.0f || cm > 400.0f) return -1.0f;
  return cm;
}

void sensorTask(void *param) {
  pinMode(PIN_ECHO, INPUT);
  attachInterrupt(digitalPinToInterrupt(PIN_ECHO), echoISR, CHANGE);

  float win[DIST_SAMPLES];
  for (int i = 0; i < DIST_SAMPLES; i++) win[i] = -1.0f;
  int idx = 0;

  TickType_t lastWake = xTaskGetTickCount();
  for (;;) {
    win[idx] = measureOnce();
    rawLast = win[idx];
    idx = (idx + 1) % DIST_SAMPLES;

    float v[DIST_SAMPLES];
    int n = 0;
    for (int i = 0; i < DIST_SAMPLES; i++) if (win[i] > 0.0f) v[n++] = win[i];

    float result = -1.0f;
    if (n >= 2) {
      for (int i = 1; i < n; i++) {
        float k = v[i];
        int j = i - 1;
        while (j >= 0 && v[j] > k) { v[j + 1] = v[j]; j--; }
        v[j + 1] = k;
      }
      result = v[n / 2];
    }

    latestDistance = result;
    latestDistanceAt = millis();
    distanceSeq = distanceSeq + 1;

    vTaskDelayUntil(&lastWake, pdMS_TO_TICKS(SENSOR_PERIOD_MS));
  }
}

// ============================================================
// 10. MQTT MESSAGING & HANDLERS
// ============================================================

void publishMqttStatus(bool isComplete = false, const char *fault = "") {
  if (!mqttClient.connected()) return;

  uint32_t currentMl = (uint32_t)(currentSessionLiters() * 1000.0f);
  float pct = (targetVolumeMl > 0) ? ((float)currentMl / (float)targetVolumeMl * 100.0f) : 100.0f;
  if (pct > 100.0f) pct = 100.0f;

  char payload[320];
  if (!isComplete) {
    snprintf(payload, sizeof(payload),
      "{\"job_id\":\"%s\",\"state\":\"%s\",\"dispensed_ml\":%u,\"target_ml\":%u,"
      "\"progress_pct\":%.1f,\"flow_lpm\":%.2f,\"distance_cm\":%.1f,\"pump\":%s}",
      activeJobId, stateName(fillState), currentMl, targetVolumeMl,
      pct, flowLitersPerMinute, (float)distanceCm, pumpRunning ? "true" : "false"
    );
    mqttClient.publish(TOPIC_STATUS, payload);
  } else {
    bool isSuccess = (strlen(fault) == 0 || strcmp(fault, "WATER_FULL_18.5CM") == 0 || strcmp(fault, "WATER_FULL_10CM") == 0 || strcmp(fault, "OVERFLOW_STOP") == 0);
    const char *statusStr = isSuccess ? "SUCCESS" : "FAULT";
    snprintf(payload, sizeof(payload),
      "{\"job_id\":\"%s\",\"status\":\"%s\",\"dispensed_ml\":%u,\"target_ml\":%u,"
      "\"duration_ms\":%u,\"fault_code\":%s%s%s,\"message\":\"%s\"}",
      activeJobId, statusStr, currentMl, targetVolumeMl,
      (millis() - pumpStartedAt),
      (!isSuccess && strlen(fault) > 0) ? "\"" : "", (!isSuccess && strlen(fault) > 0) ? fault : "null", (!isSuccess && strlen(fault) > 0) ? "\"" : "",
      statusMsg
    );
    mqttClient.publish(TOPIC_COMPLETE, payload, true);
  }
}

void parseJsonCommand(const char *jsonStr) {
  Serial.printf("\n[MQTT CMD RECV] %s\n", jsonStr);

  // Deteksi fleksibel: mendukung spasi maupun tanpa spasi
  if (strstr(jsonStr, "DISPENSE")) {
    char newJobId[48] = "";
    // Ambil job_id secara fleksibel
    const char *pJob = strstr(jsonStr, "job_id");
    if (pJob) {
      const char *colon = strchr(pJob, ':');
      if (colon) {
        const char *quote1 = strchr(colon, '\"');
        if (quote1) {
          quote1++;
          int i = 0;
          while (*quote1 && *quote1 != '\"' && i < sizeof(newJobId) - 1) {
            newJobId[i++] = *quote1++;
          }
          newJobId[i] = '\0';
        }
      }
    }

    // Jika job yang sama sedang diproses atau baru saja selesai, abaikan pesan duplikat
    if (strlen(newJobId) > 0 && strlen(activeJobId) > 0 && strcmp(newJobId, activeJobId) == 0 && fillState != ST_IDLE) {
      Serial.printf("[FSM] Mengabaikan duplikat perintah untuk Job: %s\n", newJobId);
      return;
    }
    strncpy(activeJobId, newJobId, sizeof(activeJobId) - 1);

    // Ambil volume_ml secara fleksibel
    targetVolumeMl = 250;
    const char *pVol = strstr(jsonStr, "volume_ml");
    if (pVol) {
      while (*pVol && (*pVol < '0' || *pVol > '9')) pVol++;
      if (*pVol >= '0' && *pVol <= '9') {
        targetVolumeMl = (uint32_t)atoi(pVol);
      }
    }
    if (targetVolumeMl == 0) targetVolumeMl = 250;

    Serial.printf("[FSM] >>> PERINTAH DISPENSE DITERIMA! Job: %s | Target: %u mL <<<\n", activeJobId, targetVolumeMl);

    // Ubah status ke AWAITING_CUP
    fillState = ST_AWAITING_CUP;
    stateChangedAt = millis();
    awaitingCupSince = millis();
    statusMsg = "Letakkan gelas Anda...";
    faultShort = "";

    buzzerBeep(120);

    char l1[17], l2[17];
    snprintf(l1, sizeof(l1), "Order: %umL", targetVolumeMl);
    snprintf(l2, sizeof(l2), "Taruh Gelas...");
    setLcdText(l1, l2);

    publishMqttStatus(false);
  } else if (strstr(jsonStr, "ABORT")) {
    Serial.println("[FSM] >>> PERINTAH ABORT DITERIMA DARI SERVER! <<<");
    stopFilling(ST_FAULT, "Dihentikan oleh Server!", "SERVER_ABORT");
  }
}

void mqttCallback(char *topic, byte *payload, unsigned int length) {
  char msg[512];
  if (length >= sizeof(msg)) length = sizeof(msg) - 1;
  memcpy(msg, payload, length);
  msg[length] = '\0';

  Serial.printf("\n[MQTT CALLBACK] Topik: %s | Isi: %s\n", topic, msg);
  parseJsonCommand(msg);
}

// ============================================================
// 10B. HTTP POLLING & REPORTING (FIREWALL-PROOF FALLBACK)
// ============================================================

void pollServerHttpCommand() {
  if (WiFi.status() != WL_CONNECTED || fillState != ST_IDLE) return;
  uint32_t now = millis();
  if (now - lastHttpPollAt < HTTP_POLL_INTERVAL_MS) return;
  lastHttpPollAt = now;

  WiFiClient client;
  HTTPClient http;
  char url[160];
  snprintf(url, sizeof(url), "%s/command", SERVER_BASE_URL);

  if (http.begin(client, url)) {
    http.setTimeout(1500);
    int httpCode = http.GET();
    if (httpCode == HTTP_CODE_OK) {
      String payload = http.getString();
      if (payload.indexOf("\"DISPENSE\"") >= 0) {
        Serial.printf("[HTTP POLL] >>> TRANSAKSI WEB DITERIMA! <<<\n%s\n", payload.c_str());
        parseJsonCommand(payload.c_str());
      }
    }
    http.end();
  }
}

void reportHttpDispenseComplete(const char *jobId, uint32_t dispensedMl) {
  if (WiFi.status() != WL_CONNECTED || strlen(jobId) == 0) return;

  Serial.printf("[HTTP COMPLETE] Melaporkan tuntas ke server untuk Job: %s (%u mL)...\n", jobId, dispensedMl);

  WiFiClient client;
  HTTPClient http;
  char url[160];
  snprintf(url, sizeof(url), "%s/dispense-complete", SERVER_BASE_URL);

  if (http.begin(client, url)) {
    http.setTimeout(2500);
    http.addHeader("Content-Type", "application/json");
    char body[160];
    snprintf(body, sizeof(body), "{\"job_id\":\"%s\",\"dispensed_ml\":%u,\"uv_status\":\"SUCCESS\"}", jobId, dispensedMl);

    int httpCode = http.POST(body);
    if (httpCode > 0) {
      Serial.printf("[HTTP COMPLETE] Berhasil dikonfirmasi oleh Web Server! HTTP %d\n", httpCode);
    } else {
      Serial.printf("[HTTP COMPLETE] Gagal kirim laporan: %s\n", http.errorToString(httpCode).c_str());
    }
    http.end();
  }
}

void ensureMqttConnected() {
  if (WiFi.status() != WL_CONNECTED) {
    static uint32_t lastWifiLog = 0;
    static uint32_t lastReconnectTry = 0;
    static int reconnectTarget = 1;

    if (millis() - lastWifiLog > 5000) {
      lastWifiLog = millis();
      Serial.printf("[WIFI] Sedang menyambung ke Wi-Fi... (Status: %d)\n", WiFi.status());
    }

    if (!usingFallbackAP && millis() - lastReconnectTry > 12000) {
      lastReconnectTry = millis();
      if (reconnectTarget == 1) {
        Serial.printf("[WIFI] Reconnecting to Wi-Fi 1 (%s)...\n", WIFI_SSID_1);
        WiFi.disconnect();
        WiFi.begin(WIFI_SSID_1, WIFI_PASSWORD_1);
        reconnectTarget = 2;
      } else {
        Serial.printf("[WIFI] Reconnecting to Wi-Fi 2 (%s)...\n", WIFI_SSID_2);
        WiFi.disconnect();
        WiFi.begin(WIFI_SSID_2, WIFI_PASSWORD_2);
        reconnectTarget = 1;
      }
    }
    return;
  }
  if (mqttClient.connected()) return;

  static uint32_t lastAttempt = 0;
  if (millis() - lastAttempt < 3000) return;
  lastAttempt = millis();

  char clientId[32];
  snprintf(clientId, sizeof(clientId), "FHK_ESP32_%04X", (uint16_t)ESP.getEfuseMac());

  Serial.printf("[MQTT] Menghubungkan ke broker (%s:%d)...\n", MQTT_BROKER_IP, MQTT_BROKER_PORT);

  bool ok = false;
  if (strlen(MQTT_USER) > 0) {
    ok = mqttClient.connect(clientId, MQTT_USER, MQTT_PASS);
  } else {
    ok = mqttClient.connect(clientId);
  }

  if (ok) {
    Serial.printf("[MQTT] BERHASIL TERHUBUNG! Subscribe ke topik: %s\n", TOPIC_COMMAND);
    mqttClient.subscribe(TOPIC_COMMAND, 1);
    mqttClient.subscribe("fhk/kiosk/+/command", 1);
  } else {
    Serial.printf("[MQTT] Gagal terhubung, rc=%d (Cek IP %s di baris 36)\n", mqttClient.state(), MQTT_BROKER_IP);
  }
}

// ============================================================
// 11. STATE MACHINE & LOGIKA PENGISIAN AIR
// ============================================================

void stopFilling(uint8_t newState, const char *msg, const char *shortFault = "") {
  sessionLiters = currentSessionLiters();
  uint32_t currentMl = (uint32_t)(sessionLiters * 1000.0f);
  setPump(false, true);
  settleUntil = millis() + FLOW_SETTLE_MS;
  fillState = newState;
  stateChangedAt = millis();
  statusMsg = msg;
  faultShort = shortFault;
  removalTimerActive = false;
  invalidTimerActive = false;
  fullCount = 0;

  // Laporkan ke MQTT bahwa pengisian selesai / gagal
  publishMqttStatus(true, shortFault);

  // Laporkan juga via HTTP POST ke Server Live Laravel jika selesai
  if (newState == ST_DONE && strlen(activeJobId) > 0) {
    reportHttpDispenseComplete(activeJobId, currentMl);
  }

  DBG("STATE -> %s | %s | volume sesi: %.3f L\n", stateName(newState), msg, (float)sessionLiters);
}

void startFilling(bool manual) {
  fullCount = 0;
  invalidTimerActive = false;
  sessionStartPulses = getFlowPulseCount();
  sessionLiters = 0.0f;
  settleUntil = 0;
  levelRefCm = (distanceCm > 0.0f) ? distanceCm : 999.0f;
  fillState = ST_FILLING;
  statusMsg = manual ? "Mengisi manual..." : "Mengisi air...";
  faultShort = "";
  setPump(true, true);
  DBG("STATE -> FILL (%s) Target: %u mL\n", manual ? "manual" : "otomatis", targetVolumeMl);
}

void runFillLogic(float d) {
  uint32_t now = millis();
  bool valid = d > 0.0f;
  bool inCupWindow = valid && d >= CUP_DETECT_MIN_CM && d <= CUP_DETECT_MAX_CM;

  switch (fillState) {
    case ST_IDLE:
      // Di mode baru: Jangan pernah pompa jika tidak ada pesanan aktif dari MQTT!
      break;

    case ST_AWAITING_CUP:
      // Menunggu pelanggan meletakkan gelas setelah order dibayar
      if (inCupWindow) {
        if (cupSeenSince == 0) {
          cupSeenSince = now;
        } else if (now - cupSeenSince >= CUP_STABLE_MS) {
          cupSeenSince = 0;
          startFilling(false);
        }
      } else {
        cupSeenSince = 0;
      }

      // Timeout jika 60 detik gelas tidak kunjung ditaruh
      if (now - awaitingCupSince >= CUP_WAIT_TIMEOUT_MS) {
        stopFilling(ST_FAULT, "Batas waktu taruh gelas habis!", "CUP_TIMEOUT");
      }
      break;

    case ST_FILLING: {
      uint32_t currentMl = (uint32_t)(currentSessionLiters() * 1000.0f);

      // 1) Target Volume Flow Meter tercapai (Utama: 250 mL)
      if (targetVolumeMl > 0 && currentMl >= targetVolumeMl) {
        stopFilling(ST_DONE, "Volume tercapai! Ambil gelas.", "");
        break;
      }

      // 2) Proteksi Darurat Anti-Luber (HANYA jika air meluap melewati bibir gelas <= 11 cm)
      if (valid && d <= OVERFLOW_DISTANCE_CM) {
        fullCount++;
        if (fullCount >= FULL_CONFIRM_COUNT) {
          stopFilling(ST_DONE, "Gelas penuh! Ambil gelas.", "OVERFLOW_STOP");
          break;
        }
      } else {
        fullCount = 0;
      }

      // 3) Gelas diangkat saat mengisi (sensor membaca dasar tatakan >= 24 cm)
      if (valid && d >= CUP_REMOVED_CM) {
        stopFilling(ST_FAULT, "Gelas diangkat!", "CUP_REMOVED");
        break;
      }

      // 4) Sensor jarak tak terbaca terlalu lama
      if (!valid) {
        if (!invalidTimerActive) {
          invalidTimerActive = true;
          invalidSince = now;
        } else if (now - invalidSince >= INVALID_STOP_MS) {
          stopFilling(ST_FAULT, "Sensor jarak hilang!", "SENSOR");
        }
      } else {
        invalidTimerActive = false;
      }
      break;
    }

    case ST_DONE:
    case ST_FAULT:
      // Tunggu minimal 2 detik tampilan status, lalu jika gelas diangkat -> kembali IDLE
      if ((now - stateChangedAt) < FAULT_MIN_SHOW_MS) break;

      if (!valid || d > REARM_DISTANCE_CM) {
        if (!removalTimerActive) {
          removalTimerActive = true;
          removalSince = now;
        } else if (now - removalSince >= REARM_STABLE_MS) {
          removalTimerActive = false;
          fillState = ST_IDLE;
          activeJobId[0] = '\0';
          targetVolumeMl = 0;
          faultShort = "";
          statusMsg = "Siap. Menunggu Pesanan.";
          setLcdText("FHK SMART KIOSK", "Silakan Pesan");
          DBG("STATE -> IDLE (Siap pesanan baru)\n");
        }
      } else {
        removalTimerActive = false;
      }
      break;
  }
}

void checkPumpSafety() {
  if (!pumpRunning) return;

  uint32_t now = millis();
  uint32_t pulsesNow = getFlowPulseCount();
  sessionLiters = currentSessionLiters();

  if (pulsesNow != lastFlowPulseSeenBySafety) {
    lastFlowPulseSeenBySafety = pulsesNow;
    lastFlowPulseAt = now;
  }

  // Bukti aliran alternatif: permukaan air naik
  float dNow = distanceCm;
  if (dNow > 0.0f && dNow < levelRefCm - LEVEL_PROGRESS_CM) {
    levelRefCm = dNow;
    lastFlowPulseAt = now;
  }

  if (now - pumpStartedAt >= MAX_PUMP_RUN_MS) {
    stopFilling(ST_FAULT, "Waktu maksimum habis!", "TIMEOUT");
    return;
  }

  if (ENABLE_NO_FLOW_PROTECTION &&
      now - pumpStartedAt >= NO_FLOW_TIMEOUT_MS &&
      now - lastFlowPulseAt >= NO_FLOW_TIMEOUT_MS) {
    stopFilling(ST_FAULT, "Pompa kering / tak ada aliran!", "NOFLOW");
  }
}

void updateDistanceAndLogic() {
  uint32_t now = millis();
  uint32_t seq = distanceSeq;

  if (seq != lastSeqProcessed) {
    lastSeqProcessed = seq;
    lastLogicAt = now;
    float d = latestDistance;
    distanceCm = d;
    runFillLogic(d);
  } else if ((int32_t)(now - latestDistanceAt) > (int32_t)DISTANCE_STALE_MS && now - lastLogicAt >= 100) {
    lastLogicAt = now;
    distanceCm = -1.0f;
    runFillLogic(-1.0f);
  }
}

void updateFlowAndLCD() {
  uint32_t now = millis();
  if (now - lastFlowLcdAt < FLOW_LCD_INTERVAL_MS) return;

  uint32_t pulsesNow = getFlowPulseCount();
  uint32_t elapsedMs = now - lastFlowCalcAt;
  if (elapsedMs == 0) elapsedMs = 1;
  uint32_t pulseDelta = pulsesNow - lastPulseCountForRate;

  float currentFlow = (pulseDelta * 60000.0f) /
                      (FLOW_PULSES_PER_LITER * (float)elapsedMs) * FLOW_CAL_FACTOR;
  flowLitersPerMinute = currentFlow;
  totalLiters = (float)pulsesNow / FLOW_PULSES_PER_LITER * FLOW_CAL_FACTOR;

  lastPulseCountForRate = pulsesNow;
  lastFlowCalcAt = now;
  lastFlowLcdAt = now;

  float d = distanceCm;
  char line1[32];
  char line2[32];

  uint32_t sessionMl = (uint32_t)(sessionLiters * 1000.0f);

  if (fillState == ST_IDLE) {
    snprintf(line1, sizeof(line1), "FHK SMART KIOSK");
    snprintf(line2, sizeof(line2), "Silakan Pesan");
  } else if (fillState == ST_AWAITING_CUP) {
    snprintf(line1, sizeof(line1), "Order: %umL", targetVolumeMl);
    if (d > 0.0f) {
      snprintf(line2, sizeof(line2), "Taruh Gelas D:%.0f", d);
    } else {
      snprintf(line2, sizeof(line2), "Taruh Gelas...");
    }
  } else if (fillState == ST_FILLING) {
    snprintf(line1, sizeof(line1), "Isi:%3u/%3umL", sessionMl, targetVolumeMl);
    snprintf(line2, sizeof(line2), "Flow:%.1fL D:%4.1f", currentFlow, d);
  } else if (fillState == ST_DONE) {
    snprintf(line1, sizeof(line1), "Selesai: %umL", sessionMl);
    snprintf(line2, sizeof(line2), "Ambil Gelas Anda");
  } else if (fillState == ST_FAULT) {
    snprintf(line1, sizeof(line1), "STOP: %s", faultShort);
    snprintf(line2, sizeof(line2), "Ambil Gelas");
  }

  setLcdText(line1, line2);

  // Kirim status berkala ke MQTT saat filling
  if (fillState == ST_FILLING && now - lastMqttStatusAt >= 500) {
    lastMqttStatusAt = now;
    publishMqttStatus(false);
  }

  // Kirim telemetri setiap 15 detik jika IDLE
  if (fillState == ST_IDLE && now - lastTelemetryAt >= 15000) {
    lastTelemetryAt = now;
    if (mqttClient.connected()) {
      char telem[180];
      snprintf(telem, sizeof(telem),
        "{\"kiosk_id\":\"%s\",\"state\":\"IDLE\",\"wifi_rssi\":%d,\"distance_cm\":%.1f}",
        KIOSK_ID, WiFi.RSSI(), (float)distanceCm);
      mqttClient.publish(TOPIC_TELEMETRY, telem);
    }
  }
}

void updateSettleVolume() {
  if (pumpRunning || settleUntil == 0) return;
  sessionLiters = currentSessionLiters();
  if ((int32_t)(millis() - settleUntil) >= 0) settleUntil = 0;
}

// ============================================================
// 12. CAMERA INITIALIZATION (GC2145)
// ============================================================

void initializeCamera() {
  camera_config_t config = {};
  config.ledc_channel = LEDC_CHANNEL_0;
  config.ledc_timer = LEDC_TIMER_0;

  config.pin_d0 = CAM_PIN_Y2;
  config.pin_d1 = CAM_PIN_Y3;
  config.pin_d2 = CAM_PIN_Y4;
  config.pin_d3 = CAM_PIN_Y5;
  config.pin_d4 = CAM_PIN_Y6;
  config.pin_d5 = CAM_PIN_Y7;
  config.pin_d6 = CAM_PIN_Y8;
  config.pin_d7 = CAM_PIN_Y9;

  config.pin_xclk = CAM_PIN_XCLK;
  config.pin_pclk = CAM_PIN_PCLK;
  config.pin_vsync = CAM_PIN_VSYNC;
  config.pin_href = CAM_PIN_HREF;
  config.pin_sccb_sda = CAM_PIN_SIOD;
  config.pin_sccb_scl = CAM_PIN_SIOC;
  config.pin_pwdn = CAM_PIN_PWDN;
  config.pin_reset = CAM_PIN_RESET;

  config.xclk_freq_hz = 20000000;
  config.pixel_format = PIXFORMAT_RGB565;
  config.frame_size = FRAMESIZE_QVGA;
  config.jpeg_quality = 12;

  if (psramFound()) {
    config.fb_count = 2;
    config.fb_location = CAMERA_FB_IN_PSRAM;
  } else {
    config.fb_count = 1;
    config.fb_location = CAMERA_FB_IN_DRAM;
  }
  config.grab_mode = CAMERA_GRAB_LATEST; // Mengambil frame paling baru secara real-time (anti-lag)

  esp_err_t err = esp_camera_init(&config);
  cameraReady = (err == ESP_OK);
  if (cameraReady) {
    Serial.println("Camera GC2145 initialized successfully.");
  } else {
    Serial.printf("Camera GC2145 init FAILED: 0x%x\n", err);
  }
}

// ============================================================
// 13. WEB SERVER & STREAMING (PORT 80 & 81)
// ============================================================

bool frameToJpeg(camera_fb_t *fb, uint8_t **jpgBuffer, size_t *jpgLength) {
  if (fb == nullptr) return false;
  if (fb->format == PIXFORMAT_JPEG) {
    *jpgBuffer = fb->buf;
    *jpgLength = fb->len;
    return true;
  }
  // Kualitas kompresi dioptimalkan ke 20 (proses cepat ~12ms per frame, warna tetap jernih)
  return fmt2jpg(fb->buf, fb->len, fb->width, fb->height,
                 fb->format, 20, jpgBuffer, jpgLength);
}

esp_err_t streamHandler(httpd_req_t *req) {
  if (!cameraReady) return ESP_FAIL;
  static const char *CONTENT_TYPE = "multipart/x-mixed-replace;boundary=frameboundary";
  char partHeader[96];
  httpd_resp_set_type(req, CONTENT_TYPE);
  httpd_resp_set_hdr(req, "Access-Control-Allow-Origin", "*");

  while (true) {
    camera_fb_t *fb = esp_camera_fb_get();
    if (fb == nullptr) return ESP_FAIL;
    uint8_t *jpgBuffer = nullptr;
    size_t jpgLength = 0;
    bool converted = frameToJpeg(fb, &jpgBuffer, &jpgLength);
    if (!converted) { esp_camera_fb_return(fb); return ESP_FAIL; }

    esp_err_t result = httpd_resp_send_chunk(req, "\r\n--frameboundary\r\n", 19);
    if (result == ESP_OK) {
      snprintf(partHeader, sizeof(partHeader), "Content-Type: image/jpeg\r\nContent-Length: %u\r\n\r\n", (unsigned int)jpgLength);
      result = httpd_resp_send_chunk(req, partHeader, strlen(partHeader));
    }
    if (result == ESP_OK) {
      result = httpd_resp_send_chunk(req, (const char *)jpgBuffer, jpgLength);
    }
    if (fb->format != PIXFORMAT_JPEG) free(jpgBuffer);
    esp_camera_fb_return(fb);
    if (result != ESP_OK) break;
    vTaskDelay(pdMS_TO_TICKS(35)); // Jeda optimal ~25-28 FPS mulus
  }
  return ESP_OK;
}

esp_err_t dataHandler(httpd_req_t *req) {
  char json[520];
  snprintf(json, sizeof(json),
    "{\"kiosk_id\":\"%s\",\"active_job\":\"%s\",\"distance_cm\":%.2f,\"flow_lpm\":%.3f,"
    "\"session_ml\":%.0f,\"target_ml\":%u,\"pump\":%s,\"mqtt\":%s,\"state\":\"%s\",\"msg\":\"%s\"}",
    KIOSK_ID, activeJobId, (float)distanceCm, (float)flowLitersPerMinute,
    (float)sessionLiters * 1000.0f, targetVolumeMl,
    pumpRunning ? "true" : "false",
    mqttClient.connected() ? "true" : "false",
    stateName(fillState), statusMsg);
  httpd_resp_set_type(req, "application/json");
  return httpd_resp_send(req, json, HTTPD_RESP_USE_STRLEN);
}

esp_err_t indexHandler(httpd_req_t *req) {
  const char *html = 
    "<!DOCTYPE html><html><head><meta charset='utf-8'>"
    "<meta name='viewport' content='width=device-width, initial-scale=1'>"
    "<title>FHK ESP32-S3 Camera Stream</title>"
    "<style>body{background:#0f172a;color:#f8fafc;font-family:sans-serif;text-align:center;margin:0;padding:16px;}"
    "h1{color:#38bdf8;font-size:20px;margin:8px 0;}"
    ".box{max-width:480px;margin:12px auto;border-radius:12px;overflow:hidden;border:2px solid #334155;background:#1e293b;}"
    "img{width:100%;min-height:240px;display:block;}"
    ".stats{max-width:480px;margin:12px auto;background:#1e293b;border-radius:12px;padding:14px;font-family:monospace;font-size:13px;text-align:left;line-height:1.6;border:1px solid #334155;}"
    "</style></head><body>"
    "<h1>📸 FHK Smart Kiosk Live Camera</h1>"
    "<div class='box'><img id='st' src='' alt='Menghubungkan ke Stream Port 81...'></div>"
    "<div class='stats' id='stt'>Memuat telemetri sensor...</div>"
    "<script>"
    "document.getElementById('st').src = window.location.protocol + '//' + window.location.hostname + ':81/stream';"
    "setInterval(()=>{fetch('/data').then(r=>r.json()).then(d=>{"
    "document.getElementById('stt').innerHTML = "
    "`<b>Status:</b> ${d.state} (${d.msg})<br>"
    "<b>Jarak Ultrasonik:</b> ${d.distance_cm} cm<br>"
    "<b>Aliran:</b> ${d.flow_lpm} L/min | <b>Volume:</b> ${d.session_ml} / ${d.target_ml} mL<br>"
    "<b>Pompa:</b> ${d.pump} | <b>MQTT Broker:</b> ${d.mqtt}`;"
    "}).catch(()=>{});},1000);"
    "</script></body></html>";

  httpd_resp_set_type(req, "text/html");
  return httpd_resp_send(req, html, HTTPD_RESP_USE_STRLEN);
}

void startWebServers() {
  httpd_config_t config = HTTPD_DEFAULT_CONFIG();
  config.server_port = 80;
  config.core_id = 0;
  if (httpd_start(&controlHttpd, &config) == ESP_OK) {
    httpd_uri_t iUri = { "/", HTTP_GET, indexHandler, nullptr };
    httpd_register_uri_handler(controlHttpd, &iUri);

    httpd_uri_t dUri = { "/data", HTTP_GET, dataHandler, nullptr };
    httpd_register_uri_handler(controlHttpd, &dUri);
  }

  httpd_config_t sConf = HTTPD_DEFAULT_CONFIG();
  sConf.server_port = 81;
  sConf.ctrl_port = 32769;
  sConf.core_id = 0;
  sConf.task_priority = tskIDLE_PRIORITY + 1;
  if (httpd_start(&streamHttpd, &sConf) == ESP_OK) {
    httpd_uri_t sUri = { "/stream", HTTP_GET, streamHandler, nullptr };
    httpd_register_uri_handler(streamHttpd, &sUri);
  }
}

// ============================================================
// 14. WIFI NETWORK
// ============================================================

void startNetwork() {
  WiFi.setSleep(false);
  WiFi.mode(WIFI_STA);

  // 1. Coba hubungkan ke Wi-Fi 1 (SIEGA)
  Serial.printf("Connecting to Wi-Fi 1: %s...\n", WIFI_SSID_1);
  lcdPrintLine(0, "WiFi Connect...");
  lcdPrintLine(1, WIFI_SSID_1);
  WiFi.begin(WIFI_SSID_1, WIFI_PASSWORD_1);

  uint32_t t0 = millis();
  while (WiFi.status() != WL_CONNECTED && millis() - t0 < 8000) {
    delay(300);
    Serial.print(".");
  }
  Serial.println();

  // 2. Jika Wi-Fi 1 tidak tersambung, coba Wi-Fi 2 (OMBRA COFFEE_4G)
  if (WiFi.status() != WL_CONNECTED) {
    Serial.printf("Wi-Fi 1 tidak tersambung. Connecting to Wi-Fi 2 (Fallback): %s...\n", WIFI_SSID_2);
    lcdPrintLine(0, "WiFi Fallback...");
    lcdPrintLine(1, "OMBRA COFFEE_4G");
    WiFi.disconnect();
    delay(500);
    WiFi.begin(WIFI_SSID_2, WIFI_PASSWORD_2);

    t0 = millis();
    while (WiFi.status() != WL_CONNECTED && millis() - t0 < 10000) {
      delay(300);
      Serial.print(".");
    }
    Serial.println();
  }

  // 3. Evaluasi hasil koneksi Wi-Fi
  if (WiFi.status() == WL_CONNECTED) {
    usingFallbackAP = false;
    Serial.printf("Wi-Fi Connected! SSID: %s | IP: ", WiFi.SSID().c_str());
    Serial.println(WiFi.localIP());
    char ipLine[17];
    snprintf(ipLine, sizeof(ipLine), "IP:%s", WiFi.localIP().toString().c_str());
    lcdPrintLine(0, "WiFi Connected!");
    lcdPrintLine(1, ipLine);
    delay(1500);
  } else {
    Serial.println("Kedua Wi-Fi gagal terhubung. Mengaktifkan Fallback AP...");
    lcdPrintLine(0, "WiFi Gagal!");
    lcdPrintLine(1, "Mode Soft-AP");
    delay(1000);
    WiFi.disconnect(true);
    WiFi.mode(WIFI_AP);
    WiFi.softAPConfig(apIP, apGateway, apSubnet);
    WiFi.softAP(FALLBACK_AP_SSID, FALLBACK_AP_PASSWORD, 6, false, 4);
    usingFallbackAP = true;
    Serial.print("AP IP: "); Serial.println(WiFi.softAPIP());
  }
}

// ============================================================
// 15. SETUP
// ============================================================

void setup() {
  Serial.begin(115200);
  esp_log_level_set("*", ESP_LOG_ERROR);
  delay(500);

  Serial.println("\n========================================");
  Serial.println(" FRESH HYDRATION KIOSK - ESP32-S3 (v8 MQTT)");
  Serial.println("========================================");

  // Siapkan nama-nama topik MQTT
  snprintf(TOPIC_COMMAND,   sizeof(TOPIC_COMMAND),   "fhk/kiosk/%s/command",   KIOSK_ID);
  snprintf(TOPIC_STATUS,    sizeof(TOPIC_STATUS),    "fhk/kiosk/%s/status",    KIOSK_ID);
  snprintf(TOPIC_COMPLETE,  sizeof(TOPIC_COMPLETE),  "fhk/kiosk/%s/complete",  KIOSK_ID);
  snprintf(TOPIC_TELEMETRY, sizeof(TOPIC_TELEMETRY), "fhk/kiosk/%s/telemetry", KIOSK_ID);

  // Konfigurasi pin
  pinMode(PIN_RELAY, OUTPUT);
  digitalWrite(PIN_RELAY, relayOffLevel());

  pinMode(PIN_BUZZER, OUTPUT);
  digitalWrite(PIN_BUZZER, BUZZER_ACTIVE_HIGH ? LOW : HIGH);

  pinMode(PIN_TRIG, OUTPUT);
  digitalWrite(PIN_TRIG, LOW);
  pinMode(PIN_ECHO, INPUT);

  pinMode(PIN_FLOW, INPUT);
  attachInterrupt(digitalPinToInterrupt(PIN_FLOW), flowPulseISR, RISING);

  // Sensor jarak di Core 1
  xTaskCreatePinnedToCore(sensorTask, "sensor", 4096, nullptr, 3, nullptr, 1);

  // LCD Init
  lcdInit();
  lcdPrintLine(0, "FRESH HYDRATION");
  lcdPrintLine(1, "Starting MQTT...");

  initializeCamera();
  startNetwork();
  startWebServers();

  // Konfigurasi MQTT
  mqttClient.setServer(MQTT_BROKER_IP, MQTT_BROKER_PORT);
  mqttClient.setCallback(mqttCallback);
  mqttClient.setBufferSize(512);

  buzzerBeep(100);
  lcdPrintLine(0, "FHK SMART KIOSK");
  lcdPrintLine(1, "Silakan Pesan");

  // LCD Task di Core 1
  xTaskCreatePinnedToCore(lcdTask, "lcd", 4096, nullptr, 1, nullptr, 1);

  lastFlowLcdAt = millis();
  lastFlowCalcAt = millis();
  lastPulseCountForRate = getFlowPulseCount();
}

// ============================================================
// 16. MAIN LOOP
// ============================================================

void loop() {
  buzzerService();
  ensureMqttConnected();
  mqttClient.loop();

  // Polling HTTP saat IDLE (Hybrid fallback jika MQTT di server diblokir hosting)
  pollServerHttpCommand();

  updateDistanceAndLogic();
  checkPumpSafety();
  updateSettleVolume();
  updateFlowAndLCD();

  delay(5);
}

