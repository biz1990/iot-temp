<?php
/**
 * Cron Job: Data Cleanup
 * Run daily via SQL Server Agent or system cron
 * php cron/cleanup.php
 */

require_once __DIR__ . '/../includes/Database.php';

echo "Starting data cleanup...\n";
echo "Timestamp: " . date('Y-m-d H:i:s') . "\n\n";

try {
    $db = Database::getInstance()->getConnection();
    
    // Execute stored procedure for cleanup
    $stmt = $db->query("EXEC sp_CleanupOldData");
    
    echo "Cleanup completed successfully!\n";
    echo "Old sensor data (>2 years) has been removed.\n";
    echo "Old alert history has been archived.\n";
    echo "Processed offline buffer entries (>30 days) have been cleaned.\n";
    
    // Log the cleanup execution
    $logStmt = $db->prepare("
        INSERT INTO AuditLog (Action, EntityType, NewValues)
        VALUES ('CLEANUP_EXECUTED', 'System', ?)
    ");
    $logStmt->execute([json_encode([
        'executed_at' => date('Y-m-d H:i:s'),
        'script' => 'cron/cleanup.php'
    ])]);
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    error_log("Cleanup cron job failed: " . $e->getMessage());
    exit(1);
}

echo "\nDone.\n";
