<?php
/**
 * Data Controller
 * 
 * Handles sensor data ingestion, batch sync, and history retrieval
 */

class DataController {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Ingest single sensor reading
     * POST /api/data/ingest
     */
    public function ingest(): void {
        $device = AuthMiddleware::handleDeviceAuth();
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$data) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid JSON data']);
            return;
        }
        
        // Validate required fields
        if (!isset($data['temperature']) || !isset($data['humidity'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Temperature and humidity are required']);
            return;
        }
        
        try {
            // Insert sensor reading
            $readingId = $this->db->insert('sensor_readings', [
                'device_id' => $device['id'],
                'temperature' => (float)$data['temperature'],
                'humidity' => (float)$data['humidity'],
                'battery_level' => isset($data['battery']) ? (float)$data['battery'] : null,
                'signal_strength' => isset($data['rssi']) ? (int)$data['rssi'] : null,
                'recorded_at' => isset($data['timestamp']) ? date('Y-m-d H:i:s', $data['timestamp']) : date('Y-m-d H:i:s')
            ]);
            
            // Update device last seen
            $this->db->update('devices', [
                'last_seen' => date('Y-m-d H:i:s')
            ], 'id = ?', [$device['id']]);
            
            // Check for alerts
            $this->checkAlerts($device['id'], (float)$data['temperature'], (float)$data['humidity']);
            
            Logger::debug('Sensor Data Ingested', [
                'device_id' => $device['id'],
                'reading_id' => $readingId,
                'temperature' => $data['temperature'],
                'humidity' => $data['humidity']
            ]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Data received successfully',
                'reading_id' => $readingId
            ]);
            
        } catch (Exception $e) {
            Logger::error('Data Ingestion Failed', [
                'device_id' => $device['id'],
                'error' => $e->getMessage()
            ]);
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }
    
    /**
     * Batch sync for offline devices
     * POST /api/data/batch
     */
    public function batchSync(): void {
        $device = AuthMiddleware::handleDeviceAuth();
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$data || !isset($data['readings']) || !is_array($data['readings'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid batch data']);
            return;
        }
        
        $readings = $data['readings'];
        
        // Limit batch size
        if (count($readings) > MAX_OFFLINE_BUFFER) {
            http_response_code(400);
            echo json_encode([
                'success' => false, 
                'message' => "Batch size exceeds maximum of " . MAX_OFFLINE_BUFFER
            ]);
            return;
        }
        
        try {
            $this->db->beginTransaction();
            
            $insertedCount = 0;
            $skippedCount = 0;
            
            foreach ($readings as $reading) {
                if (!isset($reading['temperature']) || !isset($reading['humidity'])) {
                    $skippedCount++;
                    continue;
                }
                
                // Check for duplicate
                $timestamp = isset($reading['timestamp']) 
                    ? date('Y-m-d H:i:s', $reading['timestamp']) 
                    : date('Y-m-d H:i:s');
                
                $exists = $this->db->fetchOne(
                    "SELECT id FROM sensor_readings 
                     WHERE device_id = ? AND recorded_at = ?",
                    [$device['id'], $timestamp]
                );
                
                if ($exists) {
                    $skippedCount++;
                    continue;
                }
                
                $this->db->insert('sensor_readings', [
                    'device_id' => $device['id'],
                    'temperature' => (float)$reading['temperature'],
                    'humidity' => (float)$reading['humidity'],
                    'battery_level' => isset($reading['battery']) ? (float)$reading['battery'] : null,
                    'signal_strength' => isset($reading['rssi']) ? (int)$reading['rssi'] : null,
                    'recorded_at' => $timestamp
                ]);
                
                $insertedCount++;
            }
            
            // Update device last seen
            $this->db->update('devices', [
                'last_seen' => date('Y-m-d H:i:s')
            ], 'id = ?', [$device['id']]);
            
            $this->db->commit();
            
            Logger::info('Batch Sync Completed', [
                'device_id' => $device['id'],
                'inserted' => $insertedCount,
                'skipped' => $skippedCount
            ]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Batch sync completed',
                'inserted' => $insertedCount,
                'skipped' => $skippedCount
            ]);
            
        } catch (Exception $e) {
            $this->db->rollback();
            Logger::error('Batch Sync Failed', [
                'device_id' => $device['id'],
                'error' => $e->getMessage()
            ]);
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }
    
    /**
     * Get historical data
     * GET /api/data/history
     */
    public function history(): void {
        AuthMiddleware::handle();
        
        $deviceId = $_GET['device_id'] ?? null;
        $startDate = $_GET['start_date'] ?? date('Y-m-d H:i:s', strtotime('-24 hours'));
        $endDate = $_GET['end_date'] ?? date('Y-m-d H:i:s');
        $interval = $_GET['interval'] ?? 'hour'; // minute, hour, day
        
        if (!$deviceId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Device ID required']);
            return;
        }
        
        $user = Auth::getCurrentUser();
        $isAdmin = Auth::isAdmin();
        
        // Check ownership
        if (!$isAdmin) {
            $device = $this->db->fetchOne(
                "SELECT id FROM devices WHERE id = ? AND user_id = ?",
                [$deviceId, $user['id']]
            );
            
            if (!$device) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Access denied']);
                return;
            }
        }
        
        // Build aggregation query based on interval
        switch ($interval) {
            case 'minute':
                $groupBy = "FORMAT(recorded_at, 'yyyy-MM-dd HH:mm')";
                break;
            case 'hour':
                $groupBy = "FORMAT(recorded_at, 'yyyy-MM-dd HH:00')";
                break;
            case 'day':
                $groupBy = "CAST(recorded_at AS DATE)";
                break;
            default:
                $groupBy = "FORMAT(recorded_at, 'yyyy-MM-dd HH:00')";
        }
        
        $sql = "SELECT 
                    {$groupBy} as time_bucket,
                    AVG(temperature) as avg_temperature,
                    MIN(temperature) as min_temperature,
                    MAX(temperature) as max_temperature,
                    AVG(humidity) as avg_humidity,
                    MIN(humidity) as min_humidity,
                    MAX(humidity) as max_humidity,
                    COUNT(*) as reading_count
                FROM sensor_readings
                WHERE device_id = ? AND recorded_at BETWEEN ? AND ?
                GROUP BY {$groupBy}
                ORDER BY time_bucket";
        
        $data = $this->db->fetchAll($sql, [$deviceId, $startDate, $endDate]);
        
        echo json_encode([
            'success' => true,
            'data' => $data,
            'metadata' => [
                'device_id' => $deviceId,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'interval' => $interval
            ]
        ]);
    }
    
    /**
     * Get statistics
     * GET /api/data/stats
     */
    public function statistics(): void {
        AuthMiddleware::handle();
        
        $deviceId = $_GET['device_id'] ?? null;
        $period = $_GET['period'] ?? '24h'; // 1h, 24h, 7d, 30d
        
        // Calculate date range
        switch ($period) {
            case '1h':
                $startDate = date('Y-m-d H:i:s', strtotime('-1 hour'));
                break;
            case '24h':
                $startDate = date('Y-m-d H:i:s', strtotime('-24 hours'));
                break;
            case '7d':
                $startDate = date('Y-m-d H:i:s', strtotime('-7 days'));
                break;
            case '30d':
                $startDate = date('Y-m-d H:i:s', strtotime('-30 days'));
                break;
            default:
                $startDate = date('Y-m-d H:i:s', strtotime('-24 hours'));
        }
        
        $user = Auth::getCurrentUser();
        $isAdmin = Auth::isAdmin();
        
        if ($deviceId) {
            // Single device stats
            if (!$isAdmin) {
                $device = $this->db->fetchOne(
                    "SELECT id FROM devices WHERE id = ? AND user_id = ?",
                    [$deviceId, $user['id']]
                );
                
                if (!$device) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'message' => 'Access denied']);
                    return;
                }
            }
            
            $stats = $this->db->fetchOne(
                "SELECT 
                    COUNT(*) as total_readings,
                    AVG(temperature) as avg_temperature,
                    MIN(temperature) as min_temperature,
                    MAX(temperature) as max_temperature,
                    AVG(humidity) as avg_humidity,
                    MIN(humidity) as min_humidity,
                    MAX(humidity) as max_humidity,
                    AVG(battery_level) as avg_battery
                 FROM sensor_readings
                 WHERE device_id = ? AND recorded_at >= ?",
                [$deviceId, $startDate]
            );
            
            echo json_encode([
                'success' => true,
                'data' => $stats,
                'metadata' => [
                    'device_id' => $deviceId,
                    'period' => $period
                ]
            ]);
        } else {
            // All devices summary
            $whereClause = $isAdmin ? "" : "WHERE d.user_id = ?";
            $params = $isAdmin ? [$startDate] : [$user['id'], $startDate];
            
            $stats = $this->db->fetchOne(
                "SELECT 
                    COUNT(DISTINCT d.id) as total_devices,
                    COUNT(r.id) as total_readings,
                    AVG(r.temperature) as avg_temperature,
                    AVG(r.humidity) as avg_humidity
                 FROM devices d
                 LEFT JOIN sensor_readings r ON d.id = r.device_id AND r.recorded_at >= ?
                 {$whereClause}",
                $params
            );
            
            echo json_encode([
                'success' => true,
                'data' => $stats,
                'metadata' => [
                    'period' => $period
                ]
            ]);
        }
    }
    
    /**
     * Check and trigger alerts
     */
    private function checkAlerts(int $deviceId, float $temperature, float $humidity): void {
        // Get active alert rules for this device
        $alerts = $this->db->fetchAll(
            "SELECT * FROM alerts WHERE device_id = ? AND status = 'active'",
            [$deviceId]
        );
        
        foreach ($alerts as $alert) {
            $triggered = false;
            $currentValue = null;
            
            switch ($alert['alert_type']) {
                case 'temperature_high':
                    $currentValue = $temperature;
                    $triggered = $temperature > ($alert['threshold_value'] + $alert['hysteresis']);
                    break;
                case 'temperature_low':
                    $currentValue = $temperature;
                    $triggered = $temperature < ($alert['threshold_value'] - $alert['hysteresis']);
                    break;
                case 'humidity_high':
                    $currentValue = $humidity;
                    $triggered = $humidity > ($alert['threshold_value'] + $alert['hysteresis']);
                    break;
                case 'humidity_low':
                    $currentValue = $humidity;
                    $triggered = $humidity < ($alert['threshold_value'] - $alert['hysteresis']);
                    break;
            }
            
            if ($triggered) {
                // Check if already triggered recently (prevent spam)
                $recentAlert = $this->db->fetchOne(
                    "SELECT TOP 1 id FROM alert_history 
                     WHERE alert_id = ? AND triggered_at > DATEADD(minute, -10, GETDATE())",
                    [$alert['id']]
                );
                
                if (!$recentAlert) {
                    // Create alert instance
                    $this->db->insert('alert_history', [
                        'alert_id' => $alert['id'],
                        'device_id' => $deviceId,
                        'triggered_at' => date('Y-m-d H:i:s'),
                        'value' => $currentValue,
                        'threshold' => $alert['threshold_value'],
                        'status' => 'active'
                    ]);
                    
                    // Update alert last triggered
                    $this->db->update('alerts', [
                        'last_triggered' => date('Y-m-d H:i:s'),
                        'trigger_count' => ($alert['trigger_count'] ?? 0) + 1
                    ], 'id = ?', [$alert['id']]);
                    
                    Logger::warning('Alert Triggered', [
                        'alert_id' => $alert['id'],
                        'alert_type' => $alert['alert_type'],
                        'device_id' => $deviceId,
                        'value' => $currentValue,
                        'threshold' => $alert['threshold_value']
                    ]);
                }
            }
        }
    }
}
