<?php
/**
 * Device Controller
 * 
 * Handles device registration, management, and provisioning
 */

class DeviceController {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Register a new device
     * POST /api/devices/register
     */
    public function register(): void {
        AuthMiddleware::handle();
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$data) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid JSON data']);
            return;
        }
        
        // Validate required fields
        $requiredFields = ['device_name', 'device_type'];
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => "Field '{$field}' is required"]);
                return;
            }
        }
        
        try {
            $this->db->beginTransaction();
            
            // Generate unique device token
            $deviceToken = Auth::generateDeviceToken();
            
            // Check for duplicate device name for this user
            $user = Auth::getCurrentUser();
            $existing = $this->db->fetchOne(
                "SELECT id FROM devices WHERE device_name = ? AND user_id = ?",
                [$data['device_name'], $user['id']]
            );
            
            if ($existing) {
                throw new Exception('Device name already exists for your account');
            }
            
            // Insert device
            $deviceId = $this->db->insert('devices', [
                'user_id' => $user['id'],
                'device_token' => $deviceToken,
                'device_name' => $data['device_name'],
                'device_type' => $data['device_type'] ?? 'arduino_r4_wifi',
                'description' => $data['description'] ?? null,
                'location' => $data['location'] ?? null,
                'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            $this->db->commit();
            
            Logger::audit('Device Registered', [
                'device_id' => $deviceId,
                'device_name' => $data['device_name'],
                'device_token' => $deviceToken
            ]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Device registered successfully',
                'device' => [
                    'id' => $deviceId,
                    'device_name' => $data['device_name'],
                    'device_token' => $deviceToken,
                    'device_type' => $data['device_type']
                ]
            ]);
            
        } catch (Exception $e) {
            $this->db->rollback();
            Logger::error('Device Registration Failed', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }
    
    /**
     * List all devices for current user
     * GET /api/devices
     */
    public function list(): void {
        AuthMiddleware::handle();
        
        $user = Auth::getCurrentUser();
        $isAdmin = Auth::isAdmin();
        
        // Build query based on role
        if ($isAdmin) {
            $sql = "SELECT d.*, u.username as owner_username 
                    FROM devices d 
                    JOIN users u ON d.user_id = u.id 
                    ORDER BY d.created_at DESC";
            $params = [];
        } else {
            $sql = "SELECT d.* FROM devices d WHERE d.user_id = ? ORDER BY d.created_at DESC";
            $params = [$user['id']];
        }
        
        // Pagination
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $pageSize = isset($_GET['limit']) ? (int)$_GET['limit'] : DEFAULT_PAGE_SIZE;
        $pageSize = min($pageSize, MAX_PAGE_SIZE);
        $offset = ($page - 1) * $pageSize;
        
        $countSql = $isAdmin 
            ? "SELECT COUNT(*) as total FROM devices"
            : "SELECT COUNT(*) as total FROM devices WHERE user_id = ?";
        
        $total = $this->db->fetchOne($countSql, $isAdmin ? [] : [$user['id']])['total'];
        
        $sql .= " OFFSET {$offset} ROWS FETCH NEXT {$pageSize} ROWS ONLY";
        
        $devices = $this->db->fetchAll($sql, $params);
        
        // Get latest sensor reading for each device
        foreach ($devices as &$device) {
            $latestReading = $this->db->fetchOne(
                "SELECT TOP 1 temperature, humidity, battery_level, recorded_at 
                 FROM sensor_readings 
                 WHERE device_id = ? 
                 ORDER BY recorded_at DESC",
                [$device['id']]
            );
            
            $device['latest_reading'] = $latestReading;
            
            // Calculate online status (offline if no reading in last 10 minutes)
            $device['is_online'] = $latestReading && 
                (strtotime($latestReading['recorded_at']) > (time() - 600));
        }
        
        echo json_encode([
            'success' => true,
            'data' => $devices,
            'pagination' => [
                'current_page' => $page,
                'page_size' => $pageSize,
                'total_items' => $total,
                'total_pages' => ceil($total / $pageSize)
            ]
        ]);
    }
    
    /**
     * Get single device details
     * GET /api/devices/{id}
     */
    public function get(): void {
        AuthMiddleware::handle();
        
        $deviceId = $_GET['id'] ?? null;
        
        if (!$deviceId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Device ID required']);
            return;
        }
        
        $user = Auth::getCurrentUser();
        $isAdmin = Auth::isAdmin();
        
        // Build query based on role
        if ($isAdmin) {
            $sql = "SELECT d.*, u.username as owner_username 
                    FROM devices d 
                    JOIN users u ON d.user_id = u.id 
                    WHERE d.id = ?";
        } else {
            $sql = "SELECT d.* FROM devices d WHERE d.id = ? AND d.user_id = ?";
            $params = [$deviceId, $user['id']];
        }
        
        $device = $this->db->fetchOne($sql, $isAdmin ? [$deviceId] : $params);
        
        if (!$device) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Device not found']);
            return;
        }
        
        // Get statistics
        $stats = $this->db->fetchOne(
            "SELECT 
                COUNT(*) as total_readings,
                MIN(recorded_at) as first_reading,
                MAX(recorded_at) as last_reading,
                AVG(temperature) as avg_temperature,
                AVG(humidity) as avg_humidity
             FROM sensor_readings 
             WHERE device_id = ?",
            [$deviceId]
        );
        
        $device['statistics'] = $stats;
        
        // Get active alerts
        $alerts = $this->db->fetchAll(
            "SELECT * FROM alerts 
             WHERE device_id = ? AND status = 'active' 
             ORDER BY triggered_at DESC",
            [$deviceId]
        );
        
        $device['active_alerts'] = $alerts;
        
        echo json_encode([
            'success' => true,
            'data' => $device
        ]);
    }
    
    /**
     * Update device
     * PUT /api/devices/{id}
     */
    public function update(): void {
        AuthMiddleware::handle();
        
        $deviceId = $_GET['id'] ?? null;
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$deviceId || !$data) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid request']);
            return;
        }
        
        $user = Auth::getCurrentUser();
        $isAdmin = Auth::isAdmin();
        
        // Check ownership
        $device = $this->db->fetchOne(
            $isAdmin ? "SELECT * FROM devices WHERE id = ?" : "SELECT * FROM devices WHERE id = ? AND user_id = ?",
            $isAdmin ? [$deviceId] : [$deviceId, $user['id']]
        );
        
        if (!$device) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Device not found or access denied']);
            return;
        }
        
        // Prepare update data
        $updateData = [];
        $allowedFields = ['device_name', 'description', 'location', 'is_active'];
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $updateData[$field] = $data[$field];
            }
        }
        
        if (empty($updateData)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'No valid fields to update']);
            return;
        }
        
        $this->db->update('devices', $updateData, 'id = ?', [$deviceId]);
        
        Logger::audit('Device Updated', [
            'device_id' => $deviceId,
            'fields' => array_keys($updateData)
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Device updated successfully'
        ]);
    }
    
    /**
     * Delete device
     * DELETE /api/devices/{id}
     */
    public function delete(): void {
        AuthMiddleware::handle();
        
        $deviceId = $_GET['id'] ?? null;
        
        if (!$deviceId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Device ID required']);
            return;
        }
        
        $user = Auth::getCurrentUser();
        $isAdmin = Auth::isAdmin();
        
        // Check ownership
        $device = $this->db->fetchOne(
            $isAdmin ? "SELECT * FROM devices WHERE id = ?" : "SELECT * FROM devices WHERE id = ? AND user_id = ?",
            $isAdmin ? [$deviceId] : [$deviceId, $user['id']]
        );
        
        if (!$device) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Device not found or access denied']);
            return;
        }
        
        try {
            $this->db->beginTransaction();
            
            // Delete associated alerts
            $this->db->delete('alerts', 'device_id = ?', [$deviceId]);
            
            // Note: Sensor readings are kept for historical purposes
            // Soft delete by marking as inactive instead of hard delete
            $this->db->update('devices', [
                'is_active' => 0,
                'deleted_at' => date('Y-m-d H:i:s')
            ], 'id = ?', [$deviceId]);
            
            $this->db->commit();
            
            Logger::audit('Device Deleted', ['device_id' => $deviceId]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Device deleted successfully'
            ]);
            
        } catch (Exception $e) {
            $this->db->rollback();
            Logger::error('Device Deletion Failed', [
                'device_id' => $deviceId,
                'error' => $e->getMessage()
            ]);
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }
    
    /**
     * Provision device with initial configuration
     * POST /api/devices/{id}/provision
     */
    public function provision(): void {
        AuthMiddleware::handle();
        
        $deviceId = $_GET['id'] ?? null;
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$deviceId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Device ID required']);
            return;
        }
        
        $user = Auth::getCurrentUser();
        
        // Check ownership
        $device = $this->db->fetchOne(
            "SELECT * FROM devices WHERE id = ? AND user_id = ?",
            [$deviceId, $user['id']]
        );
        
        if (!$device) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Device not found']);
            return;
        }
        
        // Create default alert rules if provided
        if (isset($data['alert_rules']) && is_array($data['alert_rules'])) {
            foreach ($data['alert_rules'] as $rule) {
                $this->db->insert('alerts', [
                    'device_id' => $deviceId,
                    'alert_type' => $rule['type'] ?? 'temperature_high',
                    'threshold_value' => $rule['threshold'] ?? 0,
                    'hysteresis' => $rule['hysteresis'] ?? HYSTERESIS_DEFAULT,
                    'status' => 'active',
                    'created_at' => date('Y-m-d H:i:s')
                ]);
            }
        }
        
        Logger::audit('Device Provisioned', ['device_id' => $deviceId]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Device provisioned successfully',
            'configuration' => [
                'device_token' => $device['device_token'],
                'server_url' => APP_URL . '/api/data/ingest',
                'heartbeat_interval' => HEARTBEAT_INTERVAL,
                'max_buffer_size' => MAX_OFFLINE_BUFFER
            ]
        ]);
    }
}
