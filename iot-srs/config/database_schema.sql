-- IoT-SRS Database Schema for SQL Server
-- Version: 2.0
-- Date: 25/06/2026

-- Create Database
CREATE DATABASE iot_srs_db;
GO

USE iot_srs_db;
GO

-- Users Table (FR-013, FR-014)
CREATE TABLE Users (
    UserID INT IDENTITY(1,1) PRIMARY KEY,
    Username NVARCHAR(50) NOT NULL UNIQUE,
    PasswordHash NVARCHAR(255) NOT NULL,
    Email NVARCHAR(100) NOT NULL UNIQUE,
    FullName NVARCHAR(100),
    Role NVARCHAR(20) NOT NULL CHECK (Role IN ('Admin', 'Viewer')),
    IsActive BIT DEFAULT 1,
    CreatedAt DATETIME2 DEFAULT GETDATE(),
    LastLogin DATETIME2,
    UpdatedAt DATETIME2 DEFAULT GETDATE()
);
GO

-- Devices Table (FR-001, FR-002)
CREATE TABLE Devices (
    DeviceID INT IDENTITY(1,1) PRIMARY KEY,
    DeviceToken NVARCHAR(64) NOT NULL UNIQUE,
    DeviceName NVARCHAR(100) NOT NULL,
    Location NVARCHAR(200),
    Description NVARCHAR(500),
    FirmwareVersion NVARCHAR(20),
    IsOnline BIT DEFAULT 0,
    LastSeen DATETIME2,
    CreatedAt DATETIME2 DEFAULT GETDATE(),
    UpdatedAt DATETIME2 DEFAULT GETDATE()
);
GO

-- SensorData Table (FR-003, FR-004, FR-005)
CREATE TABLE SensorData (
    DataID BIGINT IDENTITY(1,1) PRIMARY KEY,
    DeviceID INT NOT NULL FOREIGN KEY REFERENCES Devices(DeviceID),
    Temperature DECIMAL(5,2),
    Humidity DECIMAL(5,2),
    Timestamp DATETIME2 NOT NULL,
    ReceivedAt DATETIME2 DEFAULT GETDATE(),
    IsValid BIT DEFAULT 1,
    INDEX IX_SensorData_Timestamp (Timestamp),
    INDEX IX_SensorData_Device (DeviceID, Timestamp)
);
GO

-- Alerts Table (FR-017, FR-018, FR-019)
CREATE TABLE AlertThresholds (
    ThresholdID INT IDENTITY(1,1) PRIMARY KEY,
    DeviceID INT NOT NULL FOREIGN KEY REFERENCES Devices(DeviceID),
    MetricType NVARCHAR(20) NOT NULL CHECK (MetricType IN ('Temperature', 'Humidity')),
    MinValue DECIMAL(5,2),
    MaxValue DECIMAL(5,2),
    Hysteresis DECIMAL(5,2) DEFAULT 0.5,
    IsActive BIT DEFAULT 1,
    CreatedAt DATETIME2 DEFAULT GETDATE(),
    UpdatedAt DATETIME2 DEFAULT GETDATE()
);
GO

-- ActiveAlerts Table
CREATE TABLE ActiveAlerts (
    AlertID INT IDENTITY(1,1) PRIMARY KEY,
    ThresholdID INT NOT NULL FOREIGN KEY REFERENCES AlertThresholds(ThresholdID),
    DeviceID INT NOT NULL FOREIGN KEY REFERENCES Devices(DeviceID),
    MetricType NVARCHAR(20) NOT NULL,
    CurrentValue DECIMAL(5,2) NOT NULL,
    ThresholdValue DECIMAL(5,2) NOT NULL,
    AlertType NVARCHAR(10) CHECK (AlertType IN ('HIGH', 'LOW')),
    TriggeredAt DATETIME2 DEFAULT GETDATE(),
    Acknowledged BIT DEFAULT 0,
    AcknowledgedBy INT FOREIGN KEY REFERENCES Users(UserID),
    AcknowledgedAt DATETIME2,
    Resolved BIT DEFAULT 0,
    ResolvedAt DATETIME2,
    INDEX IX_ActiveAlerts_Device (DeviceID),
    INDEX IX_ActiveAlerts_Active (Resolved, Acknowledged)
);
GO

-- AlertHistory Table (Audit Log for Alerts)
CREATE TABLE AlertHistory (
    HistoryID BIGINT IDENTITY(1,1) PRIMARY KEY,
    AlertID INT NOT NULL,
    DeviceID INT NOT NULL,
    MetricType NVARCHAR(20) NOT NULL,
    CurrentValue DECIMAL(5,2) NOT NULL,
    ThresholdValue DECIMAL(5,2) NOT NULL,
    AlertType NVARCHAR(10) NOT NULL,
    TriggeredAt DATETIME2 NOT NULL,
    AcknowledgedAt DATETIME2,
    ResolvedAt DATETIME2,
    DurationSeconds AS DATEDIFF(SECOND, TriggeredAt, ISNULL(ResolvedAt, GETDATE())),
    INDEX IX_AlertHistory_Triggered (TriggeredAt)
);
GO

-- AuditLog Table (FR-026)
CREATE TABLE AuditLog (
    LogID BIGINT IDENTITY(1,1) PRIMARY KEY,
    UserID INT FOREIGN KEY REFERENCES Users(UserID),
    Action NVARCHAR(50) NOT NULL,
    EntityType NVARCHAR(50),
    EntityID INT,
    OldValues NVARCHAR(MAX),
    NewValues NVARCHAR(MAX),
    IPAddress NVARCHAR(45),
    UserAgent NVARCHAR(255),
    CreatedAt DATETIME2 DEFAULT GETDATE(),
    INDEX IX_AuditLog_User (UserID),
    INDEX IX_AuditLog_Action (Action),
    INDEX IX_AuditLog_Created (CreatedAt)
);
GO

-- OTAFirmware Table (FR-020, FR-021)
CREATE TABLE OTAFirmware (
    FirmwareID INT IDENTITY(1,1) PRIMARY KEY,
    Version NVARCHAR(20) NOT NULL UNIQUE,
    Description NVARCHAR(500),
    FilePath NVARCHAR(255) NOT NULL,
    FileSize BIGINT,
    FileHash NVARCHAR(64),
    IsLatest BIT DEFAULT 0,
    UploadedBy INT FOREIGN KEY REFERENCES Users(UserID),
    UploadedAt DATETIME2 DEFAULT GETDATE(),
    INDEX IX_OTAFirmware_Latest (IsLatest)
);
GO

-- OTAUpdates Table (Track OTA update history)
CREATE TABLE OTAUpdates (
    UpdateID BIGINT IDENTITY(1,1) PRIMARY KEY,
    DeviceID INT NOT NULL FOREIGN KEY REFERENCES Devices(DeviceID),
    FirmwareID INT NOT NULL FOREIGN KEY REFERENCES OTAFirmware(FirmwareID),
    Status NVARCHAR(20) CHECK (Status IN ('Pending', 'Downloading', 'Installing', 'Completed', 'Failed')),
    ErrorMessage NVARCHAR(500),
    StartedAt DATETIME2 DEFAULT GETDATE(),
    CompletedAt DATETIME2,
    INDEX IX_OTAUpdates_Device (DeviceID),
    INDEX IX_OTAUpdates_Status (Status)
);
GO

-- OfflineBuffer Table (FR-006, FR-007)
CREATE TABLE OfflineBuffer (
    BufferID BIGINT IDENTITY(1,1) PRIMARY KEY,
    DeviceToken NVARCHAR(64) NOT NULL,
    BatchData NVARCHAR(MAX) NOT NULL, -- JSON array of sensor readings
    ReceivedAt DATETIME2 DEFAULT GETDATE(),
    Processed BIT DEFAULT 0,
    ProcessedAt DATETIME2,
    ErrorMessages NVARCHAR(MAX),
    INDEX IX_OfflineBuffer_Processed (Processed),
    INDEX IX_OfflineBuffer_Received (ReceivedAt)
);
GO

-- DashboardViews Table (Save custom dashboard configurations)
CREATE TABLE DashboardViews (
    ViewID INT IDENTITY(1,1) PRIMARY KEY,
    UserID INT NOT NULL FOREIGN KEY REFERENCES Users(UserID),
    ViewName NVARCHAR(100) NOT NULL,
    ViewConfig NVARCHAR(MAX) NOT NULL, -- JSON configuration
    IsDefault BIT DEFAULT 0,
    CreatedAt DATETIME2 DEFAULT GETDATE(),
    UpdatedAt DATETIME2 DEFAULT GETDATE()
);
GO

-- Create stored procedure for data cleanup (FR-012)
CREATE PROCEDURE sp_CleanupOldData
AS
BEGIN
    SET NOCOUNT ON;
    
    DECLARE @CutoffDate DATETIME2 = DATEADD(YEAR, -2, GETDATE());
    
    -- Delete old sensor data
    DELETE FROM SensorData 
    WHERE Timestamp < @CutoffDate;
    
    -- Delete old alert history
    DELETE FROM AlertHistory 
    WHERE TriggeredAt < @CutoffDate;
    
    -- Delete old OTA update records
    DELETE FROM OTAUpdates 
    WHERE CompletedAt < @CutoffDate AND CompletedAt IS NOT NULL;
    
    -- Delete processed offline buffer entries older than 30 days
    DELETE FROM OfflineBuffer 
    WHERE Processed = 1 AND ProcessedAt < DATEADD(DAY, -30, GETDATE());
    
    PRINT 'Cleanup completed successfully';
END;
GO

-- Create stored procedure for checking alerts
CREATE PROCEDURE sp_CheckAlerts
    @DeviceID INT,
    @Temperature DECIMAL(5,2) = NULL,
    @Humidity DECIMAL(5,2) = NULL
AS
BEGIN
    SET NOCOUNT ON;
    
    -- Check temperature alerts
    IF @Temperature IS NOT NULL
    BEGIN
        INSERT INTO ActiveAlerts (ThresholdID, DeviceID, MetricType, CurrentValue, ThresholdValue, AlertType)
        SELECT 
            at.ThresholdID,
            @DeviceID,
            'Temperature',
            @Temperature,
            CASE WHEN @Temperature > at.MaxValue THEN at.MaxValue ELSE at.MinValue END,
            CASE WHEN @Temperature > at.MaxValue THEN 'HIGH' ELSE 'LOW' END
        FROM AlertThresholds at
        WHERE at.DeviceID = @DeviceID 
            AND at.MetricType = 'Temperature'
            AND at.IsActive = 1
            AND ((@Temperature > at.MaxValue + at.Hysteresis) 
                 OR (@Temperature < at.MinValue - at.Hysteresis))
            AND NOT EXISTS (
                SELECT 1 FROM ActiveAlerts aa 
                WHERE aa.DeviceID = @DeviceID 
                    AND aa.MetricType = 'Temperature'
                    AND aa.Resolved = 0
            );
    END
    
    -- Check humidity alerts
    IF @Humidity IS NOT NULL
    BEGIN
        INSERT INTO ActiveAlerts (ThresholdID, DeviceID, MetricType, CurrentValue, ThresholdValue, AlertType)
        SELECT 
            at.ThresholdID,
            @DeviceID,
            'Humidity',
            @Humidity,
            CASE WHEN @Humidity > at.MaxValue THEN at.MaxValue ELSE at.MinValue END,
            CASE WHEN @Humidity > at.MaxValue THEN 'HIGH' ELSE 'LOW' END
        FROM AlertThresholds at
        WHERE at.DeviceID = @DeviceID 
            AND at.MetricType = 'Humidity'
            AND at.IsActive = 1
            AND ((@Humidity > at.MaxValue + at.Hysteresis) 
                 OR (@Humidity < at.MinValue - at.Hysteresis))
            AND NOT EXISTS (
                SELECT 1 FROM ActiveAlerts aa 
                WHERE aa.DeviceID = @DeviceID 
                    AND aa.MetricType = 'Humidity'
                    AND aa.Resolved = 0
            );
    END
END;
GO

-- Create view for real-time dashboard
CREATE VIEW vw_DashboardSummary AS
SELECT 
    d.DeviceID,
    d.DeviceName,
    d.Location,
    d.IsOnline,
    d.LastSeen,
    sd.Temperature,
    sd.Humidity,
    sd.Timestamp as LastReading,
    COUNT(aa.AlertID) as ActiveAlertCount
FROM Devices d
LEFT JOIN (
    SELECT DeviceID, Temperature, Humidity, Timestamp,
           ROW_NUMBER() OVER (PARTITION BY DeviceID ORDER BY Timestamp DESC) as rn
    FROM SensorData
) sd ON d.DeviceID = sd.DeviceID AND sd.rn = 1
LEFT JOIN ActiveAlerts aa ON d.DeviceID = aa.DeviceID AND aa.Resolved = 0
GROUP BY d.DeviceID, d.DeviceName, d.Location, d.IsOnline, d.LastSeen, 
         sd.Temperature, sd.Humidity, sd.Timestamp;
GO

-- Insert default admin user (password: admin123)
INSERT INTO Users (Username, PasswordHash, Email, FullName, Role)
VALUES ('admin', '$2y$12$LQv3c1yqBWVHxkd0LHAkCOYz6TtxMQJqhN8/X4.G.2fFZqJLqGxW.', 'admin@iot-srs.com', 'System Administrator', 'Admin');
GO

PRINT 'Database schema created successfully!';
GO
