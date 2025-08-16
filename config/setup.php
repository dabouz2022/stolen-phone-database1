<?php
require_once __DIR__ . '/env_loader.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/admin_auth.php';

// Load environment variables
EnvLoader::load(__DIR__ . '/.env');

// Security check - only allow setup if no admin users exist
function isSetupAllowed() {
    try {
        $db = Database::getInstance();
        $stmt = $db->executeQuery("SELECT COUNT(*) as count FROM admin_users");
        $result = $stmt->fetch();
        return $result['count'] == 0;
    } catch (Exception $e) {
        // If table doesn't exist, setup is allowed
        return true;
    }
}

// Handle setup request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    try {
        // Check if setup is allowed
        if (!isSetupAllowed()) {
            echo json_encode(['success' => false, 'message' => 'Setup has already been completed']);
            exit;
        }

        $data = json_decode(file_get_contents('php://input'), true);
        
        // Validate setup token
        $setupToken = $data['setup_token'] ?? '';
        $expectedToken = $_ENV['ADMIN_SETUP_TOKEN'] ?? '';
        
        if (!$setupToken || $setupToken !== $expectedToken) {
            echo json_encode(['success' => false, 'message' => 'Invalid setup token']);
            exit;
        }

        // Get credentials from environment or request
        $username = $data['username'] ?? $_ENV['INITIAL_ADMIN_USERNAME'] ?? '';
        $password = $data['password'] ?? $_ENV['INITIAL_ADMIN_PASSWORD'] ?? '';
        $email = $data['email'] ?? '';

        if (!$username || !$password) {
            echo json_encode(['success' => false, 'message' => 'Username and password are required']);
            exit;
        }

        // Create admin user
        $adminAuth = AdminAuth::getInstance();
        $result = $adminAuth->createUser($username, $password, $email);
        
        if ($result['success']) {
            // Log the setup completion
            $db = Database::getInstance();
            $db->logSecurityEvent('admin_setup_completed', [
                'username' => $username,
                'ip' => $_SERVER['REMOTE_ADDR'],
                'user_agent' => $_SERVER['HTTP_USER_AGENT']
            ]);
            
            echo json_encode([
                'success' => true, 
                'message' => 'Admin user created successfully. Please remove the setup credentials from your .env file for security.'
            ]);
        } else {
            echo json_encode($result);
        }

    } catch (Exception $e) {
        error_log("Setup error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Setup failed']);
    }
    exit;
}

// Show setup form if GET request
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Setup - Stolen Phone Database</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            margin: 0;
            padding: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .setup-container {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 400px;
        }
        .setup-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        .setup-header h1 {
            color: #333;
            margin-bottom: 0.5rem;
        }
        .setup-header p {
            color: #666;
            font-size: 0.9rem;
        }
        .form-group {
            margin-bottom: 1rem;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #333;
            font-weight: 500;
        }
        .form-group input {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
            box-sizing: border-box;
        }
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 2px rgba(102, 126, 234, 0.2);
        }
        .setup-button {
            width: 100%;
            padding: 0.75rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: transform 0.2s;
        }
        .setup-button:hover {
            transform: translateY(-1px);
        }
        .setup-button:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        .message {
            padding: 0.75rem;
            border-radius: 5px;
            margin-bottom: 1rem;
            font-weight: 500;
        }
        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .security-note {
            background: #fff3cd;
            color: #856404;
            padding: 1rem;
            border-radius: 5px;
            margin-top: 1rem;
            font-size: 0.9rem;
            border: 1px solid #ffeaa7;
        }
    </style>
</head>
<body>
    <div class="setup-container">
        <div class="setup-header">
            <h1>🔐 Admin Setup</h1>
            <p>Create your secure admin account</p>
        </div>

        <div id="message"></div>

        <?php if (isSetupAllowed()): ?>
        <form id="setup-form">
            <div class="form-group">
                <label for="setup_token">Setup Token</label>
                <input type="password" id="setup_token" name="setup_token" required 
                       placeholder="Enter setup token from .env file">
            </div>
            
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required 
                       placeholder="Enter admin username" value="dabouz444">
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required 
                       placeholder="Enter secure password" value="dabouz2019">
            </div>
            
            <div class="form-group">
                <label for="email">Email (Optional)</label>
                <input type="email" id="email" name="email" 
                       placeholder="Enter email address">
            </div>
            
            <button type="submit" class="setup-button">Create Admin Account</button>
        </form>

        <div class="security-note">
            <strong>Security Note:</strong> After setup, remove the INITIAL_ADMIN_USERNAME, INITIAL_ADMIN_PASSWORD, and ADMIN_SETUP_TOKEN from your .env file for security.
        </div>
        <?php else: ?>
        <div class="message error">
            Setup has already been completed. Admin users already exist in the system.
        </div>
        <p style="text-align: center; margin-top: 2rem;">
            <a href="/admin.html" style="color: #667eea; text-decoration: none;">Go to Admin Login</a>
        </p>
        <?php endif; ?>
    </div>

    <script>
        document.getElementById('setup-form')?.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const button = document.querySelector('.setup-button');
            const messageDiv = document.getElementById('message');
            
            button.disabled = true;
            button.textContent = 'Creating Account...';
            
            const formData = new FormData(this);
            const data = Object.fromEntries(formData.entries());
            
            try {
                const response = await fetch('setup.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(data)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    messageDiv.innerHTML = `<div class="message success">${result.message}</div>`;
                    this.style.display = 'none';
                    setTimeout(() => {
                        window.location.href = '/admin.html';
                    }, 3000);
                } else {
                    messageDiv.innerHTML = `<div class="message error">${result.message}</div>`;
                }
            } catch (error) {
                messageDiv.innerHTML = `<div class="message error">Setup failed. Please try again.</div>`;
            }
            
            button.disabled = false;
            button.textContent = 'Create Admin Account';
        });
    </script>
</body>
</html>
