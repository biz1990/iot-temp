<?php
/**
 * Authentication Middleware
 * 
 * Validates JWT tokens and checks user permissions
 */

class AuthMiddleware {
    /**
     * Handle authentication check
     */
    public static function handle(): void {
        $headers = getallheaders();
        
        // Check for JWT token in Authorization header
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        
        if (empty($authHeader)) {
            // Fall back to session
            if (!Auth::isLoggedIn()) {
                self::unauthorized('Authentication required');
            }
            return;
        }
        
        // Extract token from "Bearer <token>" format
        if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            $token = $matches[1];
            
            $payload = Auth::validateToken($token);
            
            if (!$payload) {
                self::unauthorized('Invalid or expired token');
            }
            
            // Store validated user info in session for this request
            $_SESSION['user_id'] = $payload['user_id'];
            $_SESSION['username'] = $payload['username'];
            $_SESSION['role'] = $payload['role'];
        } else {
            self::unauthorized('Invalid authorization header format');
        }
    }
    
    /**
     * Handle device token authentication
     */
    public static function handleDeviceAuth(): ?array {
        $headers = getallheaders();
        $deviceToken = $headers['X-Device-Token'] ?? $headers['x-device-token'] ?? '';
        
        if (empty($deviceToken)) {
            self::unauthorized('Device token required');
        }
        
        $device = Auth::validateDeviceToken($deviceToken);
        
        if (!$device) {
            Logger::warning('Invalid device token attempt', ['token_prefix' => substr($deviceToken, 0, 10)]);
            self::unauthorized('Invalid device token');
        }
        
        return $device;
    }
    
    /**
     * Check if user has required role
     */
    public static function requireRole(string $role): void {
        self::handle();
        
        if (!Auth::hasRole($role)) {
            self::forbidden("Access denied. Required role: {$role}");
        }
    }
    
    /**
     * Check if user is admin
     */
    public static function requireAdmin(): void {
        self::requireRole('admin');
    }
    
    /**
     * Send unauthorized response
     */
    private static function unauthorized(string $message): void {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'error' => 'Unauthorized',
            'message' => $message
        ]);
        exit;
    }
    
    /**
     * Send forbidden response
     */
    private static function forbidden(string $message): void {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'error' => 'Forbidden',
            'message' => $message
        ]);
        exit;
    }
}

/**
 * Get all HTTP headers (fallback for Apache)
 */
if (!function_exists('getallheaders')) {
    function getallheaders() {
        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) == 'HTTP_') {
                $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
            }
        }
        return $headers;
    }
}
