<?php
require_once '../config/env_loader.php';
require_once '../config/database.php';
require_once '../config/admin_auth.php';
require_once '../middleware/SecurityMiddleware.php';

// Load environment variables
EnvLoader::load(__DIR__ . '/../config/.env');

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$security = new SecurityMiddleware();
$adminAuth = AdminAuth::getInstance();

// Rate limiting for auth endpoints
$ipKey = 'auth_rate_limit:' . $_SERVER['REMOTE_ADDR'];
if (!$security->rateLimit($ipKey, 20, 900)) { // 20 attempts per 15 minutes
    http_response_code(429);
    echo json_encode(['error' => 'Too many requests. Please try again later.']);
    exit;
}

// Handle different HTTP methods
switch ($_SERVER['REQUEST_METHOD']) {
    case 'POST':
        handlePostRequest();
        break;
    case 'GET':
        handleGetRequest();
        break;
    case 'DELETE':
        handleDeleteRequest();
        break;
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        break;
}

function handlePostRequest() {
    global $adminAuth, $security;
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON data']);
        return;
    }
    
    $action = $data['action'] ?? '';
    
    switch ($action) {
        case 'login':
            handleLogin($data);
            break;
        case 'refresh':
            handleRefreshToken($data);
            break;
        case 'change_password':
            handleChangePassword($data);
            break;
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
}

function handleGetRequest() {
    global $adminAuth;
    
    $action = $_GET['action'] ?? '';
    
    switch ($action) {
        case 'verify':
            handleVerifyToken();
            break;
        case 'profile':
            handleGetProfile();
            break;
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
}

function handleDeleteRequest() {
    global $adminAuth;
    
    $data = json_decode(file_get_contents('php://input'), true);
    $action = $data['action'] ?? '';
    
    switch ($action) {
        case 'logout':
            handleLogout($data);
            break;
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
}

function handleLogin($data) {
    global $adminAuth, $security;
    
    // Validate input
    $validation = $security->validateInput($data, [
        'username' => ['required' => true, 'min_length' => 3, 'max_length' => 50],
        'password' => ['required' => true, 'min_length' => 6]
    ]);
    
    if ($validation !== true) {
        http_response_code(400);
        echo json_encode(['error' => 'Validation failed', 'details' => $validation]);
        return;
    }
    
    $username = $security->sanitizeInput($data['username']);
    $password = $data['password'];
    
    // Attempt login
    $result = $adminAuth->login($username, $password);
    
    if ($result['success']) {
        echo json_encode([
            'success' => true,
            'token' => $result['token'],
            'user' => $result['user'],
            'expires_in' => (int)($_ENV['SESSION_TIMEOUT'] ?? 1800)
        ]);
    } else {
        http_response_code(401);
        echo json_encode(['error' => $result['message']]);
    }
}

function handleVerifyToken() {
    global $adminAuth;
    
    $token = getTokenFromRequest();
    
    if (!$token) {
        http_response_code(401);
        echo json_encode(['error' => 'No token provided']);
        return;
    }
    
    $user = $adminAuth->validateToken($token);
    
    if ($user) {
        echo json_encode([
            'valid' => true,
            'user' => $user
        ]);
    } else {
        http_response_code(401);
        echo json_encode(['valid' => false, 'error' => 'Invalid or expired token']);
    }
}

function handleRefreshToken($data) {
    global $adminAuth;
    
    $token = $data['token'] ?? getTokenFromRequest();
    
    if (!$token) {
        http_response_code(401);
        echo json_encode(['error' => 'No token provided']);
        return;
    }
    
    $user = $adminAuth->validateToken($token);
    
    if ($user) {
        // Token is still valid, just extend it
        echo json_encode([
            'success' => true,
            'token' => $token,
            'user' => $user,
            'expires_in' => (int)($_ENV['SESSION_TIMEOUT'] ?? 1800)
        ]);
    } else {
        http_response_code(401);
        echo json_encode(['error' => 'Token refresh failed']);
    }
}

function handleLogout($data) {
    global $adminAuth;
    
    $token = $data['token'] ?? getTokenFromRequest();
    
    if ($token) {
        $adminAuth->logout($token);
    }
    
    echo json_encode(['success' => true, 'message' => 'Logged out successfully']);
}

function handleGetProfile() {
    global $adminAuth;
    
    $user = $adminAuth->requireAuth();
    
    // Get additional user info
    $db = Database::getInstance();
    $stmt = $db->executeQuery(
        "SELECT username, email, last_login, created_at FROM admin_users WHERE id = ?",
        [$user['user_id']]
    );
    
    $profile = $stmt->fetch();
    
    if ($profile) {
        echo json_encode([
            'success' => true,
            'profile' => $profile
        ]);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Profile not found']);
    }
}

function handleChangePassword($data) {
    global $adminAuth, $security;
    
    $user = $adminAuth->requireAuth();
    
    // Validate input
    $validation = $security->validateInput($data, [
        'current_password' => ['required' => true],
        'new_password' => ['required' => true, 'min_length' => 8],
        'confirm_password' => ['required' => true]
    ]);
    
    if ($validation !== true) {
        http_response_code(400);
        echo json_encode(['error' => 'Validation failed', 'details' => $validation]);
        return;
    }
    
    if ($data['new_password'] !== $data['confirm_password']) {
        http_response_code(400);
        echo json_encode(['error' => 'New passwords do not match']);
        return;
    }
    
    // Verify current password
    $db = Database::getInstance();
    $stmt = $db->executeQuery(
        "SELECT password_hash FROM admin_users WHERE id = ?",
        [$user['user_id']]
    );
    
    $currentUser = $stmt->fetch();
    
    if (!password_verify($data['current_password'], $currentUser['password_hash'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Current password is incorrect']);
        return;
    }
    
    // Update password
    $newPasswordHash = password_hash($data['new_password'], PASSWORD_BCRYPT, [
        'cost' => (int)($_ENV['BCRYPT_ROUNDS'] ?? 12)
    ]);
    
    $db->executeQuery(
        "UPDATE admin_users SET password_hash = ?, updated_at = NOW() WHERE id = ?",
        [$newPasswordHash, $user['user_id']]
    );
    
    // Log security event
    $db->logSecurityEvent('admin_password_changed', [
        'user_id' => $user['user_id'],
        'username' => $user['username'],
        'ip' => $_SERVER['REMOTE_ADDR'],
        'user_agent' => $_SERVER['HTTP_USER_AGENT']
    ]);
    
    echo json_encode(['success' => true, 'message' => 'Password changed successfully']);
}

function getTokenFromRequest() {
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? '';
    
    if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
        return $matches[1];
    }
    
    return $_POST['token'] ?? $_GET['token'] ?? null;
}

// Cleanup expired sessions periodically
if (rand(1, 100) <= 5) { // 5% chance
    $adminAuth->cleanupExpiredSessions();
}
?>
