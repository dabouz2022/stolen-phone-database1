const express = require('express');
const bodyParser = require('body-parser');
const cors = require('cors');
const sqlite3 = require('sqlite3').verbose();
const bcrypt = require('bcrypt');
const crypto = require('crypto');
const app = express();

// Environment configuration
const port = process.env.PORT || 3000;
const NODE_ENV = process.env.NODE_ENV || 'development';

// Security configuration
const BCRYPT_ROUNDS = 12;
const SESSION_TIMEOUT = 1800; // 30 minutes
const MAX_LOGIN_ATTEMPTS = 5;
const LOCKOUT_TIME = 1800; // 30 minutes

// CORS configuration for production
const corsOptions = {
    origin: NODE_ENV === 'production' 
        ? process.env.ALLOWED_ORIGINS?.split(',') || ['https://yourdomain.com']
        : '*',
    methods: ['GET', 'POST', 'DELETE', 'PUT', 'PATCH', 'OPTIONS'],
    allowedHeaders: ['Content-Type', 'Authorization'],
    credentials: true
};

app.use(cors(corsOptions));
app.use(bodyParser.json({ limit: '50mb' })); // Increase payload limit to handle base64 images

// Production logging
const log = (message, level = 'info') => {
    if (NODE_ENV === 'production') {
        console.log(`[${new Date().toISOString()}] [${level.toUpperCase()}] ${message}`);
    } else {
        console.log(message);
    }
};

// Connect to SQLite database
const db = new sqlite3.Database('./stolen_phones.db', (err) => {
    if (err) {
        log('Database connection error: ' + err.message, 'error');
        process.exit(1);
    } else {
        log('Connected to the database.');
        createTable(); // Call createTable() here, after the database connection is established
    }
});

// Create the phones table if it doesn't exist
function createTable() {
    db.run(`CREATE TABLE IF NOT EXISTS phones (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        phone TEXT NOT NULL,
        imei TEXT NOT NULL UNIQUE,
        color TEXT NOT NULL,
        model TEXT NOT NULL,
        brand TEXT NOT NULL,
        boxPhoto TEXT,
        reportDate DATETIME DEFAULT CURRENT_TIMESTAMP,
        status TEXT NOT NULL DEFAULT 'pending',
        CHECK (status IN ('pending', 'approved', 'rejected'))
    )`, (err) => {
        if (err) {
            log('Error creating table: ' + err.message, 'error');
        } else {
            log('Phones table created or already exists.');
        }
    });

    // Create admin users table
    db.run(`CREATE TABLE IF NOT EXISTS admin_users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE NOT NULL,
        password_hash TEXT NOT NULL,
        email TEXT,
        is_active INTEGER DEFAULT 1,
        last_login DATETIME,
        failed_attempts INTEGER DEFAULT 0,
        locked_until DATETIME,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )`, (err) => {
        if (err) {
            log('Error creating admin_users table: ' + err.message, 'error');
        } else {
            log('Admin users table created or already exists.');
            createDefaultAdmin();
        }
    });

    // Create admin sessions table
    db.run(`CREATE TABLE IF NOT EXISTS admin_sessions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        token TEXT UNIQUE NOT NULL,
        ip_address TEXT,
        user_agent TEXT,
        expires_at DATETIME NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES admin_users(id) ON DELETE CASCADE
    )`, (err) => {
        if (err) {
            log('Error creating admin_sessions table: ' + err.message, 'error');
        } else {
            log('Admin sessions table created or already exists.');
        }
    });

    // Create login attempts table
    db.run(`CREATE TABLE IF NOT EXISTS admin_login_attempts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT,
        ip_address TEXT,
        user_agent TEXT,
        success INTEGER DEFAULT 0,
        attempt_time DATETIME DEFAULT CURRENT_TIMESTAMP
    )`, (err) => {
        if (err) {
            log('Error creating admin_login_attempts table: ' + err.message, 'error');
        } else {
            log('Admin login attempts table created or already exists.');
        }
    });
}

// Create default admin user
async function createDefaultAdmin() {
    db.get('SELECT COUNT(*) as count FROM admin_users', async (err, row) => {
        if (err) {
            log('Error checking admin users: ' + err.message, 'error');
            return;
        }
        
        if (row.count === 0) {
            try {
                const hashedPassword = await bcrypt.hash('dabouz2019', BCRYPT_ROUNDS);
                db.run(
                    'INSERT INTO admin_users (username, password_hash) VALUES (?, ?)',
                    ['dabouz444', hashedPassword],
                    (err) => {
                        if (err) {
                            log('Error creating default admin: ' + err.message, 'error');
                        } else {
                            log('✅ Default admin user created successfully!');
                            log('Username: dabouz444');
                            log('Password: dabouz2019');
                        }
                    }
                );
            } catch (error) {
                log('Error hashing password: ' + error.message, 'error');
            }
        }
    });
}

// Endpoint to report a stolen phone
app.post('/report-stolen', (req, res) => {
    const { name, phone, imei, color, model, brand, boxPhoto } = req.body;

    // Basic validation
    if (!name || !phone || !imei || !color || !model || !brand) {
        return res.status(400).json({ success: false, message: 'All fields are required.' });
    }
    if (!/^\d+$/.test(phone)) {
        return res.status(400).json({ success: false, message: 'Phone Number must contain only numbers.' });
    }
    if (!/^\d+$/.test(imei)) {
        return res.status(400).json({ success: false, message: 'IMEI must contain only numbers.' });
    }
    if (imei.length < 14 || imei.length > 16) {
        return res.status(400).json({ success: false, message: 'IMEI must be 14 to 16 digits long.' });
    }

    // Check if IMEI exists
    db.get(
        `SELECT status FROM phones WHERE imei = ?`,
        [imei],
        (err, row) => {
            if (err) {
                log('Error checking IMEI: ' + err.message, 'error');
                return res.status(500).json({ success: false, message: 'Error checking IMEI.' });
            }
            
            if (row) {
                const message = row.status === 'approved' 
                    ? 'This IMEI is already registered as stolen.'
                    : 'This IMEI is already pending approval.';
                return res.status(400).json({ success: false, message });
            }

            // Insert new report
            const stmt = db.prepare(
                `INSERT INTO phones (name, phone, imei, color, model, brand, boxPhoto, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')`
            );
            
            stmt.run(name, phone, imei, color, model, brand, boxPhoto, (err) => {
                if (err) {
                    log('Error inserting data: ' + err.message, 'error');
                    return res.status(500).json({ success: false, message: 'Failed to submit report.' });
                }
                log('Report submitted successfully, pending approval.');
                res.json({ 
                    success: true, 
                    message: 'Report submitted successfully and pending admin approval.' 
                });
            });
            stmt.finalize();
        }
    );
});

// Endpoint to check IMEI (only checks approved phones)
app.get('/check-imei', (req, res) => {
    const imei = req.query.imei;
    if (!/^\d+$/.test(imei)) {
        return res.status(400).json({ success: false, message: 'IMEI must contain only numbers.' });
    }
    if (imei.length < 14 || imei.length > 16) {
        return res.status(400).json({ success: false, message: 'IMEI must be 14 to 16 digits long.' });
    }

    db.get(
        `SELECT * FROM phones WHERE imei = ? AND status = 'approved'`,
        [imei],
        (err, row) => {
            if (err) {
                log('Error querying database: ' + err.message, 'error');
                return res.status(500).json({ success: false, message: 'Error checking IMEI.' });
            }
            if (row) {
                log('Phone found.');
                res.json({ success: true, found: true, phone: row });
            } else {
                log('Phone not found.');
                res.json({ success: true, found: false });
            }
        }
    );
});

// Authentication middleware
function authenticateToken(req, res, next) {
    const authHeader = req.headers['authorization'];
    const token = authHeader && authHeader.split(' ')[1];

    if (!token) {
        return res.status(401).json({ error: 'Access token required' });
    }

    // Verify token in database
    db.get(
        'SELECT s.*, u.username, u.is_active FROM admin_sessions s JOIN admin_users u ON s.user_id = u.id WHERE s.token = ? AND s.expires_at > datetime("now") AND u.is_active = 1',
        [token],
        (err, session) => {
            if (err) {
                log('Token verification error: ' + err.message, 'error');
                return res.status(500).json({ error: 'Token verification failed' });
            }

            if (!session) {
                return res.status(401).json({ error: 'Invalid or expired token' });
            }

            // Extend session
            const newExpiry = new Date(Date.now() + SESSION_TIMEOUT * 1000).toISOString();
            db.run('UPDATE admin_sessions SET expires_at = ? WHERE token = ?', [newExpiry, token]);

            req.user = { user_id: session.user_id, username: session.username };
            next();
        }
    );
}

// Rate limiting helper
const rateLimitMap = new Map();

function rateLimit(ip, maxAttempts = 10, windowMs = 15 * 60 * 1000) {
    const now = Date.now();
    const windowStart = now - windowMs;
    
    if (!rateLimitMap.has(ip)) {
        rateLimitMap.set(ip, []);
    }
    
    const attempts = rateLimitMap.get(ip);
    // Remove old attempts
    const recentAttempts = attempts.filter(time => time > windowStart);
    rateLimitMap.set(ip, recentAttempts);
    
    if (recentAttempts.length >= maxAttempts) {
        return false;
    }
    
    recentAttempts.push(now);
    return true;
}

// Authentication endpoints
app.post('/api/auth', async (req, res) => {
    const { action, username, password } = req.body;
    const clientIP = req.ip || req.connection.remoteAddress;

    if (action === 'login') {
        // Rate limiting
        if (!rateLimit(clientIP, 10, 15 * 60 * 1000)) {
            return res.status(429).json({ error: 'Too many login attempts. Please try again later.' });
        }

        if (!username || !password) {
            return res.status(400).json({ error: 'Username and password are required' });
        }

        try {
            // Get user from database
            db.get(
                'SELECT * FROM admin_users WHERE username = ? AND is_active = 1',
                [username],
                async (err, user) => {
                    if (err) {
                        log('Login error: ' + err.message, 'error');
                        return res.status(500).json({ error: 'Login failed' });
                    }

                    if (!user) {
                        // Log failed attempt
                        db.run(
                            'INSERT INTO admin_login_attempts (username, ip_address, user_agent, success) VALUES (?, ?, ?, 0)',
                            [username, clientIP, req.headers['user-agent']]
                        );
                        return res.status(401).json({ error: 'Invalid credentials' });
                    }

                    // Check if account is locked
                    if (user.locked_until && new Date(user.locked_until) > new Date()) {
                        return res.status(401).json({ error: 'Account is temporarily locked' });
                    }

                    // Verify password
                    const passwordMatch = await bcrypt.compare(password, user.password_hash);
                    
                    if (!passwordMatch) {
                        // Increment failed attempts
                        const newFailedAttempts = user.failed_attempts + 1;
                        let lockUntil = null;
                        
                        if (newFailedAttempts >= MAX_LOGIN_ATTEMPTS) {
                            lockUntil = new Date(Date.now() + LOCKOUT_TIME * 1000).toISOString();
                        }

                        db.run(
                            'UPDATE admin_users SET failed_attempts = ?, locked_until = ? WHERE id = ?',
                            [newFailedAttempts, lockUntil, user.id]
                        );

                        // Log failed attempt
                        db.run(
                            'INSERT INTO admin_login_attempts (username, ip_address, user_agent, success) VALUES (?, ?, ?, 0)',
                            [username, clientIP, req.headers['user-agent']]
                        );

                        return res.status(401).json({ error: 'Invalid credentials' });
                    }

                    // Reset failed attempts and create session
                    db.run(
                        'UPDATE admin_users SET failed_attempts = 0, locked_until = NULL, last_login = datetime("now") WHERE id = ?',
                        [user.id]
                    );

                    // Create session token
                    const token = crypto.randomBytes(32).toString('hex');
                    const expiresAt = new Date(Date.now() + SESSION_TIMEOUT * 1000).toISOString();

                    db.run(
                        'INSERT INTO admin_sessions (user_id, token, ip_address, user_agent, expires_at) VALUES (?, ?, ?, ?, ?)',
                        [user.id, token, clientIP, req.headers['user-agent'], expiresAt],
                        (err) => {
                            if (err) {
                                log('Session creation error: ' + err.message, 'error');
                                return res.status(500).json({ error: 'Login failed' });
                            }

                            // Log successful attempt
                            db.run(
                                'INSERT INTO admin_login_attempts (username, ip_address, user_agent, success) VALUES (?, ?, ?, 1)',
                                [username, clientIP, req.headers['user-agent']]
                            );

                            res.json({
                                success: true,
                                token: token,
                                user: {
                                    id: user.id,
                                    username: user.username,
                                    email: user.email
                                },
                                expires_in: SESSION_TIMEOUT
                            });
                        }
                    );
                }
            );
        } catch (error) {
            log('Login error: ' + error.message, 'error');
            res.status(500).json({ error: 'Login failed' });
        }
    }
});

app.get('/api/auth', (req, res) => {
    const { action } = req.query;
    
    if (action === 'verify') {
        const authHeader = req.headers['authorization'];
        const token = authHeader && authHeader.split(' ')[1];

        if (!token) {
            return res.status(401).json({ valid: false, error: 'No token provided' });
        }

        db.get(
            'SELECT s.*, u.username FROM admin_sessions s JOIN admin_users u ON s.user_id = u.id WHERE s.token = ? AND s.expires_at > datetime("now") AND u.is_active = 1',
            [token],
            (err, session) => {
                if (err) {
                    log('Token verification error: ' + err.message, 'error');
                    return res.status(500).json({ valid: false, error: 'Token verification failed' });
                }

                if (!session) {
                    return res.json({ valid: false, error: 'Invalid or expired token' });
                }

                // Extend session
                const newExpiry = new Date(Date.now() + SESSION_TIMEOUT * 1000).toISOString();
                db.run('UPDATE admin_sessions SET expires_at = ? WHERE token = ?', [newExpiry, token]);

                res.json({
                    valid: true,
                    user: {
                        user_id: session.user_id,
                        username: session.username
                    }
                });
            }
        );
    }
});

app.delete('/api/auth', (req, res) => {
    const { action } = req.body;
    
    if (action === 'logout') {
        const authHeader = req.headers['authorization'];
        const token = authHeader && authHeader.split(' ')[1];

        if (token) {
            db.run('DELETE FROM admin_sessions WHERE token = ?', [token]);
        }

        res.json({ success: true, message: 'Logged out successfully' });
    }
});

// Admin endpoints (protected)
app.get('/admin/pending-reports', authenticateToken, (req, res) => {
    db.all(
        `SELECT * FROM phones WHERE status = 'pending' ORDER BY reportDate DESC`,
        [],
        (err, rows) => {
            if (err) {
                log('Error fetching pending reports: ' + err.message, 'error');
                return res.status(500).json({ success: false, message: 'Error fetching pending reports.' });
            }
            res.json({ success: true, reports: rows });
        }
    );
});

app.get('/check-all-stolen', (req, res) => {
    log('Fetching all stolen phones...');
    db.all(
        `SELECT * FROM phones WHERE status = 'approved' ORDER BY reportDate DESC`,
        [],
        (err, rows) => {
            if (err) {
                log('Error fetching all stolen phones: ' + err.message, 'error');
                return res.status(500).json({ 
                    success: false, 
                    message: 'Error fetching stolen phones.' 
                });
            }
            log('Found phones:', rows);
            res.json({ 
                success: true, 
                phones: rows 
            });
        }
    );
});

app.post('/admin/approve-report/:id', (req, res) => {
    const reportId = req.params.id;
    
    db.run(
        `UPDATE phones SET status = 'approved' WHERE id = ?`,
        [reportId],
        function(err) {
            if (err) {
                log('Error approving report: ' + err.message, 'error');
                return res.status(500).json({ 
                    success: false, 
                    message: 'Error approving report.' 
                });
            }
            res.json({ 
                success: true, 
                message: 'Report approved successfully.' 
            });
        }
    );
});

app.post('/admin/reject-report/:id', (req, res) => {
    const reportId = req.params.id;
    
    db.run(
        `UPDATE phones SET status = 'rejected' WHERE id = ?`,
        [reportId],
        function(err) {
            if (err) {
                log('Error rejecting report: ' + err.message, 'error');
                return res.status(500).json({ 
                    success: false, 
                    message: 'Error rejecting report.' 
                });
            }
            res.json({ 
                success: true, 
                message: 'Report rejected successfully.' 
            });
        }
    );
});

app.delete('/admin/delete-phone/:id', (req, res) => {
    const phoneId = req.params.id;
    
    db.run(
        `DELETE FROM phones WHERE id = ?`,
        [phoneId],
        function(err) {
            if (err) {
                log('Error deleting phone: ' + err.message, 'error');
                return res.status(500).json({ 
                    success: false, 
                    message: 'Error deleting phone from database.' 
                });
            }
            res.json({ 
                success: true, 
                message: 'Phone deleted successfully.' 
            });
        }
    );
});

app.post('/admin/empty-database', (req, res) => {
    db.run(
        `DELETE FROM phones`,
        function(err) {
            if (err) {
                log('Error emptying database: ' + err.message, 'error');
                return res.status(500).json({ 
                    success: false, 
                    message: 'Error emptying database.' 
                });
            }
            res.json({ 
                success: true, 
                message: 'Database emptied successfully.' 
            });
        }
    );
});

// Analytics API Endpoints
app.get('/api/analytics/overview', (req, res) => {
    const queries = [
        'SELECT COUNT(*) as total FROM phones',
        'SELECT COUNT(*) as pending FROM phones WHERE status = "pending"',
        'SELECT COUNT(*) as approved FROM phones WHERE status = "approved"',
        'SELECT COUNT(*) as today FROM phones WHERE DATE(reportDate) = DATE("now")',
        'SELECT COUNT(*) as week FROM phones WHERE reportDate >= DATE("now", "-7 days")'
    ];

    Promise.all(queries.map(query => {
        return new Promise((resolve, reject) => {
            db.get(query, (err, row) => {
                if (err) reject(err);
                else resolve(row);
            });
        });
    }))
    .then(results => {
        const [total, pending, approved, today, week] = results;
        const recoveryRate = total.total > 0 ? Math.round((approved.approved / total.total) * 100) : 0;
        
        res.json({
            totalReports: total.total,
            pendingReports: pending.pending,
            approvedReports: approved.approved,
            recoveryRate: recoveryRate,
            newToday: today.today,
            reportsChange: week.week > 0 ? Math.round((week.week / total.total) * 100) : 0,
            approvedChange: approved.approved > 0 ? Math.round((approved.approved / total.total) * 100) : 0
        });
    })
    .catch(err => {
        log('Error fetching analytics overview: ' + err.message, 'error');
        res.status(500).json({ error: 'Error fetching analytics data' });
    });
});

app.get('/api/analytics/monthly', (req, res) => {
    const query = `
        SELECT 
            strftime('%m', reportDate) as month,
            COUNT(*) as count
        FROM phones 
        WHERE reportDate >= DATE('now', '-6 months')
        GROUP BY strftime('%m', reportDate)
        ORDER BY month
    `;

    db.all(query, (err, rows) => {
        if (err) {
            log('Error fetching monthly analytics: ' + err.message, 'error');
            return res.status(500).json({ error: 'Error fetching monthly data' });
        }

        const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        const data = Array(6).fill(0);
        
        rows.forEach(row => {
            const monthIndex = parseInt(row.month) - 1;
            if (monthIndex >= 0 && monthIndex < 6) {
                data[monthIndex] = row.count;
            }
        });

        res.json({
            labels: monthNames.slice(0, 6),
            values: data
        });
    });
});

app.get('/api/analytics/brands', (req, res) => {
    const query = `
        SELECT 
            brand,
            COUNT(*) as count
        FROM phones 
        GROUP BY brand 
        ORDER BY count DESC 
        LIMIT 5
    `;

    db.all(query, (err, rows) => {
        if (err) {
            log('Error fetching brand analytics: ' + err.message, 'error');
            return res.status(500).json({ error: 'Error fetching brand data' });
        }

        const labels = rows.map(row => row.brand);
        const values = rows.map(row => row.count);

        res.json({
            labels: labels,
            values: values
        });
    });
});

app.get('/api/analytics/recent', (req, res) => {
    const query = `
        SELECT 
            name,
            brand,
            model,
            reportDate,
            status
        FROM phones 
        ORDER BY reportDate DESC 
        LIMIT 10
    `;

    db.all(query, (err, rows) => {
        if (err) {
            log('Error fetching recent activity: ' + err.message, 'error');
            return res.status(500).json({ error: 'Error fetching recent data' });
        }

        const activities = rows.map(row => {
            const timeAgo = getTimeAgo(new Date(row.reportDate));
            const action = row.status === 'pending' ? 'New report submitted' : 'Report approved';
            return {
                description: `${action}: ${row.brand} ${row.model}`,
                time: timeAgo
            };
        });

        res.json({
            activities: activities
        });
    });
});

function getTimeAgo(date) {
    const now = new Date();
    const diffInSeconds = Math.floor((now - date) / 1000);
    
    if (diffInSeconds < 60) return `${diffInSeconds} seconds ago`;
    if (diffInSeconds < 3600) return `${Math.floor(diffInSeconds / 60)} minutes ago`;
    if (diffInSeconds < 86400) return `${Math.floor(diffInSeconds / 3600)} hours ago`;
    return `${Math.floor(diffInSeconds / 86400)} days ago`;
}

// Health check endpoint
app.get('/api/health', (req, res) => {
    res.status(200).json({ 
        status: 'healthy', 
        timestamp: new Date().toISOString(),
        uptime: process.uptime()
    });
});

// Serve static files from the current directory
app.use(express.static('./'));

// Admin panel routes
app.get('/admin', (req, res) => {
    res.sendFile('index.html', { root: './' });
});

app.get('/admin.html', (req, res) => {
    res.sendFile('admin.html', { root: './' });
});

// Error handling middleware
app.use((err, req, res, next) => {
    log(`Error: ${err.message}`, 'error');
    res.status(500).json({ error: 'Internal server error' });
});

// 404 handler
app.use((req, res) => {
    res.status(404).json({ error: 'Not found' });
});

// Graceful shutdown handling
const server = app.listen(port, () => {
    log(`Server is running on port ${port}`);
});

// Handle graceful shutdown
process.on('SIGTERM', () => {
    log('SIGTERM received, shutting down gracefully');
    server.close(() => {
        log('Process terminated');
        db.close((err) => {
            if (err) {
                log('Error closing database: ' + err.message, 'error');
            } else {
                log('Database connection closed');
            }
            process.exit(0);
        });
    });
});

process.on('SIGINT', () => {
    log('SIGINT received, shutting down gracefully');
    server.close(() => {
        log('Process terminated');
        db.close((err) => {
            if (err) {
                log('Error closing database: ' + err.message, 'error');
            } else {
                log('Database connection closed');
            }
            process.exit(0);
        });
    });
});

// Handle uncaught exceptions
process.on('uncaughtException', (err) => {
    log('Uncaught Exception: ' + err.message, 'error');
    log(err.stack, 'error');
    process.exit(1);
});

process.on('unhandledRejection', (reason, promise) => {
    log('Unhandled Rejection at: ' + promise + ' reason: ' + reason, 'error');
    process.exit(1);
});