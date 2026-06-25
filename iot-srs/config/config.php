<?php
/**
 * IoT-SRS Configuration File
 * 
 * Environment-specific settings for the application
 */

// Application Settings
define('APP_NAME', 'IoT-SRS');
define('APP_VERSION', '2.0.0');
define('APP_ENV', 'development'); // development | production
define('APP_URL', 'http://localhost/iot-srs');
define('TIMEZONE', 'Asia/Ho_Chi_Minh');

// Database Configuration (SQL Server)
define('DB_HOST', 'localhost');
define('DB_PORT', '1433');
define('DB_NAME', 'iot_srs_db');
define('DB_USER', 'sa');
define('DB_PASS', 'YourStrong@Passw0rd');
define('DB_CHARSET', 'UTF-8');

// Security Settings
define('JWT_SECRET', 'your-super-secret-jwt-key-change-in-production');
define('JWT_EXPIRY', 3600); // 1 hour
define('PASSWORD_MIN_LENGTH', 8);
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_TIME', 900); // 15 minutes

// Device Settings
define('DEVICE_TOKEN_PREFIX', 'DEV_');
define('MAX_OFFLINE_BUFFER', 100);
define('DATA_RETENTION_DAYS', 730); // 2 years
define('HEARTBEAT_INTERVAL', 300); // 5 minutes

// Alert Settings
define('ALERT_CHECK_INTERVAL', 60); // seconds
define('HYSTERESIS_DEFAULT', 2.0); // degrees Celsius
define('MAX_ALERTS_PER_DEVICE', 10);

// OTA Settings
define('OTA_ENABLED', true);
define('FIRMWARE_UPLOAD_PATH', __DIR__ . '/uploads/firmware/');
define('MAX_FIRMWARE_SIZE', 5242880); // 5MB

// Export Settings
define('EXPORT_MAX_ROWS', 100000);
define('TEMP_EXPORT_PATH', __DIR__ . '/uploads/temp/');

// Logging Settings
define('LOG_PATH', __DIR__ . '/logs/');
define('LOG_LEVEL', 'DEBUG'); // DEBUG, INFO, WARNING, ERROR, CRITICAL
define('LOG_MAX_FILES', 30);
define('LOG_ROTATION', 'daily');

// Rate Limiting
define('RATE_LIMIT_ENABLED', true);
define('RATE_LIMIT_REQUESTS', 100); // per minute
define('RATE_LIMIT_WINDOW', 60); // seconds

// CORS Settings
define('CORS_ENABLED', true);
define('CORS_ALLOWED_ORIGINS', ['*']);
define('CORS_ALLOWED_METHODS', ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS']);
define('CORS_ALLOWED_HEADERS', ['Content-Type', 'Authorization', 'X-Device-Token']);

// Session Settings
define('SESSION_LIFETIME', 7200); // 2 hours
define('SESSION_SECURE', false); // true for HTTPS only
define('SESSION_HTTPONLY', true);

// Pagination
define('DEFAULT_PAGE_SIZE', 20);
define('MAX_PAGE_SIZE', 100);

// Timezone Setup
date_default_timezone_set(TIMEZONE);

// Create necessary directories
$directories = [
    LOG_PATH,
    FIRMWARE_UPLOAD_PATH,
    TEMP_EXPORT_PATH
];

foreach ($directories as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}
