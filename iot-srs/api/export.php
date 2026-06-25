<?php
/**
 * API Endpoint: Export Data
 * GET /api/export.php?type=csv|excel|json&device_id=X&from=YYYY-MM-DD&to=YYYY-MM-DD
 */

session_start();
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
    
    $type = $_GET['type'] ?? 'csv';
    $deviceId = $_GET['device_id'] ?? null;
    $fromDate = $_GET['from'] ?? date('Y-m-d', strtotime('-30 days'));
    $toDate = $_GET['to'] ?? date('Y-m-d');
    
    if (!$deviceId) {
        throw new Exception('Device ID required', 400);
    }

    // Fetch data
    $stmt = $db->prepare("
        SELECT 
            sd.Timestamp,
            d.DeviceName,
            sd.Temperature,
            sd.Humidity,
            sd.IsValid
        FROM SensorData sd
        JOIN Devices d ON sd.DeviceID = d.DeviceID
        WHERE sd.DeviceID = ?
            AND sd.Timestamp >= ?
            AND sd.Timestamp <= ?
        ORDER BY sd.Timestamp DESC
    ");
    $stmt->execute([$deviceId, $fromDate, $toDate]);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($type === 'json') {
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="sensor_data_' . $deviceId . '_' . date('Ymd') . '.json"');
        echo json_encode(['data' => $data], JSON_PRETTY_PRINT);
        
    } elseif ($type === 'excel') {
        // Simple Excel XML format (SpreadsheetML)
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment; filename="sensor_data_' . $deviceId . '_' . date('Ymd') . '.xls"');
        
        echo '<?xml version="1.0" encoding="UTF-8"?>';
        echo '<?mso-application progid="Excel.Sheet"?>';
        echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheetml">';
        echo '<Worksheet ss:Name="Sensor Data">';
        echo '<Table>';
        echo '<Row>';
        echo '<Cell><Data ss:Type="String">Timestamp</Data></Cell>';
        echo '<Cell><Data ss:Type="String">Device Name</Data></Cell>';
        echo '<Cell><Data ss:Type="String">Temperature (°C)</Data></Cell>';
        echo '<Cell><Data ss:Type="String">Humidity (%)</Data></Cell>';
        echo '<Cell><Data ss:Type="String">Valid</Data></Cell>';
        echo '</Row>';
        
        foreach ($data as $row) {
            echo '<Row>';
            echo '<Cell><Data ss:Type="String">' . htmlspecialchars($row['Timestamp']) . '</Data></Cell>';
            echo '<Cell><Data ss:Type="String">' . htmlspecialchars($row['DeviceName']) . '</Data></Cell>';
            echo '<Cell><Data ss:Type="Number">' . ($row['Temperature'] ?? '') . '</Data></Cell>';
            echo '<Cell><Data ss:Type="Number">' . ($row['Humidity'] ?? '') . '</Data></Cell>';
            echo '<Cell><Data ss:Type="String">' . ($row['IsValid'] ? 'Yes' : 'No') . '</Data></Cell>';
            echo '</Row>';
        }
        
        echo '</Table></Worksheet></Workbook>';
        
    } else {
        // CSV (default)
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="sensor_data_' . $deviceId . '_' . date('Ymd') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // Add BOM for UTF-8
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Header row
        fputcsv($output, ['Timestamp', 'Device Name', 'Temperature (°C)', 'Humidity (%)', 'Valid']);
        
        // Data rows
        foreach ($data as $row) {
            fputcsv($output, [
                $row['Timestamp'],
                $row['DeviceName'],
                $row['Temperature'] ?? '',
                $row['Humidity'] ?? '',
                $row['IsValid'] ? 'Yes' : 'No'
            ]);
        }
        
        fclose($output);
    }

} catch (Exception $e) {
    http_response_code($e->getCode() ?: 500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
