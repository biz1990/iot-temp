# Arduino Firmware Installation Guide

## Hardware Requirements

- **Board**: Arduino Uno R4 WiFi
- **Sensor**: DHT22 (AM2302) Temperature & Humidity Sensor
- **Display**: 0.96" OLED Display (SSD1306, I2C, 128x64)
- **Wiring**:
  - DHT22 Data Pin → Digital Pin 2
  - DHT22 VCC → 5V
  - DHT22 GND → GND
  - OLED SDA → A4 (I2C)
  - OLED SCL → A5 (I2C)
  - OLED VCC → 5V
  - OLED GND → GND
  - 10K pull-up resistor between DHT22 Data and VCC

## Software Requirements

### Option 1: Arduino IDE

1. Install Arduino IDE 2.x from https://www.arduino.cc/en/software
2. Add Arduino Uno R4 WiFi board support:
   - Go to **File > Preferences**
   - Add to "Additional Boards Manager URLs":
     ```
     https://downloads.arduino.cc/packages/package_index.json
     ```
   - Go to **Tools > Board > Boards Manager**
   - Search for "Arduino Renesas Boards" and install
   - Select **Arduino Uno R4 WiFi** board

3. Install required libraries via Library Manager:
   - `ArduinoHttpClient` by Arduino
   - `DHT sensor library` by Adafruit
   - `ArduinoJson` by Benoit Blanchon (v7.x)
   - `Adafruit SSD1306` by Adafruit
   - `Adafruit GFX Library` by Adafruit
   - `Adafruit BusIO` by Adafruit
   - `Adafruit Unified Sensor` by Adafruit

### Option 2: PlatformIO (Recommended)

1. Install VS Code from https://code.visualstudio.com/
2. Install PlatformIO extension
3. Open the `firmware` folder in VS Code
4. PlatformIO will auto-install all dependencies
5. Build and upload via PlatformIO toolbar

## Configuration

Before uploading, edit `arduino_uno_r4_wifi.ino` and update:

```cpp
// WiFi Credentials
const char* WIFI_SSID = "YOUR_WIFI_SSID";
const char* WIFI_PASS = "YOUR_WIFI_PASSWORD";

// Server Configuration
const char* SERVER_HOST = "192.168.1.100"; // Your PHP server IP
const int SERVER_PORT = 80;
const char* API_INGEST_PATH = "/iot-srs/api/ingest.php";

// Device Identity (Get from Admin Dashboard after registration)
const char* DEVICE_TOKEN = "abc123xyz789..."; // Unique token per device
const char* DEVICE_ID = "DEV-001"; // Must match DB record
```

## Upload Instructions

### Using Arduino IDE

1. Connect Arduino Uno R4 WiFi via USB-C cable
2. Select **Tools > Board > Arduino Uno R4 WiFi**
3. Select correct **Port** (COMx on Windows, /dev/ttyACMx on Linux)
4. Click **Upload** button (→ arrow icon)
5. Open **Serial Monitor** (baud rate: 9600) to view logs

### Using PlatformIO

1. Connect Arduino via USB-C
2. Click **Upload** button (→ arrow with checkmark) in bottom toolbar
3. Click **Serial Monitor** button (plug icon) to view output

## Verification

After upload, you should see:

1. **OLED Display** shows:
   - "IoT-SRS Monitor"
   - Temperature and Humidity readings
   - Status: "Online" or "Offline"
   - Buffer count

2. **Serial Monitor** output:
   ```
   === IoT-SRS Firmware Starting ===
   Connecting to YOUR_WIFI_SSID
   ............
   IP Address: 192.168.1.XXX
   WiFi Connected
   Setup complete. Entering loop.
   Read: T=25.30, H=60.20
   Added to buffer. Count: 1
   Sending single reading...
   Single send success
   ```

3. **PHP Dashboard** (`http://your-server/iot-srs/`):
   - New device appears in Kanban view
   - Real-time charts update every minute
   - Data logged in `sensor_readings` table

## Troubleshooting

### WiFi Connection Fails
- Verify SSID and password are correct
- Ensure 2.4GHz network (R4 WiFi doesn't support 5GHz)
- Check signal strength near device
- Try static IP if DHCP fails

### Sensor Returns NaN
- Check wiring connections
- Verify 10K pull-up resistor on data pin
- Try different GPIO pin (update `DHT_PIN`)
- DHT22 is slow - don't read faster than once per 2 seconds

### OLED Not Displaying
- Verify I2C address (run I2C scanner sketch)
- Check SDA/SCL connections
- Some OLEDs use 0x3D instead of 0x3C

### API Returns Error
- Verify `SERVER_HOST` is reachable from Arduino
- Check firewall allows port 80/443
- Validate `DEVICE_TOKEN` exists in database
- Check PHP error logs

### Buffer Keeps Growing
- Indicates sync failures
- Check server logs for API errors
- Verify device token authentication
- Test API endpoint manually with Postman

## OTA Update (Future Enhancement)

Current firmware checks for OTA availability but doesn't implement full binary download. To enable OTA:

1. Implement HTTPS secure download
2. Add firmware signature verification
3. Use ArduinoOTA library for Renesas RA4M1
4. Create dual-bank flash partition scheme

## Power Optimization

For battery-powered deployments:

1. Replace `delay()` with `LowPower.sleep()`
2. Reduce `READ_INTERVAL` to 5-15 minutes
3. Disable OLED between updates
4. Use external RTC for precise timing
5. Add LiPo battery management circuit

## Support

For issues, refer to:
- SRS Document: `/workspace/IoT-SRS.md`
- API Documentation: `/workspace/iot-srs/README.md`
- Serial debug output (9600 baud)
