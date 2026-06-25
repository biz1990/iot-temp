<?php
/**
 * API Endpoint: Receive sensor data from IoT devices
 * POST /api/ingest.php
 * 
 * Request Body (JSON):
 * {
 *   "device_token": "abc123...",
 *   "timestamp": "2026-06-25T10:30:00Z",
 *   "temperature": 25.5,
 *   "humidity": 60.2
 * }
 * 
 * Batch Request (for offline sync):
 * {
 *   "device_token": "abc123...",
 *   "batch": [
 *     {"timestamp": "...", "temperature": 25.5, "humidity": 60.2},
 *     {"timestamp": "...", "temperature": 26.1, "humidity": 59.8}
 *   ]
 * }
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/Auth.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Method not allowed', 405);
    }

    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON', 400);
    }

    // Validate device token
    if (empty($data['device_token'])) {
        throw new Exception('Device token required', 400);
    }

    $db = Database::getInstance()->getConnection();
    
    // Verify device exists
    $stmt = $db->prepare("SELECT DeviceID, DeviceName FROM Devices WHERE DeviceToken = ? AND IsActive = 1");
    $stmt->execute([$data['device_token']]);
    $device = $stmt->fetch();

    if (!$device) {
        // Auto-register new device (optional - can be disabled for security)
        $deviceToken = $data['device_token'];
        $deviceName = 'Device_' . substr($deviceToken, 0, 8);
        
        $insertStmt = $db->prepare("INSERT INTO Devices (DeviceToken, DeviceName, IsOnline) VALUES (?, ?, 1)");
        $insertStmt->execute([$deviceToken, $deviceName]);
        
        $deviceId = $db->lastInsertId();
    } else {
        $deviceId = $device['DeviceID'];
        
        // Update device online status
        $updateStmt = $db->prepare("UPDATE Devices SET IsOnline = 1, LastSeen = GETDATE() WHERE DeviceID = ?");
        $updateStmt->execute([$deviceId]);
    }

    // Handle batch data (offline sync)
    if (isset($data['batch']) && is_array($data['batch'])) {
        $insertStmt = $db->prepare("
            INSERT INTO SensorData (DeviceID, Temperature, Humidity, Timestamp, IsValid)
            VALUES (?, ?, ?, ?, 1)
        ");
        
        $checkAlertProc = $db->prepare("EXEC sp_CheckAlerts ?, ?, ?");
        
        $db->beginTransaction();
        
        foreach ($data['batch'] as $reading) {
            if (!isset($reading['timestamp'])) {
                continue;
            }
            
            $temp = isset($reading['temperature']) ? floatval($reading['temperature']) : null;
            $humid = isset($reading['humidity']) ? floatval($reading['humidity']) : null;
            $ts = $reading['timestamp'];
            
            $insertStmt->execute([$deviceId, $temp, $humid, $ts]);
            
            // Check alerts for latest reading
            if ($temp !== null || $humid !== null) {
                $checkAlertProc->execute([$deviceId, $temp, $humid]);
            }
        }
        
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Batch data received',
            'records_processed' => count($data['batch']),
            'device_id' => $deviceId
        ]);
        
    } else {
        // Single reading
        if (!isset($data['timestamp'])) {
            throw new Exception('Timestamp required', 400);
        }

        $temperature = isset($data['temperature']) ? floatval($data['temperature']) : null;
        $humidity = isset($data['humidity']) ? floatval($data['humidity']) : null;
        $timestamp = $data['timestamp'];

        // Validate sensor ranges (DHT22 specs)
        if ($temperature !== null && ($temperature < -40 || $temperature > 80)) {
            throw new Exception('Temperature out of valid range (-40 to 80)', 400);
        }
        
        if ($humidity !== null && ($humidity < 0 || $humidity > 100)) {
            throw new Exception('Humidity out of valid range (0 to 100)', 400);
        }

        $stmt = $db->prepare("
            INSERT INTO SensorData (DeviceID, Temperature, Humidity, Timestamp, IsValid)
            VALUES (?, ?, ?, ?, 1)
        ");
        
        $stmt->execute([$deviceId, $temperature, $humidity, $timestamp]);

        // Check for alerts
        $alertProc = $db->prepare("EXEC sp_CheckAlerts ?, ?, ?");
        $alertProc->execute([$deviceId, $temperature, $humidity]);

        echo json_encode([
            'success' => true,
            'message' => 'Data received successfully',
            'data_id' => $db->lastInsertId(),
            'device_id' => $deviceId
        ]);
    }

} catch (Exception $e) {
    http_response_code($e->getCode() ?: 500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
