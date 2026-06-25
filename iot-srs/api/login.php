<?php
/**
 * API Endpoint: User Authentication
 * POST /api/login.php
 * GET /api/logout.php
 */

session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/Auth.php';

try {
    $db = Database::getInstance()->getConnection();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Login
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);

        if (empty($data['username']) || empty($data['password'])) {
            throw new Exception('Username and password required', 400);
        }

        $stmt = $db->prepare("
            SELECT UserID, Username, PasswordHash, Role, IsActive 
            FROM Users 
            WHERE Username = ? AND IsActive = 1
        ");
        $stmt->execute([$data['username']]);
        $user = $stmt->fetch();

        if (!$user) {
            throw new Exception('Invalid credentials', 401);
        }

        if (!Auth::verifyPassword($data['password'], $user['PasswordHash'])) {
            // Log failed attempt
            error_log("Failed login attempt for user: " . $data['username']);
            throw new Exception('Invalid credentials', 401);
        }

        // Update last login
        $updateStmt = $db->prepare("UPDATE Users SET LastLogin = GETDATE() WHERE UserID = ?");
        $updateStmt->execute([$user['UserID']]);

        // Login successful
        Auth::login($user['UserID'], $user['Username'], $user['Role']);

        // Log audit
        $auditStmt = $db->prepare("
            INSERT INTO AuditLog (UserID, Action, IPAddress, UserAgent)
            VALUES (?, 'LOGIN', ?, ?)
        ");
        $auditStmt->execute([
            $user['UserID'],
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Login successful',
            'user' => [
                'id' => $user['UserID'],
                'username' => $user['Username'],
                'role' => $user['Role'],
                'fullName' => $user['FullName'] ?? ''
            ]
        ]);

    } elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Logout
        if (Auth::isLoggedIn()) {
            $userId = Auth::getUserId();
            
            // Log audit
            $auditStmt = $db->prepare("
                INSERT INTO AuditLog (UserID, Action, IPAddress, UserAgent)
                VALUES (?, 'LOGOUT', ?, ?)
            ");
            $auditStmt->execute([
                $userId,
                $_SERVER['REMOTE_ADDR'] ?? '',
                $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]);
        }
        
        Auth::logout();
        
        echo json_encode([
            'success' => true,
            'message' => 'Logout successful'
        ]);
    }

} catch (Exception $e) {
    http_response_code($e->getCode() ?: 500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
