<?php
/**
 * API Endpoint: Dashboard Data
 * GET /api/dashboard.php
 * Returns real-time dashboard summary and charts data
 */

session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
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
    $action = $_GET['action'] ?? 'summary';

    if ($action === 'summary') {
        // Get dashboard summary (FR-008)
        $stmt = $db->query("SELECT * FROM vw_DashboardSummary ORDER BY DeviceName");
        $devices = $stmt->fetchAll();
        
        // Get statistics
        $statsStmt = $db->query("
            SELECT 
                COUNT(DISTINCT d.DeviceID) as TotalDevices,
                SUM(CASE WHEN d.IsOnline = 1 THEN 1 ELSE 0 END) as OnlineDevices,
                (SELECT COUNT(*) FROM ActiveAlerts WHERE Resolved = 0) as ActiveAlerts,
                (SELECT COUNT(*) FROM Users WHERE IsActive = 1) as TotalUsers
            FROM Devices d
        ");
        $stats = $statsStmt->fetch();

        echo json_encode([
            'success' => true,
            'data' => [
                'devices' => $devices,
                'statistics' => $stats
            ]
        ]);

    } elseif ($action === 'chart') {
        // Get chart data for specific device (FR-009)
        $deviceId = $_GET['device_id'] ?? null;
        $hours = intval($_GET['hours'] ?? 24);
        
        if (!$deviceId) {
            throw new Exception('Device ID required', 400);
        }

        $stmt = $db->prepare("
            SELECT TOP 100
                Temperature,
                Humidity,
                Timestamp
            FROM SensorData
            WHERE DeviceID = ?
                AND Timestamp >= DATEADD(HOUR, -?, GETDATE())
            ORDER BY Timestamp DESC
        ");
        $stmt->execute([$deviceId, $hours]);
        $readings = $stmt->fetchAll();

        echo json_encode([
            'success' => true,
            'data' => [
                'device_id' => $deviceId,
                'period_hours' => $hours,
                'readings' => $readings
            ]
        ]);

    } elseif ($action === 'alerts') {
        // Get active alerts (FR-017)
        $stmt = $db->query("
            SELECT 
                aa.AlertID,
                aa.DeviceID,
                d.DeviceName,
                aa.MetricType,
                aa.CurrentValue,
                aa.ThresholdValue,
                aa.AlertType,
                aa.TriggeredAt,
                aa.Acknowledged,
                aa.Resolved
            FROM ActiveAlerts aa
            JOIN Devices d ON aa.DeviceID = d.DeviceID
            WHERE aa.Resolved = 0
            ORDER BY aa.TriggeredAt DESC
        ");
        $alerts = $stmt->fetchAll();

        echo json_encode([
            'success' => true,
            'data' => $alerts
        ]);

    } elseif ($action === 'kanban') {
        // Get Kanban view data (FR-010)
        $status = $_GET['status'] ?? 'all'; // all, online, offline, alerts
        
        $whereClause = '';
        if ($status === 'online') {
            $whereClause = 'WHERE d.IsOnline = 1';
        } elseif ($status === 'offline') {
            $whereClause = 'WHERE d.IsOnline = 0';
        } elseif ($status === 'alerts') {
            $whereClause = 'INNER JOIN ActiveAlerts aa ON d.DeviceID = aa.DeviceID AND aa.Resolved = 0';
        }

        $stmt = $db->query("
            SELECT 
                d.DeviceID,
                d.DeviceName,
                d.Location,
                d.IsOnline,
                d.LastSeen,
                sd.Temperature,
                sd.Humidity,
                sd.Timestamp as LastReading,
                COUNT(aa.AlertID) as AlertCount
            FROM Devices d
            LEFT JOIN (
                SELECT DeviceID, Temperature, Humidity, Timestamp,
                       ROW_NUMBER() OVER (PARTITION BY DeviceID ORDER BY Timestamp DESC) as rn
                FROM SensorData
            ) sd ON d.DeviceID = sd.DeviceID AND sd.rn = 1
            $whereClause
            LEFT JOIN ActiveAlerts aa ON d.DeviceID = aa.DeviceID AND aa.Resolved = 0
            GROUP BY d.DeviceID, d.DeviceName, d.Location, d.IsOnline, d.LastSeen,
                     sd.Temperature, sd.Humidity, sd.Timestamp
            ORDER BY d.DeviceName
        ");
        $kanbanData = $stmt->fetchAll();

        echo json_encode([
            'success' => true,
            'data' => $kanbanData
        ]);

    } else {
        throw new Exception('Invalid action', 400);
    }

} catch (Exception $e) {
    http_response_code($e->getCode() ?: 500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
