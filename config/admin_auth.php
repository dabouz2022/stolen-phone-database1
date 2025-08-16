<?php
require_once __DIR__ . '/env_loader.php';
require_once __DIR__ . '/database.php';

// Load environment variables
EnvLoader::load(__DIR__ . '/.env');

// Secure Admin Authentication Class
class AdminAuth {
    private static $instance = null;
    private $db;
    private $jwtSecret;
    private $sessionTimeout;
    private $maxLoginAttempts;
    private $lockoutTime;

    private function __construct() {
        $this->db = Database::getInstance();
        $this->jwtSecret = $_ENV['JWT_SECRET'] ?? 'fallback_secret_key';
        $this->sessionTimeout = (int)($_ENV['SESSION_TIMEOUT'] ?? 1800);
        $this->maxLoginAttempts = (int)($_ENV['MAX_LOGIN_ATTEMPTS'] ?? 5);
        $this->lockoutTime = (int)($_ENV['LOCKOUT_TIME'] ?? 1800);
        
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $this->initializeDatabase();
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function initializeDatabase() {
        try {
            // Create admin users table if it doesn't exist
            $this->db->executeQuery("
                CREATE TABLE IF NOT EXISTS admin_users (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    username VARCHAR(255) UNIQUE NOT NULL,
                    password_hash VARCHAR(255) NOT NULL,
                    email VARCHAR(255),
                    is_active BOOLEAN DEFAULT TRUE,
                    last_login TIMESTAMP NULL,
                    failed_attempts INT DEFAULT 0,
                    locked_until TIMESTAMP NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                )
            ");

            // Create admin sessions table
            $this->db->executeQuery("
                CREATE TABLE IF NOT EXISTS admin_sessions (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    token VARCHAR(255) UNIQUE NOT NULL,
                    ip_address VARCHAR(45),
                    user_agent TEXT,
                    expires_at TIMESTAMP NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES admin_users(id) ON DELETE CASCADE
                )
            ");

            // Create login attempts table
            $this->db->executeQuery("
                CREATE TABLE IF NOT EXISTS admin_login_attempts (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    username VARCHAR(255),
                    ip_address VARCHAR(45),
                    user_agent TEXT,
                    success BOOLEAN DEFAULT FALSE,
                    attempt_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            ");

        } catch (Exception $e) {
            error_log("Failed to initialize admin database: " . $e->getMessage());
        }
    }

    public function login($username, $password) {
        try {
            // Check if IP is rate limited
            if (!$this->checkRateLimit()) {
                $this->logLoginAttempt($username, false, 'rate_limited');
                return ['success' => false, 'message' => 'Too many login attempts. Please try again later.'];
            }

            // Get user from database
            $stmt = $this->db->executeQuery(
                "SELECT * FROM admin_users WHERE username = ? AND is_active = TRUE",
                [$username]
            );
            $user = $stmt->fetch();

            if (!$user) {
                $this->logLoginAttempt($username, false, 'user_not_found');
                return ['success' => false, 'message' => 'Invalid credentials'];
            }

            // Check if user is locked
            if ($user['locked_until'] && new DateTime($user['locked_until']) > new DateTime()) {
                $this->logLoginAttempt($username, false, 'account_locked');
                return ['success' => false, 'message' => 'Account is temporarily locked'];
            }

            // Verify password
            if (!password_verify($password, $user['password_hash'])) {
                $this->incrementFailedAttempts($user['id']);
                $this->logLoginAttempt($username, false, 'invalid_password');
                return ['success' => false, 'message' => 'Invalid credentials'];
            }

            // Reset failed attempts and create session
            $this->resetFailedAttempts($user['id']);
            $token = $this->createSession($user['id']);
            $this->logLoginAttempt($username, true, 'success');

            return [
                'success' => true,
                'token' => $token,
                'user' => [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'email' => $user['email']
                ]
            ];

        } catch (Exception $e) {
            error_log("Login error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Login failed'];
        }
    }

    public function validateToken($token) {
        try {
            $stmt = $this->db->executeQuery(
                "SELECT s.*, u.username, u.is_active 
                 FROM admin_sessions s 
                 JOIN admin_users u ON s.user_id = u.id 
                 WHERE s.token = ? AND s.expires_at > NOW() AND u.is_active = TRUE",
                [$token]
            );
            
            $session = $stmt->fetch();
            
            if (!$session) {
                return false;
            }

            // Validate IP and User Agent for additional security
            if ($session['ip_address'] !== $_SERVER['REMOTE_ADDR'] ||
                $session['user_agent'] !== $_SERVER['HTTP_USER_AGENT']) {
                $this->invalidateSession($token);
                return false;
            }

            // Update session expiry
            $this->extendSession($token);
            
            return [
                'user_id' => $session['user_id'],
                'username' => $session['username']
            ];

        } catch (Exception $e) {
            error_log("Token validation error: " . $e->getMessage());
            return false;
        }
    }

    public function logout($token) {
        try {
            $this->invalidateSession($token);
            $this->db->logSecurityEvent('admin_logout', [
                'ip' => $_SERVER['REMOTE_ADDR'],
                'user_agent' => $_SERVER['HTTP_USER_AGENT']
            ]);
            return true;
        } catch (Exception $e) {
            error_log("Logout error: " . $e->getMessage());
            return false;
        }
    }

    public function createUser($username, $password, $email = null) {
        try {
            $passwordHash = password_hash($password, PASSWORD_BCRYPT, [
                'cost' => (int)($_ENV['BCRYPT_ROUNDS'] ?? 12)
            ]);

            $this->db->executeQuery(
                "INSERT INTO admin_users (username, password_hash, email) VALUES (?, ?, ?)",
                [$username, $passwordHash, $email]
            );

            return ['success' => true, 'message' => 'Admin user created successfully'];

        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                return ['success' => false, 'message' => 'Username already exists'];
            }
            error_log("Create user error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to create user'];
        }
    }

    private function createSession($userId) {
        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', time() + $this->sessionTimeout);

        $this->db->executeQuery(
            "INSERT INTO admin_sessions (user_id, token, ip_address, user_agent, expires_at) 
             VALUES (?, ?, ?, ?, ?)",
            [
                $userId,
                $token,
                $_SERVER['REMOTE_ADDR'],
                $_SERVER['HTTP_USER_AGENT'],
                $expiresAt
            ]
        );

        // Update last login
        $this->db->executeQuery(
            "UPDATE admin_users SET last_login = NOW() WHERE id = ?",
            [$userId]
        );

        return $token;
    }

    private function extendSession($token) {
        $expiresAt = date('Y-m-d H:i:s', time() + $this->sessionTimeout);
        $this->db->executeQuery(
            "UPDATE admin_sessions SET expires_at = ? WHERE token = ?",
            [$expiresAt, $token]
        );
    }

    private function invalidateSession($token) {
        $this->db->executeQuery("DELETE FROM admin_sessions WHERE token = ?", [$token]);
    }

    private function incrementFailedAttempts($userId) {
        $this->db->executeQuery(
            "UPDATE admin_users SET failed_attempts = failed_attempts + 1 WHERE id = ?",
            [$userId]
        );

        // Lock account if too many failed attempts
        $stmt = $this->db->executeQuery(
            "SELECT failed_attempts FROM admin_users WHERE id = ?",
            [$userId]
        );
        $user = $stmt->fetch();

        if ($user['failed_attempts'] >= $this->maxLoginAttempts) {
            $lockUntil = date('Y-m-d H:i:s', time() + $this->lockoutTime);
            $this->db->executeQuery(
                "UPDATE admin_users SET locked_until = ? WHERE id = ?",
                [$lockUntil, $userId]
            );
        }
    }

    private function resetFailedAttempts($userId) {
        $this->db->executeQuery(
            "UPDATE admin_users SET failed_attempts = 0, locked_until = NULL WHERE id = ?",
            [$userId]
        );
    }

    private function checkRateLimit() {
        $ip = $_SERVER['REMOTE_ADDR'];
        $stmt = $this->db->executeQuery(
            "SELECT COUNT(*) as attempts 
             FROM admin_login_attempts 
             WHERE ip_address = ? AND attempt_time > DATE_SUB(NOW(), INTERVAL 15 MINUTE)",
            [$ip]
        );
        
        $result = $stmt->fetch();
        return $result['attempts'] < 10; // Max 10 attempts per 15 minutes per IP
    }

    private function logLoginAttempt($username, $success, $reason = '') {
        $this->db->executeQuery(
            "INSERT INTO admin_login_attempts (username, ip_address, user_agent, success) 
             VALUES (?, ?, ?, ?)",
            [
                $username,
                $_SERVER['REMOTE_ADDR'],
                $_SERVER['HTTP_USER_AGENT'],
                $success
            ]
        );

        $this->db->logSecurityEvent($success ? 'admin_login_success' : 'admin_login_failed', [
            'username' => $username,
            'ip' => $_SERVER['REMOTE_ADDR'],
            'user_agent' => $_SERVER['HTTP_USER_AGENT'],
            'reason' => $reason
        ]);
    }

    public function requireAuth() {
        $token = $this->getTokenFromRequest();
        
        if (!$token) {
            $this->sendUnauthorizedResponse('No authentication token provided');
        }

        $user = $this->validateToken($token);
        if (!$user) {
            $this->sendUnauthorizedResponse('Invalid or expired token');
        }

        return $user;
    }

    private function getTokenFromRequest() {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? '';
        
        if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return $matches[1];
        }
        
        return $_POST['token'] ?? $_GET['token'] ?? null;
    }

    private function sendUnauthorizedResponse($message) {
        header('HTTP/1.1 401 Unauthorized');
        header('Content-Type: application/json');
        echo json_encode(['error' => $message]);
        exit;
    }

    public function cleanupExpiredSessions() {
        $this->db->executeQuery("DELETE FROM admin_sessions WHERE expires_at < NOW()");
    }
}
?>
