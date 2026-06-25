# IoT-SRS - PHP/SQL Server Application

## 📋 Overview
Complete IoT Sensor Remote Monitoring System built with PHP 8.x and SQL Server.

## 🏗️ Project Structure

```
iot-srs/
├── config/
│   ├── database.php          # Database configuration
│   └── database_schema.sql   # SQL Server schema
├── api/
│   ├── ingest.php            # Device data ingestion endpoint
│   ├── login.php             # User authentication
│   ├── dashboard.php         # Dashboard data API
│   ├── alerts.php            # Alert management API
│   └── export.php            # Data export (CSV/Excel/JSON)
├── includes/
│   ├── Database.php          # Database connection class
│   └── Auth.php              # Authentication helpers
├── cron/
│   └── cleanup.php           # Automated data cleanup job
├── assets/                   # Static files (CSS, JS, images)
├── index.php                 # Main application (login + dashboard)
└── README.md                 # This file
```

## 🚀 Installation

### Prerequisites
- PHP 8.0+ with SQL Server extensions (`pdo_sqlsrv`, `sqlsrv`)
- SQL Server 2019+
- Web server (Apache/Nginx/IIS)

### 1. Database Setup

```bash
# Run the schema script in SQL Server Management Studio
# or via sqlcmd:
sqlcmd -S localhost -U sa -P your_password -i config/database_schema.sql
```

### 2. Configuration

Edit `config/database.php`:

```php
return [
    'server' => 'YOUR_SQL_SERVER',
    'database' => 'iot_srs_db',
    'username' => 'your_username',
    'password' => 'your_password',
];
```

Or set environment variables:
- `DB_SERVER`
- `DB_NAME`
- `DB_USER`
- `DB_PASS`

### 3. Web Server Setup

#### Apache
```apache
<VirtualHost *:80>
    DocumentRoot /var/www/iot-srs
    <Directory /var/www/iot-srs>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

#### Nginx + PHP-FPM
```nginx
server {
    listen 80;
    root /var/www/iot-srs;
    index index.php;
    
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
}
```

### 4. Set Permissions

```bash
chmod -R 755 /path/to/iot-srs
chown -R www-data:www-data /path/to/iot-srs
```

## 🔧 Default Credentials

- **Username:** `admin`
- **Password:** `admin123`

⚠️ **Change immediately after first login!**

## 📡 API Endpoints

### Device Ingestion
```bash
POST /api/ingest.php
Content-Type: application/json

{
  "device_token": "your_device_token",
  "timestamp": "2026-06-25T10:30:00Z",
  "temperature": 25.5,
  "humidity": 60.2
}
```

### Batch Upload (Offline Sync)
```bash
POST /api/ingest.php
Content-Type: application/json

{
  "device_token": "your_device_token",
  "batch": [
    {"timestamp": "...", "temperature": 25.5, "humidity": 60.2},
    {"timestamp": "...", "temperature": 26.1, "humidity": 59.8}
  ]
}
```

### Login
```bash
POST /api/login.php
Content-Type: application/json

{"username": "admin", "password": "admin123"}
```

### Dashboard Data
```bash
GET /api/dashboard.php?action=summary
GET /api/dashboard.php?action=chart&device_id=1&hours=24
GET /api/dashboard.php?action=alerts
GET /api/dashboard.php?action=kanban
```

### Alert Management
```bash
POST /api/alerts.php
{"alert_id": 1, "action": "acknowledge"}
POST /api/alerts.php
{"alert_id": 1, "action": "resolve"}
```

### Export Data
```bash
GET /api/export.php?type=csv&device_id=1&from=2026-06-01&to=2026-06-25
GET /api/export.php?type=excel&device_id=1
GET /api/export.php?type=json&device_id=1
```

## ⏰ Scheduled Tasks

### Daily Cleanup (SQL Server Agent)

Create a SQL Server Agent Job:

```sql
-- Step: Execute cleanup
EXEC sp_CleanupOldData
```

Schedule: Daily at 2:00 AM

### Alternative: System Cron

```bash
# Edit crontab
crontab -e

# Add daily cleanup at 2 AM
0 2 * * * /usr/bin/php /path/to/iot-srs/cron/cleanup.php >> /var/log/iot-cleanup.log 2>&1
```

## 🧪 Testing

### Test Device Ingestion

```bash
curl -X POST http://localhost/api/ingest.php \
  -H "Content-Type: application/json" \
  -d '{
    "device_token": "test_token_123",
    "timestamp": "'$(date -u +%Y-%m-%dT%H:%M:%SZ)'",
    "temperature": 25.5,
    "humidity": 60.2
  }'
```

### Test Login

```bash
curl -X POST http://localhost/api/login.php \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"admin123"}'
```

## 🔐 Security Features

- ✅ Bcrypt password hashing (cost factor 12)
- ✅ Role-based access control (Admin/Viewer)
- ✅ Device token authentication
- ✅ SQL injection prevention (prepared statements)
- ✅ XSS protection (htmlspecialchars)
- ✅ Audit logging for all actions
- ✅ Session management

## 📊 Key Features Implemented

| Feature | Status | File |
|---------|--------|------|
| Device Registration | ✅ | api/ingest.php |
| Sensor Data Collection | ✅ | api/ingest.php |
| Offline Buffer & Batch Sync | ✅ | api/ingest.php |
| Real-time Dashboard | ✅ | index.php |
| Kanban View | ✅ | api/dashboard.php |
| Charts & Graphs | ✅ | index.php + Chart.js |
| Alert Management | ✅ | api/alerts.php |
| Alert Hysteresis | ✅ | DB stored procedure |
| Data Export (CSV/Excel) | ✅ | api/export.php |
| OTA Firmware Updates | ✅ | Database schema ready |
| Auto Data Cleanup (>2 years) | ✅ | cron/cleanup.php |
| Audit Logging | ✅ | Throughout API |
| User Authentication | ✅ | api/login.php |

## 🛠️ Troubleshooting

### SQL Server Connection Issues

1. Verify PHP extensions:
```bash
php -m | grep -i sqlsrv
```

2. Install if missing:
```bash
# Ubuntu/Debian
pecl install sqlsrv pdo_sqlsrv

# Add to php.ini
extension=sqlsrv.so
extension=pdo_sqlsrv.so
```

### Permission Denied

```bash
chown -R www-data:www-data /path/to/iot-srs
chmod -R 755 /path/to/iot-srs
```

### Check Logs

```bash
# PHP errors
tail -f /var/log/php/error.log

# Web server
tail -f /var/log/nginx/error.log
```

## 📝 Arduino Example Code

```cpp
#include <WiFi.h>
#include <HTTPClient.h>
#include <DHT.h>

#define DHTPIN 2
#define DHTTYPE DHT22
#define DEVICE_TOKEN "your_unique_token_here"

DHT dht(DHTPIN, DHTTYPE);

void setup() {
  Serial.begin(115200);
  dht.begin();
  WiFi.begin("SSID", "PASSWORD");
  
  while (WiFi.status() != WL_CONNECTED) {
    delay(500);
  }
}

void loop() {
  float temp = dht.readTemperature();
  float humid = dht.readHumidity();
  
  if (!isnan(temp) && !isnan(humid)) {
    HTTPClient http;
    http.begin("http://your-server/api/ingest.php");
    http.addHeader("Content-Type", "application/json");
    
    String json = "{\"device_token\":\"" + String(DEVICE_TOKEN) + 
                  "\",\"timestamp\":\"" + getTimestamp() + 
                  "\",\"temperature\":" + String(temp) +
                  ",\"humidity\":" + String(humid) + "}";
    
    int code = http.POST(json);
    http.end();
  }
  
  delay(60000); // Send every minute
}

String getTimestamp() {
  // Implement NTP time sync
  return "2026-06-25T10:30:00Z";
}
```

## 📄 License

MIT License - See LICENSE file for details.

## 👥 Support

For issues and feature requests, please contact the development team.

---
**Version:** 2.0  
**Last Updated:** 25/06/2026  
**Tech Stack:** PHP 8.x + SQL Server 2019+
