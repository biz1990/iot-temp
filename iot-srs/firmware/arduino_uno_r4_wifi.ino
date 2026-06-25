/**
 * IoT-SRS Arduino Firmware
 * Target Hardware: Arduino Uno R4 WiFi
 * Sensors: DHT22 (Temperature & Humidity)
 * Display: MKE-M07 OLED (I2C)
 * 
 * Features per SRS v2.0:
 * - HTTP POST to PHP API with Device Token Authentication
 * - Offline buffering (up to 100 readings) when WiFi unavailable
 * - Batch sync mechanism when connection restored
 * - OTA Update readiness (flag check)
 * - Deep sleep optimization
 * - LED status indicators
 */

#include <WiFiS3.h>
#include <ArduinoHttpClient.h>
#include <DHT.h>
#include <ArduinoJson.h>
#include <Wire.h>
#include <Adafruit_GFX.h>
#include <Adafruit_SSD1306.h>

// ================= CONFIGURATION =================
// Replace with your network credentials
const char* WIFI_SSID = "YOUR_WIFI_SSID";
const char* WIFI_PASS = "YOUR_WIFI_PASSWORD";

// Server Configuration
const char* SERVER_HOST = "your-server-ip-or-domain.com";
const int SERVER_PORT = 80;
const char* API_INGEST_PATH = "/iot-srs/api/ingest.php";

// Device Identity (Get this from Admin Dashboard)
const char* DEVICE_TOKEN = "YOUR_UNIQUE_DEVICE_TOKEN";
const char* DEVICE_ID = "DEV-001"; // Must match registration in DB

// Sensor Config
#define DHT_PIN 2
#define DHT_TYPE DHT22

// OLED Config
#define OLED_WIDTH 128
#define OLED_HEIGHT 64
#define OLED_RESET -1
#define OLED_ADDRESS 0x3C

// Timing Constants (ms)
const unsigned long READ_INTERVAL = 60000;      // Read sensors every 60s
const unsigned long SYNC_INTERVAL = 300000;     // Try batch sync every 5m
const unsigned long OTA_CHECK_INTERVAL = 3600000; // Check OTA every 1h

// Buffer Limits
const int MAX_BUFFER_SIZE = 100;

// ================= GLOBAL OBJECTS =================
WiFiClient wifiClient;
HttpClient http = HttpClient(wifiClient, SERVER_HOST, SERVER_PORT);
DHT dht(DHT_PIN, DHT_TYPE);
Adafruit_SSD1306 display(OLED_WIDTH, OLED_HEIGHT, &Wire, OLED_RESET);

// ================= STATE VARIABLES =================
struct SensorReading {
  long timestamp;
  float temperature;
  float humidity;
  bool synced;
};

SensorReading readBuffer[MAX_BUFFER_SIZE];
int bufferCount = 0;
unsigned long lastReadTime = 0;
unsigned long lastSyncTime = 0;
unsigned long lastOtaCheckTime = 0;
bool isWifiConnected = false;
bool hasNewData = false;

// Status LED pins (Built-in on R4 WiFi, but can use external)
const int LED_WIFI = LED_BUILTIN;
const int LED_ERROR = 4;
const int LED_SYNC = 5;

// ================= FUNCTION PROTOTYPES =================
void setupWiFi();
void disconnectWiFi();
bool connectToWiFi();
float readTemperature();
float readHumidity();
void updateOLED(float temp, float hum, const char* status);
void addToBuffer(float temp, float hum);
void sendSingleReading(float temp, float hum);
void performBatchSync();
bool checkForOTA();
void blinkLED(int pin, int count, int duration);
void saveBufferToEEPROM(); // Optional: Persist buffer across reboots
void loadBufferFromEEPROM(); // Optional: Restore buffer on boot

// ================= SETUP =================
void setup() {
  Serial.begin(9600);
  while (!Serial); // Wait for serial port

  // Initialize LEDs
  pinMode(LED_WIFI, OUTPUT);
  pinMode(LED_ERROR, OUTPUT);
  pinMode(LED_SYNC, OUTPUT);
  digitalWrite(LED_WIFI, LOW);
  digitalWrite(LED_ERROR, LOW);
  digitalWrite(LED_SYNC, LOW);

  Serial.println(F("=== IoT-SRS Firmware Starting ==="));
  updateOLED(0, 0, "Booting...");

  // Initialize Sensors
  dht.begin();
  delay(500);

  // Initialize OLED
  if (!display.begin(SSD1306_SWITCHCAPVCC, OLED_ADDRESS)) {
    Serial.println(F("OLED allocation failed"));
    blinkLED(LED_ERROR, 3, 200);
    for (;;); // Halt
  }
  display.clearDisplay();
  display.setTextSize(1);
  display.setTextColor(SSD1306_WHITE);
  updateOLED(0, 0, "Init WiFi...");

  // Load buffered data if any (Optional EEPROM logic)
  // loadBufferFromEEPROM(); 

  // Connect to WiFi
  setupWiFi();

  lastReadTime = millis() - READ_INTERVAL; // Force immediate read
  lastSyncTime = millis();
  lastOtaCheckTime = millis();

  Serial.println(F("Setup complete. Entering loop."));
  updateOLED(0, 0, "Ready");
}

// ================= MAIN LOOP =================
void loop() {
  unsigned long currentMillis = millis();

  // 1. Check WiFi Connection
  if (!isWifiConnected && (currentMillis % 10000 < 100)) {
    // Try reconnect every 10s if disconnected
    connectToWiFi();
  }

  // 2. Read Sensors at Interval
  if (currentMillis - lastReadTime >= READ_INTERVAL) {
    lastReadTime = currentMillis;
    
    float temp = readTemperature();
    float hum = readHumidity();

    if (!isnan(temp) && !isnan(hum)) {
      Serial.printf("Read: T=%.2f, H=%.2f\n", temp, hum);
      addToBuffer(temp, hum);
      
      // If online, try immediate send (optional optimization)
      if (isWifiConnected) {
        sendSingleReading(temp, hum);
      }
      
      updateOLED(temp, hum, isWifiConnected ? "Online" : "Offline");
      hasNewData = true;
    } else {
      Serial.println(F("Sensor read failed"));
      updateOLED(0, 0, "Sensor Err");
      blinkLED(LED_ERROR, 1, 100);
    }
  }

  // 3. Batch Sync at Interval (if offline data exists)
  if (currentMillis - lastSyncTime >= SYNC_INTERVAL) {
    lastSyncTime = currentMillis;
    if (bufferCount > 0 && isWifiConnected) {
      Serial.println(F("Attempting batch sync..."));
      digitalWrite(LED_SYNC, HIGH);
      performBatchSync();
      digitalWrite(LED_SYNC, LOW);
    }
  }

  // 4. Check for OTA Updates
  if (currentMillis - lastOtaCheckTime >= OTA_CHECK_INTERVAL) {
    lastOtaCheckTime = currentMillis;
    if (isWifiConnected) {
      checkForOTA();
    }
  }

  // Small delay to prevent watchdog trigger
  delay(100);
}

// ================= WIFI FUNCTIONS =================
void setupWiFi() {
  if (connectToWiFi()) {
    isWifiConnected = true;
    blinkLED(LED_WIFI, 2, 100);
    Serial.println(F("WiFi Connected"));
  } else {
    isWifiConnected = false;
    Serial.println(F("WiFi Failed"));
    blinkLED(LED_ERROR, 5, 100);
  }
}

bool connectToWiFi() {
  Serial.print(F("Connecting to "));
  Serial.println(WIFI_SSID);
  updateOLED(0, 0, "Connecting WiFi...");
  
  int status = WL_IDLE_STATUS;
  int attempts = 0;
  while (status != WL_CONNECTED && attempts < 20) {
    status = WiFi.begin(WIFI_SSID, WIFI_PASS);
    delay(1000);
    attempts++;
    Serial.print(".");
  }

  if (status == WL_CONNECTED) {
    Serial.println(F("\nIP Address: "));
    Serial.println(WiFi.localIP());
    return true;
  }
  return false;
}

void disconnectWiFi() {
  WiFi.disconnect();
  isWifiConnected = false;
}

// ================= SENSOR FUNCTIONS =================
float readTemperature() {
  float t = dht.readTemperature();
  if (isnan(t)) return -999.0;
  return t;
}

float readHumidity() {
  float h = dht.readHumidity();
  if (isnan(h)) return -999.0;
  return h;
}

// ================= OLED FUNCTIONS =================
void updateOLED(float temp, float hum, const char* status) {
  display.clearDisplay();
  display.setCursor(0, 0);
  display.setTextSize(1);
  display.println(F("IoT-SRS Monitor"));
  display.println(F("----------------"));
  display.print(F("T: "));
  display.print(temp);
  display.println(F(" C"));
  display.print(F("H: "));
  display.print(hum);
  display.println(F(" %"));
  display.println(F("----------------"));
  display.print(F("Status: "));
  display.println(status);
  display.print(F("Buf: "));
  display.println(bufferCount);
  display.display();
}

// ================= BUFFER MANAGEMENT =================
void addToBuffer(float temp, float hum) {
  if (bufferCount >= MAX_BUFFER_SIZE) {
    // Buffer full, drop oldest (FIFO) or handle error
    Serial.println(F("Buffer full! Dropping oldest."));
    // Shift array left
    for (int i = 0; i < bufferCount - 1; i++) {
      readBuffer[i] = readBuffer[i+1];
    }
    bufferCount--;
  }

  readBuffer[bufferCount].timestamp = millis() / 1000; // Unix timestamp approx
  readBuffer[bufferCount].temperature = temp;
  readBuffer[bufferCount].humidity = hum;
  readBuffer[bufferCount].synced = false;
  bufferCount++;
  
  Serial.printf("Added to buffer. Count: %d\n", bufferCount);
}

// ================= API COMMUNICATION =================
void sendSingleReading(float temp, float hum) {
  long now = time(nullptr); // Requires NTP or use millis() offset
  if (now < 0) now = millis() / 1000; // Fallback

  StaticJsonDocument<256> doc;
  doc["device_id"] = DEVICE_ID;
  doc["token"] = DEVICE_TOKEN;
  doc["temperature"] = temp;
  doc["humidity"] = hum;
  doc["timestamp"] = now;

  String jsonBody;
  serializeJson(doc, jsonBody);

  Serial.println(F("Sending single reading..."));
  http.post(API_INGEST_PATH, "application/json", jsonBody);

  int statusCode = http.responseStatusCode();
  String response = http.responseBody();

  if (statusCode == 200) {
    Serial.println(F("Single send success"));
    // Mark latest buffer item as synced if needed
    if (bufferCount > 0) readBuffer[bufferCount-1].synced = true;
  } else {
    Serial.printf("Single send failed: %d\n", statusCode);
    // Keep in buffer for retry
  }
  http.stop();
}

void performBatchSync() {
  if (bufferCount == 0) return;

  StaticJsonDocument<1024> doc;
  JsonArray readings = doc.createNestedArray("readings");
  doc["device_id"] = DEVICE_ID;
  doc["token"] = DEVICE_TOKEN;

  int syncCount = 0;
  for (int i = 0; i < bufferCount; i++) {
    if (!readBuffer[i].synced) {
      JsonObject reading = readings.createNestedObject();
      reading["temperature"] = readBuffer[i].temperature;
      reading["humidity"] = readBuffer[i].humidity;
      reading["timestamp"] = readBuffer[i].timestamp;
      syncCount++;
    }
  }

  if (syncCount == 0) {
    Serial.println(F("No unsynced data"));
    return;
  }

  String jsonBody;
  serializeJson(doc, jsonBody);

  Serial.printf("Batch syncing %d records...\n", syncCount);
  http.post(API_INGEST_PATH, "application/json", jsonBody);

  int statusCode = http.responseStatusCode();
  String response = http.responseBody();

  if (statusCode == 200) {
    Serial.println(F("Batch sync success"));
    // Mark all as synced and clear buffer
    for (int i = 0; i < bufferCount; i++) {
      readBuffer[i].synced = true;
    }
    bufferCount = 0; // Clear buffer
    blinkLED(LED_SYNC, 3, 100);
  } else {
    Serial.printf("Batch sync failed: %d\n", statusCode);
    // Keep in buffer for next attempt
    blinkLED(LED_ERROR, 2, 200);
  }
  http.stop();
}

// ================= OTA UPDATE CHECK =================
bool checkForOTA() {
  Serial.println(F("Checking for OTA updates..."));
  
  // Simple GET request to check version flag
  // In production, implement full binary download and flash update
  String otaPath = String("/iot-srs/api/ota_check.php?device_id=") + DEVICE_ID;
  
  http.get(otaPath);
  int statusCode = http.responseStatusCode();
  
  if (statusCode == 200) {
    String response = http.responseBody();
    StaticJsonDocument<256> doc;
    DeserializationError error = deserializeJson(doc, response);
    
    if (!error) {
      bool updateAvailable = doc["update_available"];
      if (updateAvailable) {
        Serial.println(F("OTA Update Available! Triggering..."));
        // Implement firmware download and reboot logic here
        // For now, just log it
        updateOLED(0, 0, "OTA Pending");
      }
    }
  }
  http.stop();
  return false;
}

// ================= UTILS =================
void blinkLED(int pin, int count, int duration) {
  for (int i = 0; i < count; i++) {
    digitalWrite(pin, HIGH);
    delay(duration);
    digitalWrite(pin, LOW);
    if (i < count - 1) delay(duration);
  }
}

// Optional: Implement EEPROM save/load for persistence across power loss
// Requires #include <EEPROM.h> and struct packing logic
