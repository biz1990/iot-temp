<?php
/**
 * API Endpoint: Alert Management
 * POST /api/alerts.php - Acknowledge/Resolve alerts
 * GET /api/alerts.php - Get alert history
 */

session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/Auth.php';

try {
    if (!Auth::isLoggedIn()) {
        throw new Exception('Unauthorized', 401);
    }

    $db = Database::getInstance()->getConnection();
    $userId = Auth::getUserId();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Acknowledge or Resolve alert (FR-018, FR-019)
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);

        if (empty($data['alert_id'])) {
            throw new Exception('Alert ID required', 400);
        }

        $alertId = $data['alert_id'];
        $action = $data['action'] ?? 'acknowledge'; // acknowledge, resolve

        if ($action === 'acknowledge') {
            $stmt = $db->prepare("
                UPDATE ActiveAlerts 
                SET Acknowledged = 1, AcknowledgedBy = ?, AcknowledgedAt = GETDATE()
                WHERE AlertID = ? AND Acknowledged = 0
            ");
            $stmt->execute([$userId, $alertId]);
            
            $message = 'Alert acknowledged';
            
        } elseif ($action === 'resolve') {
            // Move to history first
            $historyStmt = $db->prepare("
                INSERT INTO AlertHistory (AlertID, DeviceID, MetricType, CurrentValue, ThresholdValue, AlertType, TriggeredAt, AcknowledgedAt, ResolvedAt)
                SELECT AlertID, DeviceID, MetricType, CurrentValue, ThresholdValue, AlertType, TriggeredAt, AcknowledgedAt, GETDATE()
                FROM ActiveAlerts
                WHERE AlertID = ?
            ");
            $historyStmt->execute([$alertId]);
            
            // Delete from active
            $deleteStmt = $db->prepare("DELETE FROM ActiveAlerts WHERE AlertID = ?");
            $deleteStmt->execute([$alertId]);
            
            $message = 'Alert resolved and archived';
            
        } else {
            throw new Exception('Invalid action', 400);
        }

        // Log audit
        $auditStmt = $db->prepare("
            INSERT INTO AuditLog (UserID, Action, EntityType, EntityID)
            VALUES (?, ?, 'Alert', ?)
        ");
        $auditStmt->execute([$userId, strtoupper($action), $alertId]);

        echo json_encode([
            'success' => true,
            'message' => $message
        ]);

    } elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Get alert history (FR-019)
        $limit = intval($_GET['limit'] ?? 100);
        $deviceId = $_GET['device_id'] ?? null;
        
        $whereClause = '';
        $params = [];
        
        if ($deviceId) {
            $whereClause = 'WHERE ah.DeviceID = ?';
            $params[] = $deviceId;
        }
        
        $stmt = $db->prepare("
            SELECT TOP (?)
                ah.HistoryID,
                ah.AlertID,
                ah.DeviceID,
                d.DeviceName,
                ah.MetricType,
                ah.CurrentValue,
                ah.ThresholdValue,
                ah.AlertType,
                ah.TriggeredAt,
                ah.AcknowledgedAt,
                ah.ResolvedAt,
                ah.DurationSeconds
            FROM AlertHistory ah
            JOIN Devices d ON ah.DeviceID = d.DeviceID
            $whereClause
            ORDER BY ah.TriggeredAt DESC
        ");
        
        if ($deviceId) {
            $params[] = $limit;
            $stmt->execute($params);
        } else {
            $stmt->execute([$limit]);
        }
        
        $history = $stmt->fetchAll();

        echo json_encode([
            'success' => true,
            'data' => $history
        ]);
    }

} catch (Exception $e) {
    http_response_code($e->getCode() ?: 500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
