<?php
/**
 * Logging System Class
 * 
 * Handles application logging with file rotation and multiple log levels
 */

class Logger {
    private static $instance = null;
    private $logPath;
    private $currentFile;
    
    private const LEVELS = [
        'DEBUG' => 0,
        'INFO' => 1,
        'WARNING' => 2,
        'ERROR' => 3,
        'CRITICAL' => 4
    ];
    
    /**
     * Private constructor
     */
    private function __construct() {
        $this->logPath = LOG_PATH;
        $this->currentFile = $this->getLogFileName();
        
        // Ensure log directory exists
        if (!is_dir($this->logPath)) {
            mkdir($this->logPath, 0755, true);
        }
    }
    
    /**
     * Get singleton instance
     */
    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Get log file name based on rotation policy
     */
    private function getLogFileName(): string {
        switch (LOG_ROTATION) {
            case 'daily':
                return $this->logPath . 'app_' . date('Y-m-d') . '.log';
            case 'hourly':
                return $this->logPath . 'app_' . date('Y-m-d_H') . '.log';
            default:
                return $this->logPath . 'app.log';
        }
    }
    
    /**
     * Rotate log files
     */
    private function rotateLogs(): void {
        $files = glob($this->logPath . 'app_*.log');
        
        if (count($files) > LOG_MAX_FILES) {
            usort($files, function($a, $b) {
                return filemtime($a) - filemtime($b);
            });
            
            $toDelete = array_slice($files, 0, count($files) - LOG_MAX_FILES);
            foreach ($toDelete as $file) {
                unlink($file);
            }
        }
    }
    
    /**
     * Write log entry
     */
    private function write(string $level, string $message, array $context = []): void {
        // Check log level
        if (self::LEVELS[$level] < self::LEVELS[LOG_LEVEL]) {
            return;
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $logFile = $this->getLogFileName();
        
        // Format context
        $contextStr = '';
        if (!empty($context)) {
            $contextStr = ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        
        // Format log entry
        $entry = "[{$timestamp}] [{$level}] {$message}{$contextStr}" . PHP_EOL;
        
        // Write to file
        file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
        
        // Rotate logs periodically
        $this->rotateLogs();
    }
    
    /**
     * Log debug message
     */
    public static function debug(string $message, array $context = []): void {
        self::getInstance()->write('DEBUG', $message, $context);
    }
    
    /**
     * Log info message
     */
    public static function info(string $message, array $context = []): void {
        self::getInstance()->write('INFO', $message, $context);
    }
    
    /**
     * Log warning message
     */
    public static function warning(string $message, array $context = []): void {
        self::getInstance()->write('WARNING', $message, $context);
    }
    
    /**
     * Log error message
     */
    public static function error(string $message, array $context = []): void {
        self::getInstance()->write('ERROR', $message, $context);
    }
    
    /**
     * Log critical message
     */
    public static function critical(string $message, array $context = []): void {
        self::getInstance()->write('CRITICAL', $message, $context);
    }
    
    /**
     * Log audit trail (special type for security events)
     */
    public static function audit(string $action, array $details = []): void {
        $user = Auth::getCurrentUser();
        $details['user_id'] = $user['id'] ?? null;
        $details['username'] = $user['username'] ?? 'anonymous';
        $details['ip_address'] = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $details['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        
        self::getInstance()->write('INFO', "AUDIT: {$action}", $details);
    }
    
    /**
     * View logs (for API endpoint)
     */
    public static function view(array $filters = []): array {
        $limit = $filters['limit'] ?? 100;
        $level = $filters['level'] ?? null;
        $search = $filters['search'] ?? null;
        
        $logs = [];
        $logFiles = glob(LOG_PATH . 'app_*.log');
        rsort($logFiles); // Most recent first
        
        foreach ($logFiles as $file) {
            $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            
            foreach ($lines as $line) {
                // Parse log line
                if (preg_match('/\[(.*?)\] \[(.*?)\] (.*?)(?:\s+(\{.*\}))?$/', $line, $matches)) {
                    $timestamp = $matches[1];
                    $logLevel = $matches[2];
                    $message = $matches[3];
                    $context = isset($matches[4]) ? json_decode($matches[4], true) : [];
                    
                    // Apply filters
                    if ($level && $logLevel !== $level) {
                        continue;
                    }
                    
                    if ($search && stripos($message, $search) === false) {
                        continue;
                    }
                    
                    $logs[] = [
                        'timestamp' => $timestamp,
                        'level' => $logLevel,
                        'message' => $message,
                        'context' => $context
                    ];
                    
                    if (count($logs) >= $limit) {
                        break 2;
                    }
                }
            }
        }
        
        return $logs;
    }
    
    /**
     * Clear old logs
     */
    public static function clearOlderThan(int $days): int {
        $cutoff = time() - ($days * 86400);
        $deleted = 0;
        
        $files = glob(LOG_PATH . 'app_*.log');
        foreach ($files as $file) {
            if (filemtime($file) < $cutoff) {
                unlink($file);
                $deleted++;
            }
        }
        
        return $deleted;
    }
}
