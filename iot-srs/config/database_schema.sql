-- IoT-SRS Database Schema for SQL Server 2019+
-- Execute this script to create all required tables, indexes, and stored procedures

USE master;
GO

-- Create database if not exists
IF NOT EXISTS (SELECT * FROM sys.databases WHERE name = 'iot_srs_db')
BEGIN
    CREATE DATABASE iot_srs_db;
END
GO

USE iot_srs_db;
GO

-- ============================================
-- USERS TABLE
-- ============================================
IF OBJECT_ID('users', 'U') IS NULL
BEGIN
    CREATE TABLE users (
        id INT IDENTITY(1,1) PRIMARY KEY,
        username NVARCHAR(50) UNIQUE NOT NULL,
        email NVARCHAR(100) UNIQUE NOT NULL,
        password_hash NVARCHAR(255) NOT NULL,
        full_name NVARCHAR(100),
        role NVARCHAR(20) DEFAULT 'viewer' CHECK (role IN ('admin', 'viewer')),
        is_active BIT DEFAULT 1,
        failed_login_attempts INT DEFAULT 0,
        lockout_until DATETIME NULL,
        last_login DATETIME NULL,
        created_at DATETIME DEFAULT GETDATE(),
        updated_at DATETIME DEFAULT GETDATE()
    );
    
    CREATE INDEX IX_users_username ON users(username);
    CREATE INDEX IX_users_email ON users(email);
END
GO

-- ============================================
-- DEVICES TABLE
-- ============================================
IF OBJECT_ID('devices', 'U') IS NULL
BEGIN
    CREATE TABLE devices (
        id INT IDENTITY(1,1) PRIMARY KEY,
        user_id INT NOT NULL FOREIGN KEY REFERENCES users(id) ON DELETE CASCADE,
        device_token NVARCHAR(64) UNIQUE NOT NULL,
        device_name NVARCHAR(100) NOT NULL,
        device_type NVARCHAR(50) DEFAULT 'arduino_r4_wifi',
        description NVARCHAR(500),
        location NVARCHAR(200),
        is_active BIT DEFAULT 1,
        last_seen DATETIME NULL,
        firmware_version NVARCHAR(20),
        created_at DATETIME DEFAULT GETDATE(),
        updated_at DATETIME DEFAULT GETDATE(),
        deleted_at DATETIME NULL
    );
    
    CREATE INDEX IX_devices_user_id ON devices(user_id);
    CREATE INDEX IX_devices_token ON devices(device_token);
    CREATE INDEX IX_devices_active ON devices(is_active);
END
GO

-- ============================================
-- SENSOR READINGS TABLE
-- ============================================
IF OBJECT_ID('sensor_readings', 'U') IS NULL
BEGIN
    CREATE TABLE sensor_readings (
        id BIGINT IDENTITY(1,1) PRIMARY KEY,
        device_id INT NOT NULL FOREIGN KEY REFERENCES devices(id),
        temperature DECIMAL(5,2) NOT NULL,
        humidity DECIMAL(5,2) NOT NULL,
        battery_level DECIMAL(5,2) NULL,
        signal_strength INT NULL,
        recorded_at DATETIME NOT NULL DEFAULT GETDATE()
    );
    
    CREATE INDEX IX_sensor_readings_device_id ON sensor_readings(device_id);
    CREATE INDEX IX_sensor_readings_recorded_at ON sensor_readings(recorded_at);
    CREATE INDEX IX_sensor_readings_device_time ON sensor_readings(device_id, recorded_at);
END
GO

-- ============================================
-- ALERTS TABLE (Alert Rules)
-- ============================================
IF OBJECT_ID('alerts', 'U') IS NULL
BEGIN
    CREATE TABLE alerts (
        id INT IDENTITY(1,1) PRIMARY KEY,
        device_id INT NOT NULL FOREIGN KEY REFERENCES devices(id) ON DELETE CASCADE,
        alert_type NVARCHAR(50) NOT NULL CHECK (alert_type IN (
            'temperature_high', 'temperature_low', 
            'humidity_high', 'humidity_low'
        )),
        threshold_value DECIMAL(5,2) NOT NULL,
        hysteresis DECIMAL(5,2) DEFAULT 2.0,
        status NVARCHAR(20) DEFAULT 'active' CHECK (status IN ('active', 'inactive')),
        last_triggered DATETIME NULL,
        trigger_count INT DEFAULT 0,
        created_at DATETIME DEFAULT GETDATE(),
        updated_at DATETIME DEFAULT GETDATE()
    );
    
    CREATE INDEX IX_alerts_device_id ON alerts(device_id);
    CREATE INDEX IX_alerts_status ON alerts(status);
END
GO

-- ============================================
-- ALERT HISTORY TABLE
-- ============================================
IF OBJECT_ID('alert_history', 'U') IS NULL
BEGIN
    CREATE TABLE alert_history (
        id BIGINT IDENTITY(1,1) PRIMARY KEY,
        alert_id INT NOT NULL FOREIGN KEY REFERENCES alerts(id) ON DELETE CASCADE,
        device_id INT NOT NULL FOREIGN KEY REFERENCES devices(id),
        triggered_at DATETIME NOT NULL DEFAULT GETDATE(),
        acknowledged_at DATETIME NULL,
        acknowledged_by INT NULL FOREIGN KEY REFERENCES users(id),
        value DECIMAL(5,2) NOT NULL,
        threshold DECIMAL(5,2) NOT NULL,
        status NVARCHAR(20) DEFAULT 'active' CHECK (status IN ('active', 'acknowledged', 'resolved'))
    );
    
    CREATE INDEX IX_alert_history_device_id ON alert_history(device_id);
    CREATE INDEX IX_alert_history_status ON alert_history(status);
    CREATE INDEX IX_alert_history_triggered_at ON alert_history(triggered_at);
END
GO

-- ============================================
-- OTA FIRMWARE TABLE
-- ============================================
IF OBJECT_ID('ota_firmware', 'U') IS NULL
BEGIN
    CREATE TABLE ota_firmware (
        id INT IDENTITY(1,1) PRIMARY KEY,
        version NVARCHAR(20) UNIQUE NOT NULL,
        device_type NVARCHAR(50) NOT NULL,
        file_path NVARCHAR(500) NOT NULL,
        file_size INT NOT NULL,
        checksum NVARCHAR(64) NOT NULL,
        release_notes NVARCHAR(1000),
        is_current BIT DEFAULT 0,
        created_at DATETIME DEFAULT GETDATE(),
        created_by INT FOREIGN KEY REFERENCES users(id)
    );
    
    CREATE INDEX IX_ota_firmware_version ON ota_firmware(version);
    CREATE INDEX IX_ota_firmware_device_type ON ota_firmware(device_type);
END
GO

-- ============================================
-- AUDIT LOGS TABLE
-- ============================================
IF OBJECT_ID('audit_logs', 'U') IS NULL
BEGIN
    CREATE TABLE audit_logs (
        id BIGINT IDENTITY(1,1) PRIMARY KEY,
        user_id INT NULL FOREIGN KEY REFERENCES users(id),
        action NVARCHAR(100) NOT NULL,
        entity_type NVARCHAR(50),
        entity_id INT,
        old_values NVARCHAR(MAX),
        new_values NVARCHAR(MAX),
        ip_address NVARCHAR(45),
        user_agent NVARCHAR(500),
        created_at DATETIME DEFAULT GETDATE()
    );
    
    CREATE INDEX IX_audit_logs_user_id ON audit_logs(user_id);
    CREATE INDEX IX_audit_logs_action ON audit_logs(action);
    CREATE INDEX IX_audit_logs_created_at ON audit_logs(created_at);
END
GO

-- ============================================
-- CREATE DEFAULT ADMIN USER
-- ============================================
-- Password: admin123 (hashed with bcrypt cost 12)
IF NOT EXISTS (SELECT * FROM users WHERE username = 'admin')
BEGIN
    INSERT INTO users (username, email, password_hash, full_name, role, is_active)
    VALUES (
        'admin',
        'admin@iot-srs.local',
        '$2y$12$LQv3c1yqBWVHxkd0LHAkCOYz6TtxMQJqhN8/X4.G.2IeFjO1Xyeuq',
        'System Administrator',
        'admin',
        1
    );
END
GO

-- ============================================
-- STORED PROCEDURE: Cleanup Old Data
-- ============================================
IF OBJECT_ID('sp_cleanup_old_data', 'P') IS NOT NULL
    DROP PROCEDURE sp_cleanup_old_data;
GO

CREATE PROCEDURE sp_cleanup_old_data
    @retentionDays INT = 730
AS
BEGIN
    SET NOCOUNT ON;
    
    DECLARE @cutoffDate DATETIME = DATEADD(DAY, -@retentionDays, GETDATE());
    DECLARE @deletedCount INT;
    
    BEGIN TRANSACTION;
    
    BEGIN TRY
        -- Delete old sensor readings
        DELETE FROM sensor_readings 
        WHERE recorded_at < @cutoffDate;
        
        SET @deletedCount = @@ROWCOUNT;
        
        -- Delete old alert history (resolved/acknowledged only)
        DELETE FROM alert_history 
        WHERE triggered_at < @cutoffDate 
        AND status IN ('acknowledged', 'resolved');
        
        -- Log the cleanup
        INSERT INTO audit_logs (action, entity_type, old_values, created_at)
        VALUES (
            'DATA_CLEANUP',
            'sensor_readings',
            CONCAT('Deleted ', @deletedCount, ' records older than ', @cutoffDate),
            GETDATE()
        );
        
        COMMIT TRANSACTION;
        
        SELECT @deletedCount AS deleted_readings;
    END TRY
    BEGIN CATCH
        ROLLBACK TRANSACTION;
        THROW;
    END CATCH
END
GO

-- ============================================
-- STORED PROCEDURE: Get Dashboard Overview
-- ============================================
IF OBJECT_ID('sp_dashboard_overview', 'P') IS NOT NULL
    DROP PROCEDURE sp_dashboard_overview;
GO

CREATE PROCEDURE sp_dashboard_overview
    @userId INT = NULL
AS
BEGIN
    SET NOCOUNT ON;
    
    -- Device statistics
    SELECT 
        COUNT(*) AS total_devices,
        SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) AS active_devices,
        SUM(CASE WHEN last_seen > DATEADD(MINUTE, -10, GETDATE()) THEN 1 ELSE 0 END) AS online_devices
    FROM devices
    WHERE @userId IS NULL OR user_id = @userId;
    
    -- Recent alerts
    SELECT TOP 10
        ah.id,
        ah.device_id,
        d.device_name,
        a.alert_type,
        ah.value,
        ah.threshold,
        ah.triggered_at,
        ah.status
    FROM alert_history ah
    JOIN alerts a ON ah.alert_id = a.id
    JOIN devices d ON ah.device_id = d.id
    WHERE @userId IS NULL OR d.user_id = @userId
    ORDER BY ah.triggered_at DESC;
    
    -- Latest readings summary
    SELECT 
        COUNT(DISTINCT r.device_id) AS devices_with_data,
        AVG(r.temperature) AS avg_temperature,
        AVG(r.humidity) AS avg_humidity,
        MAX(r.recorded_at) AS last_reading_time
    FROM sensor_readings r
    JOIN devices d ON r.device_id = d.id
    WHERE r.recorded_at > DATEADD(HOUR, -24, GETDATE())
    AND (@userId IS NULL OR d.user_id = @userId);
END
GO

-- ============================================
-- VIEW: Device Status Summary
-- ============================================
IF OBJECT_ID('vw_device_status', 'V') IS NOT NULL
    DROP VIEW vw_device_status;
GO

CREATE VIEW vw_device_status AS
SELECT 
    d.id,
    d.device_name,
    d.device_type,
    d.location,
    d.is_active,
    CASE 
        WHEN d.last_seen > DATEADD(MINUTE, -10, GETDATE()) THEN 'online'
        WHEN d.last_seen > DATEADD(HOUR, -24, GETDATE()) THEN 'offline_recent'
        ELSE 'offline'
    END AS status,
    d.last_seen,
    u.username AS owner_username,
    (SELECT TOP 1 temperature FROM sensor_readings WHERE device_id = d.id ORDER BY recorded_at DESC) AS last_temperature,
    (SELECT TOP 1 humidity FROM sensor_readings WHERE device_id = d.id ORDER BY recorded_at DESC) AS last_humidity,
    (SELECT COUNT(*) FROM alert_history WHERE device_id = d.id AND status = 'active') AS active_alert_count
FROM devices d
JOIN users u ON d.user_id = u.id;
GO

-- ============================================
-- VIEW: Alert Summary
-- ============================================
IF OBJECT_ID('vw_alert_summary', 'V') IS NOT NULL
    DROP VIEW vw_alert_summary;
GO

CREATE VIEW vw_alert_summary AS
SELECT 
    d.id AS device_id,
    d.device_name,
    COUNT(CASE WHEN ah.status = 'active' THEN 1 END) AS active_alerts,
    COUNT(CASE WHEN ah.status = 'acknowledged' THEN 1 END) AS acknowledged_alerts,
    MAX(ah.triggered_at) AS last_alert_time,
    STRING_AGG(DISTINCT a.alert_type, ', ') AS alert_types
FROM devices d
LEFT JOIN alerts al ON d.id = al.device_id
LEFT JOIN alert_history ah ON al.id = ah.alert_id
GROUP BY d.id, d.device_name;
GO

PRINT 'Database schema created successfully!';
PRINT 'Default admin user: admin / admin123';
PRINT 'Remember to change the default password after first login!';
GO
