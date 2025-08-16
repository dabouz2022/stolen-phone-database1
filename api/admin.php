<?php
require_once '../config/database.php';
require_once '../config/admin_auth.php';
require_once '../middleware/SecurityMiddleware.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, DELETE');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

$security = new SecurityMiddleware();
$db = Database::getInstance();
$adminAuth = AdminAuth::getInstance();

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Rate limiting for admin endpoints
$ipKey = 'admin_rate_limit:' . $_SERVER['REMOTE_ADDR'];
if (!$security->rateLimit($ipKey, 50, 3600)) {
    http_response_code(429);
    echo json_encode(['error' => 'Too many requests']);
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
    global $adminAuth;
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Verify admin password for each request
    if (!isset($data['admin_password'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Admin password required']);
        return;
    }
    
    if (!$adminAuth->authenticate($data['admin_password'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid admin password']);
        return;
    }
    
    // Handle different admin actions
    switch ($data['action'] ?? '') {
        case 'get_all_phones':
            getAllPhones();
            break;
        case 'delete_phone':
            deletePhone($data);
            break;
        case 'update_phone':
            updatePhone($data);
            break;
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
}

function handleGetRequest() {
    global $adminAuth;
    
    // Verify admin password for each request
    if (!isset($_GET['admin_password'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Admin password required']);
        return;
    }
    
    if (!$adminAuth->authenticate($_GET['admin_password'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid admin password']);
        return;
    }
    
    // Handle different admin actions
    switch ($_GET['action'] ?? '') {
        case 'get_phone':
            getPhone($_GET['imei'] ?? '');
            break;
        case 'get_stats':
            getStats();
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
    
    // Verify admin password for each request
    if (!isset($data['admin_password'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Admin password required']);
        return;
    }
    
    if (!$adminAuth->authenticate($data['admin_password'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid admin password']);
        return;
    }
    
    // Handle phone deletion
    if (isset($data['imei'])) {
        deletePhone($data);
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'IMEI required for deletion']);
    }
}

function getAllPhones() {
    global $db, $security;
    
    try {
        $stmt = $db->executeQuery(
            "SELECT * FROM stolen_phones WHERE status != 'deleted' ORDER BY report_date DESC"
        );
        
        $phones = $stmt->fetchAll();
        
        // Decrypt sensitive data
        foreach ($phones as &$phone) {
            $phone['serial_number'] = $security->decryptSensitiveData($phone['serial_number']);
        }
        
        echo json_encode($phones);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to fetch phones']);
        error_log("Database error: " . $e->getMessage());
    }
}

function getPhone($imei) {
    global $db, $security;
    
    if (!$imei) {
        http_response_code(400);
        echo json_encode(['error' => 'IMEI required']);
        return;
    }
    
    try {
        $stmt = $db->executeQuery(
            "SELECT * FROM stolen_phones WHERE imei = ?",
            [$imei]
        );
        
        $phone = $stmt->fetch();
        
        if ($phone) {
            // Decrypt sensitive data
            $phone['serial_number'] = $security->decryptSensitiveData($phone['serial_number']);
            echo json_encode($phone);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Phone not found']);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to fetch phone']);
        error_log("Database error: " . $e->getMessage());
    }
}

function deletePhone($data) {
    global $db;
    
    if (!isset($data['imei'])) {
        http_response_code(400);
        echo json_encode(['error' => 'IMEI required']);
        return;
    }
    
    try {
        $db->executeQuery(
            "UPDATE stolen_phones SET 
                status = 'deleted',
                updated_at = NOW()
             WHERE imei = ?",
            [$data['imei']]
        );
        
        echo json_encode(['message' => 'Phone deleted successfully']);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to delete phone']);
        error_log("Database error: " . $e->getMessage());
    }
}

function updatePhone($data) {
    global $db;
    
    if (!isset($data['imei'])) {
        http_response_code(400);
        echo json_encode(['error' => 'IMEI required']);
        return;
    }
    
    try {
        $db->executeQuery(
            "UPDATE stolen_phones SET 
                brand = ?, model = ?, color = ?, 
                location = ?, description = ?, 
                status = ?, updated_at = NOW()
             WHERE imei = ?",
            [
                $data['brand'] ?? null,
                $data['model'] ?? null,
                $data['color'] ?? null,
                $data['location'] ?? null,
                $data['description'] ?? null,
                $data['status'] ?? 'stolen',
                $data['imei']
            ]
        );
        
        echo json_encode(['message' => 'Phone updated successfully']);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update phone']);
        error_log("Database error: " . $e->getMessage());
    }
}

function getStats() {
    global $db;
    
    try {
        $stats = [];
        
        // Get total phones
        $stmt = $db->executeQuery("SELECT COUNT(*) as total FROM stolen_phones WHERE status != 'deleted'");
        $stats['total_phones'] = $stmt->fetch()['total'];
        
        // Get phones by status
        $stmt = $db->executeQuery("SELECT status, COUNT(*) as count FROM stolen_phones GROUP BY status");
        $stats['by_status'] = $stmt->fetchAll();
        
        // Get recent activity
        $stmt = $db->executeQuery(
            "SELECT COUNT(*) as count, DATE(report_date) as date 
             FROM stolen_phones 
             WHERE report_date > DATE_SUB(NOW(), INTERVAL 7 DAY)
             GROUP BY DATE(report_date)
             ORDER BY date DESC"
        );
        $stats['recent_activity'] = $stmt->fetchAll();
        
        echo json_encode($stats);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to fetch stats']);
        error_log("Database error: " . $e->getMessage());
    }
}
?> 