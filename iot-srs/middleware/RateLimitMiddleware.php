<?php
/**
 * Rate Limiting Middleware
 * 
 * Prevents API abuse by limiting requests per time window
 */

class RateLimitMiddleware {
    private static $limits = [];
    
    /**
     * Check rate limit for current request
     */
    public static function handle(): void {
        if (!RATE_LIMIT_ENABLED) {
            return;
        }
        
        $identifier = self::getIdentifier();
        $window = RATE_LIMIT_WINDOW;
        $maxRequests = RATE_LIMIT_REQUESTS;
        
        // Clean old entries
        self::cleanup($identifier, $window);
        
        // Get current request count
        $count = self::getRequestCount($identifier, $window);
        
        if ($count >= $maxRequests) {
            self::rateLimitExceeded($identifier, $window);
        }
        
        // Increment counter
        self::incrementRequest($identifier);
        
        // Add rate limit headers
        self::addHeaders($count + 1, $maxRequests, $window);
    }
    
    /**
     * Get unique identifier for rate limiting
     */
    private static function getIdentifier(): string {
        // Use device token if available
        $headers = getallheaders();
        $deviceToken = $headers['X-Device-Token'] ?? $headers['x-device-token'] ?? '';
        
        if (!empty($deviceToken)) {
            return 'device:' . hash('sha256', $deviceToken);
        }
        
        // Fall back to IP address
        return 'ip:' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    }
    
    /**
     * Get request count in current window
     */
    private static function getRequestCount(string $identifier, int $window): int {
        $cutoff = time() - $window;
        
        if (!isset(self::$limits[$identifier])) {
            self::$limits[$identifier] = [];
        }
        
        // Filter requests within window
        self::$limits[$identifier] = array_filter(
            self::$limits[$identifier],
            fn($timestamp) => $timestamp > $cutoff
        );
        
        return count(self::$limits[$identifier]);
    }
    
    /**
     * Increment request counter
     */
    private static function incrementRequest(string $identifier): void {
        if (!isset(self::$limits[$identifier])) {
            self::$limits[$identifier] = [];
        }
        self::$limits[$identifier][] = time();
    }
    
    /**
     * Clean old entries outside window
     */
    private static function cleanup(string $identifier, int $window): void {
        $cutoff = time() - $window;
        
        if (isset(self::$limits[$identifier])) {
            self::$limits[$identifier] = array_filter(
                self::$limits[$identifier],
                fn($timestamp) => $timestamp > $cutoff
            );
        }
    }
    
    /**
     * Handle rate limit exceeded
     */
    private static function rateLimitExceeded(string $identifier, int $window): void {
        $retryAfter = $window;
        
        Logger::warning('Rate Limit Exceeded', [
            'identifier' => $identifier,
            'window' => $window
        ]);
        
        http_response_code(429);
        header("Retry-After: {$retryAfter}");
        
        echo json_encode([
            'success' => false,
            'error' => 'Too Many Requests',
            'message' => "Rate limit exceeded. Try again in {$retryAfter} seconds.",
            'retry_after' => $retryAfter
        ]);
        exit;
    }
    
    /**
     * Add rate limit headers to response
     */
    private static function addHeaders(int $current, int $limit, int $window): void {
        header("X-RateLimit-Limit: {$limit}");
        header("X-RateLimit-Remaining: " . max(0, $limit - $current));
        header("X-RateLimit-Reset: " . (time() + $window));
    }
}
