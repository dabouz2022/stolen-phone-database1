<?php
class SecurityMiddleware {
    private $db;
    private $maxLoginAttempts = 5;
    private $lockoutTime = 1800; // 30 minutes

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function authenticate() {
        $headers = getallheaders();
        $token = $headers['Authorization'] ?? null;

        if (!$token) {
            $this->sendUnauthorizedResponse('No authentication token provided');
        }

        if (!$this->validateToken($token)) {
            $this->sendUnauthorizedResponse('Invalid or expired token');
        }

        return true;
    }

    public function validateToken($token) {
        try {
            $stmt = $this->db->executeQuery(
                "SELECT * FROM sessions WHERE token = ? AND expires_at > NOW()",
                [$token]
            );
            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            error_log("Token validation failed: " . $e->getMessage());
            return false;
        }
    }

    public function checkLoginAttempts($username) {
        try {
            $stmt = $this->db->executeQuery(
                "SELECT COUNT(*) as attempts 
                 FROM login_attempts 
                 WHERE username = ? 
                 AND attempt_time > DATE_SUB(NOW(), INTERVAL ? SECOND)
                 AND is_successful = 0",
                [$username, $this->lockoutTime]
            );
            
            $result = $stmt->fetch();
            return $result['attempts'] < $this->maxLoginAttempts;
        } catch (Exception $e) {
            error_log("Login attempt check failed: " . $e->getMessage());
            return false;
        }
    }

    public function logLoginAttempt($username, $success) {
        try {
            $this->db->executeQuery(
                "INSERT INTO login_attempts (username, ip_address, is_successful) 
                 VALUES (?, ?, ?)",
                [$username, $_SERVER['REMOTE_ADDR'], $success]
            );
        } catch (Exception $e) {
            error_log("Login attempt logging failed: " . $e->getMessage());
        }
    }

    public function validateInput($input, $rules) {
        $errors = [];
        
        foreach ($rules as $field => $rule) {
            if (!isset($input[$field])) {
                $errors[$field] = "Field is required";
                continue;
            }

            $value = $input[$field];
            
            if (isset($rule['required']) && $rule['required'] && empty($value)) {
                $errors[$field] = "Field is required";
            }
            
            if (isset($rule['type'])) {
                switch ($rule['type']) {
                    case 'email':
                        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                            $errors[$field] = "Invalid email format";
                        }
                        break;
                    case 'numeric':
                        if (!is_numeric($value)) {
                            $errors[$field] = "Must be a number";
                        }
                        break;
                    case 'imei':
                        if (!preg_match('/^\d{15}$/', $value)) {
                            $errors[$field] = "Invalid IMEI format";
                        }
                        break;
                }
            }
            
            if (isset($rule['min_length']) && strlen($value) < $rule['min_length']) {
                $errors[$field] = "Minimum length is {$rule['min_length']}";
            }
            
            if (isset($rule['max_length']) && strlen($value) > $rule['max_length']) {
                $errors[$field] = "Maximum length is {$rule['max_length']}";
            }
        }
        
        return empty($errors) ? true : $errors;
    }

    public function sanitizeInput($input) {
        return $this->db->sanitizeInput($input);
    }

    public function encryptSensitiveData($data) {
        return $this->db->encryptData($data);
    }

    public function decryptSensitiveData($data) {
        return $this->db->decryptData($data);
    }

    public function logSecurityEvent($event, $details) {
        $this->db->logSecurityEvent($event, $details);
    }

    private function sendUnauthorizedResponse($message) {
        header('HTTP/1.1 401 Unauthorized');
        echo json_encode(['error' => $message]);
        exit;
    }

    public function setCurrentIP($ip) {
        $this->db->executeQuery("SET @current_ip = ?", [$ip]);
    }

    public function generateCSRFToken() {
        $token = bin2hex(random_bytes(32));
        $_SESSION['csrf_token'] = $token;
        return $token;
    }

    public function validateCSRFToken($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }

    public function rateLimit($key, $limit, $period) {
        $redis = new Redis();
        $redis->connect('127.0.0.1', 6379);
        
        $current = $redis->get($key);
        if ($current === false) {
            $redis->setex($key, $period, 1);
            return true;
        }
        
        if ($current >= $limit) {
            return false;
        }
        
        $redis->incr($key);
        return true;
    }
}
?> 