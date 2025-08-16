<?php
require_once '../config/database.php';
require_once '../middleware/SecurityMiddleware.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

$security = new SecurityMiddleware();
$db = Database::getInstance();

// Set current IP for audit logging
$security->setCurrentIP($_SERVER['REMOTE_ADDR']);

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Authenticate request
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    $security->authenticate();
}

// Rate limiting
$ipKey = 'rate_limit:' . $_SERVER['REMOTE_ADDR'];
if (!$security->rateLimit($ipKey, 100, 3600)) {
    http_response_code(429);
    echo json_encode(['error' => 'Too many requests']);
    exit;
}

// Input validation rules
$rules = [
    'imei' => [
        'required' => true,
        'type' => 'imei',
        'min_length' => 15,
        'max_length' => 15
    ],
    'brand' => [
        'required' => true,
        'min_length' => 2,
        'max_length' => 50
    ],
    'model' => [
        'required' => true,
        'min_length' => 2,
        'max_length' => 50
    ]
];

// Handle different HTTP methods
switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        handleGetRequest();
        break;
    case 'POST':
        handlePostRequest();
        break;
    case 'PUT':
        handlePutRequest();
        break;
    case 'DELETE':
        handleDeleteRequest();
        break;
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        break;
}

function handleGetRequest() {
    global $db, $security;
    
    $imei = $_GET['imei'] ?? null;
    
    if (!$imei) {
        http_response_code(400);
        echo json_encode(['error' => 'IMEI parameter is required']);
        return;
    }
    
    try {
        $stmt = $db->executeQuery(
            "CALL secure_check_phone(?)",
            [$imei]
        );
        
        $result = $stmt->fetch();
        
        if ($result) {
            // Decrypt sensitive data if needed
            $result['serial_number'] = $security->decryptSensitiveData($result['serial_number']);
            echo json_encode($result);
        } else {
            echo json_encode(['message' => 'Phone not found in database']);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error']);
        error_log("Database error: " . $e->getMessage());
    }
}

function handlePostRequest() {
    global $db, $security, $rules;
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validate input
    $validation = $security->validateInput($data, $rules);
    if ($validation !== true) {
        http_response_code(400);
        echo json_encode(['errors' => $validation]);
        return;
    }
    
    // Sanitize input
    $data = $security->sanitizeInput($data);
    
    // Encrypt sensitive data
    $data['serial_number'] = $security->encryptSensitiveData($data['serial_number']);
    
    try {
        $db->executeQuery(
            "CALL secure_add_phone(?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $data['imei'],
                $data['brand'],
                $data['model'],
                $data['color'] ?? null,
                $data['serial_number'],
                $_SESSION['user_id'],
                $data['location'] ?? null,
                $data['description'] ?? null,
                $data['box_photo_path'] ?? null
            ]
        );
        
        http_response_code(201);
        echo json_encode(['message' => 'Phone added successfully']);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to add phone']);
        error_log("Database error: " . $e->getMessage());
    }
}

function handlePutRequest() {
    global $db, $security;
    
    $data = json_decode(file_get_contents('php://input'), true);
    $imei = $data['imei'] ?? null;
    
    if (!$imei) {
        http_response_code(400);
        echo json_encode(['error' => 'IMEI is required']);
        return;
    }
    
    try {
        // Check if phone exists and user has permission
        $stmt = $db->executeQuery(
            "SELECT * FROM stolen_phones WHERE imei = ? AND reported_by = ?",
            [$imei, $_SESSION['user_id']]
        );
        
        if ($stmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(['error' => 'Phone not found or unauthorized']);
            return;
        }
        
        // Update phone details
        $db->executeQuery(
            "UPDATE stolen_phones SET 
                brand = ?, model = ?, color = ?, 
                location = ?, description = ?, 
                status = ?, updated_at = NOW()
             WHERE imei = ?",
            [
                $data['brand'],
                $data['model'],
                $data['color'] ?? null,
                $data['location'] ?? null,
                $data['description'] ?? null,
                $data['status'] ?? 'stolen',
                $imei
            ]
        );
        
        echo json_encode(['message' => 'Phone updated successfully']);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update phone']);
        error_log("Database error: " . $e->getMessage());
    }
}

function handleDeleteRequest() {
    global $db, $security;
    
    $imei = $_GET['imei'] ?? null;
    
    if (!$imei) {
        http_response_code(400);
        echo json_encode(['error' => 'IMEI parameter is required']);
        return;
    }
    
    try {
        // Check if phone exists and user has permission
        $stmt = $db->executeQuery(
            "SELECT * FROM stolen_phones WHERE imei = ? AND reported_by = ?",
            [$imei, $_SESSION['user_id']]
        );
        
        if ($stmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(['error' => 'Phone not found or unauthorized']);
            return;
        }
        
        // Soft delete the phone
        $db->executeQuery(
            "UPDATE stolen_phones SET 
                status = 'deleted',
                updated_at = NOW()
             WHERE imei = ?",
            [$imei]
        );
        
        echo json_encode(['message' => 'Phone deleted successfully']);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to delete phone']);
        error_log("Database error: " . $e->getMessage());
    }
}
?> 