<?php
/**
 * Authentication Helper Class
 * 
 * Handles password hashing, JWT token generation/validation, and session management
 */

class Auth {
    /**
     * Hash password using bcrypt
     */
    public static function hashPassword(string $password): string {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }
    
    /**
     * Verify password against hash
     */
    public static function verifyPassword(string $password, string $hash): bool {
        return password_verify($password, $hash);
    }
    
    /**
     * Generate JWT token
     */
    public static function generateToken(array $payload): string {
        $header = [
            'alg' => 'HS256',
            'typ' => 'JWT'
        ];
        
        $payload['iat'] = time();
        $payload['exp'] = time() + JWT_EXPIRY;
        
        $base64Header = self::base64UrlEncode(json_encode($header));
        $base64Payload = self::base64UrlEncode(json_encode($payload));
        
        $signature = hash_hmac('sha256', "{$base64Header}.{$base64Payload}", JWT_SECRET, true);
        $base64Signature = self::base64UrlEncode($signature);
        
        return "{$base64Header}.{$base64Payload}.{$base64Signature}";
    }
    
    /**
     * Validate and decode JWT token
     */
    public static function validateToken(string $token): ?array {
        $parts = explode('.', $token);
        
        if (count($parts) !== 3) {
            return null;
        }
        
        [$base64Header, $base64Payload, $base64Signature] = $parts;
        
        $header = json_decode(self::base64UrlDecode($base64Header), true);
        $payload = json_decode(self::base64UrlDecode($base64Payload), true);
        
        if (!$header || !$payload) {
            return null;
        }
        
        // Verify signature
        $signature = hash_hmac('sha256', "{$base64Header}.{$base64Payload}", JWT_SECRET, true);
        $base64SignatureExpected = self::base64UrlEncode($signature);
        
        if (!hash_equals($base64Signature, $base64SignatureExpected)) {
            return null;
        }
        
        // Check expiration
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            return null;
        }
        
        return $payload;
    }
    
    /**
     * Get current logged-in user
     */
    public static function getCurrentUser(): ?array {
        if (isset($_SESSION['user_id'])) {
            $db = Database::getInstance();
            return $db->fetchOne(
                "SELECT id, username, email, full_name, role, created_at, last_login 
                 FROM users 
                 WHERE id = ? AND is_active = 1",
                [$_SESSION['user_id']]
            );
        }
        return null;
    }
    
    /**
     * Check if user is logged in
     */
    public static function isLoggedIn(): bool {
        return self::getCurrentUser() !== null;
    }
    
    /**
     * Check if user has specific role
     */
    public static function hasRole(string $role): bool {
        $user = self::getCurrentUser();
        return $user && $user['role'] === $role;
    }
    
    /**
     * Check if user is admin
     */
    public static function isAdmin(): bool {
        return self::hasRole('admin');
    }
    
    /**
     * Login user
     */
    public static function login(string $username, string $password): array {
        $db = Database::getInstance();
        
        // Get user by username or email
        $user = $db->fetchOne(
            "SELECT * FROM users 
             WHERE (username = ? OR email = ?) AND is_active = 1",
            [$username, $username]
        );
        
        if (!$user) {
            Logger::warning('Login Failed - User not found', ['username' => $username]);
            return ['success' => false, 'message' => 'Invalid credentials'];
        }
        
        // Check lockout
        if ($user['lockout_until'] && strtotime($user['lockout_until']) > time()) {
            $remainingTime = strtotime($user['lockout_until']) - time();
            Logger::warning('Login Failed - Account locked', ['username' => $username]);
            return [
                'success' => false, 
                'message' => "Account locked. Try again in " . floor($remainingTime / 60) . " minutes"
            ];
        }
        
        // Verify password
        if (!self::verifyPassword($password, $user['password_hash'])) {
            // Increment failed attempts
            $attempts = ($user['failed_login_attempts'] ?? 0) + 1;
            
            if ($attempts >= MAX_LOGIN_ATTEMPTS) {
                // Lock account
                $lockoutUntil = date('Y-m-d H:i:s', time() + LOCKOUT_TIME);
                $db->update('users', [
                    'failed_login_attempts' => $attempts,
                    'lockout_until' => $lockoutUntil
                ], 'id = ?', [$user['id']]);
                
                Logger::warning('Account Locked', [
                    'username' => $username,
                    'lockout_until' => $lockoutUntil
                ]);
                
                return [
                    'success' => false,
                    'message' => "Too many failed attempts. Account locked for " . (LOCKOUT_TIME / 60) . " minutes"
                ];
            }
            
            $db->update('users', ['failed_login_attempts' => $attempts], 'id = ?', [$user['id']]);
            
            Logger::warning('Login Failed - Invalid password', [
                'username' => $username,
                'attempts' => $attempts
            ]);
            
            return ['success' => false, 'message' => 'Invalid credentials'];
        }
        
        // Clear failed attempts and update last login
        $db->update('users', [
            'failed_login_attempts' => 0,
            'lockout_until' => null,
            'last_login' => date('Y-m-d H:i:s')
        ], 'id = ?', [$user['id']]);
        
        // Set session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['login_time'] = time();
        
        // Log successful login
        Logger::info('User Logged In', [
            'user_id' => $user['id'],
            'username' => $user['username']
        ]);
        
        // Generate JWT token
        $token = self::generateToken([
            'user_id' => $user['id'],
            'username' => $user['username'],
            'role' => $user['role']
        ]);
        
        return [
            'success' => true,
            'message' => 'Login successful',
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'full_name' => $user['full_name'],
                'role' => $user['role']
            ],
            'token' => $token
        ];
    }
    
    /**
     * Logout user
     */
    public static function logout(): void {
        if (isset($_SESSION['user_id'])) {
            Logger::info('User Logged Out', ['user_id' => $_SESSION['user_id']]);
        }
        
        session_destroy();
        $_SESSION = [];
    }
    
    /**
     * Generate device token
     */
    public static function generateDeviceToken(): string {
        return DEVICE_TOKEN_PREFIX . bin2hex(random_bytes(16));
    }
    
    /**
     * Validate device token
     */
    public static function validateDeviceToken(string $token): ?array {
        $db = Database::getInstance();
        return $db->fetchOne(
            "SELECT d.*, u.username as owner_username 
             FROM devices d
             JOIN users u ON d.user_id = u.id
             WHERE d.device_token = ? AND d.is_active = 1",
            [$token]
        );
    }
    
    /**
     * Base64 URL encode
     */
    private static function base64UrlEncode(string $data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
    
    /**
     * Base64 URL decode
     */
    private static function base64UrlDecode(string $data): string {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }
        return base64_decode(strtr($data, '-_', '+/'));
    }
}
