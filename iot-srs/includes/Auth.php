<?php
/**
 * Authentication Helper Functions
 * Handles user authentication, password hashing, and session management
 */

class Auth {
    
    /**
     * Hash password using bcrypt
     */
    public static function hashPassword($password) {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    /**
     * Verify password against hash
     */
    public static function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }

    /**
     * Generate secure random token for device authentication
     */
    public static function generateToken($length = 32) {
        return bin2hex(random_bytes($length));
    }

    /**
     * Check if user is logged in
     */
    public static function isLoggedIn() {
        return isset($_SESSION['user_id']) && isset($_SESSION['user_role']);
    }

    /**
     * Check if user has specific role
     */
    public static function hasRole($role) {
        return self::isLoggedIn() && $_SESSION['user_role'] === $role;
    }

    /**
     * Check if user is admin
     */
    public static function isAdmin() {
        return self::hasRole('Admin');
    }

    /**
     * Login user
     */
    public static function login($userId, $username, $role) {
        $_SESSION['user_id'] = $userId;
        $_SESSION['username'] = $username;
        $_SESSION['user_role'] = $role;
        $_SESSION['login_time'] = time();
    }

    /**
     * Logout user
     */
    public static function logout() {
        session_destroy();
        session_start();
    }

    /**
     * Get current user ID
     */
    public static function getUserId() {
        return $_SESSION['user_id'] ?? null;
    }

    /**
     * Get current username
     */
    public static function getUsername() {
        return $_SESSION['username'] ?? 'Guest';
    }

    /**
     * Get current user role
     */
    public static function getRole() {
        return $_SESSION['user_role'] ?? null;
    }
}
