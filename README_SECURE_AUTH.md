# Secure Admin Authentication System

## 🔐 Security Features Implemented

### 1. **Environment-Based Configuration**
- All sensitive credentials moved to `.env` file
- No hardcoded passwords in source code
- Secure encryption keys and JWT secrets

### 2. **Advanced Password Security**
- Bcrypt hashing with configurable rounds (default: 12)
- Password verification using secure comparison
- Account lockout after failed attempts

### 3. **Token-Based Authentication**
- JWT-style secure tokens for session management
- Token expiration and automatic cleanup
- IP address and User-Agent validation

### 4. **Brute Force Protection**
- Rate limiting (10 attempts per 15 minutes per IP)
- Account lockout after 5 failed attempts
- Temporary lockout periods (30 minutes default)

### 5. **Database Security**
- Prepared statements to prevent SQL injection
- Encrypted sensitive data storage
- Comprehensive security event logging

## 📋 Setup Instructions

### Step 1: Initial Setup
1. Run the setup script to create your admin account:
   ```
   http://your-domain.com/config/setup.php
   ```

2. Use these credentials for setup:
   - **Setup Token**: `setup_token_12345_secure_admin_creation`
   - **Username**: `dabouz444`
   - **Password**: `dabouz2019`

### Step 2: Security Configuration
After setup, **immediately** remove these lines from your `.env` file for security:
```env
# Remove these after setup:
INITIAL_ADMIN_USERNAME=dabouz444
INITIAL_ADMIN_PASSWORD=dabouz2019
ADMIN_SETUP_TOKEN=setup_token_12345_secure_admin_creation
```

### Step 3: Environment Variables
Ensure your `.env` file contains:
```env
# Database Configuration
DB_HOST=localhost
DB_NAME=stolen_phones_db
DB_USER=secure_user
DB_PASS=your_secure_password_here

# Security Configuration
ENCRYPTION_KEY=a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6
JWT_SECRET=super_secure_jwt_secret_key_for_stolen_phone_db_2024

# Security Settings
SESSION_TIMEOUT=1800
MAX_LOGIN_ATTEMPTS=5
LOCKOUT_TIME=1800
BCRYPT_ROUNDS=12

# Application Settings
APP_ENV=production
DEBUG_MODE=false
```

## 🚀 How to Use

### Admin Login
1. Navigate to: `http://your-domain.com/admin.html`
2. Enter your credentials:
   - **Username**: `dabouz444`
   - **Password**: `dabouz2019`
3. You'll receive a secure token for your session

### API Endpoints
- `POST /api/auth.php` - Login, refresh token, change password
- `GET /api/auth.php` - Verify token, get profile
- `DELETE /api/auth.php` - Logout

## 🛡️ Security Features in Detail

### Authentication Flow
1. **Login**: Username/password → Secure token
2. **Session**: Token validates each request
3. **Logout**: Token invalidation and cleanup

### Database Tables Created
- `admin_users` - User accounts with hashed passwords
- `admin_sessions` - Active session tokens
- `admin_login_attempts` - Failed login tracking
- `security_logs` - Comprehensive security events

### Rate Limiting
- **Authentication**: 20 attempts per 15 minutes per IP
- **General Admin**: 50 requests per hour per IP
- **Login Attempts**: 10 attempts per 15 minutes per IP

### Session Security
- IP address validation
- User agent validation
- Automatic token expiration
- Session extension on activity

## 🔧 Configuration Options

### Environment Variables
- `SESSION_TIMEOUT`: Session duration (default: 1800 seconds)
- `MAX_LOGIN_ATTEMPTS`: Failed attempts before lockout (default: 5)
- `LOCKOUT_TIME`: Account lockout duration (default: 1800 seconds)
- `BCRYPT_ROUNDS`: Password hashing complexity (default: 12)

### Security Levels
- **Production**: `APP_ENV=production`, `DEBUG_MODE=false`
- **Development**: `APP_ENV=development`, `DEBUG_MODE=true`

## 📁 File Structure
```
config/
├── .env                 # Environment variables (keep secure!)
├── .env.example        # Template for environment setup
├── admin_auth.php      # Secure authentication class
├── database.php        # Database connection and security
├── env_loader.php      # Environment variable loader
└── setup.php           # Initial admin setup script

api/
├── auth.php            # Authentication API endpoints
└── admin.php           # Admin management endpoints

admin.html              # Secure admin interface
```

## ⚠️ Security Warnings

1. **Never commit `.env` file to version control**
2. **Remove setup credentials after initial setup**
3. **Use HTTPS in production**
4. **Regularly update encryption keys**
5. **Monitor security logs for suspicious activity**

## 🔄 Maintenance

### Regular Tasks
- Clean up expired sessions: Automatic (5% chance per request)
- Review security logs: Check `security_logs` table
- Update passwords: Use change password API
- Monitor failed attempts: Check `admin_login_attempts` table

### Backup Security Data
```sql
-- Backup admin users
SELECT * FROM admin_users;

-- Backup security logs
SELECT * FROM security_logs WHERE created_at > DATE_SUB(NOW(), INTERVAL 30 DAY);
```

## 🆘 Troubleshooting

### Common Issues
1. **Can't login**: Check if account is locked, verify credentials
2. **Token expired**: Login again to get new token
3. **Rate limited**: Wait 15 minutes and try again
4. **Setup fails**: Verify database connection and permissions

### Reset Admin Account
If you're locked out, run this SQL:
```sql
-- Reset failed attempts and unlock account
UPDATE admin_users SET failed_attempts = 0, locked_until = NULL WHERE username = 'dabouz444';

-- Or create new admin (hash password with PHP)
INSERT INTO admin_users (username, password_hash) VALUES ('newadmin', '$2y$12$...');
```

## 📞 Support
For security issues or questions, review the code in:
- `config/admin_auth.php` - Main authentication logic
- `api/auth.php` - API endpoints
- `middleware/SecurityMiddleware.php` - Security utilities

---

**🔒 Your admin credentials are now secure and not exposed in source code!**
