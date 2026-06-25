<?php
/**
 * CORS Middleware
 * 
 * Handles Cross-Origin Resource Sharing headers
 */

class CorsMiddleware {
    /**
     * Handle CORS headers
     */
    public static function handle(): void {
        if (!CORS_ENABLED) {
            return;
        }
        
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        
        // Check if origin is allowed
        $allowedOrigins = CORS_ALLOWED_ORIGINS;
        $isAllowed = in_array('*', $allowedOrigins) || in_array($origin, $allowedOrigins);
        
        if ($isAllowed) {
            header("Access-Control-Allow-Origin: " . ($origin === '*' ? '*' : $origin));
        }
        
        // Allow methods
        header("Access-Control-Allow-Methods: " . implode(', ', CORS_ALLOWED_METHODS));
        
        // Allow headers
        header("Access-Control-Allow-Headers: " . implode(', ', CORS_ALLOWED_HEADERS));
        
        // Allow credentials
        header("Access-Control-Allow-Credentials: true");
        
        // Max age for preflight cache
        header("Access-Control-Max-Age: 86400");
        
        // Handle preflight OPTIONS request
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(204);
            exit;
        }
    }
}
