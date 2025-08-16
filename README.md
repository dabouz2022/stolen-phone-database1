# Stolen Phone Database

A comprehensive web application for managing and tracking stolen phone reports. Built with Node.js, Express, SQLite, and modern web technologies.

## 🚀 Features

- **Phone Report Submission**: Submit stolen phone reports with detailed information
- **IMEI Verification**: Check if a phone has been reported as stolen
- **Admin Panel**: Complete administrative interface for managing reports
- **Analytics Dashboard**: Real-time statistics and insights
- **Secure Authentication**: Admin login with session management
- **File Upload**: Support for phone box photos
- **Responsive Design**: Works on desktop and mobile devices

## 🛠️ Tech Stack

- **Backend**: Node.js, Express.js
- **Database**: SQLite3
- **Frontend**: HTML5, CSS3, JavaScript (Vanilla)
- **Authentication**: bcrypt, JWT-like sessions
- **Containerization**: Docker, Docker Compose
- **Reverse Proxy**: Nginx
- **Security**: CORS, input validation, rate limiting

## 📋 Prerequisites

- Node.js 18+ 
- npm or yarn
- Docker (for production deployment)
- Git

## 🚀 Quick Start

### Local Development

1. **Clone the repository**
   ```bash
   git clone https://github.com/YOUR_USERNAME/stolen-phone-database.git
   cd stolen-phone-database
   ```

2. **Install dependencies**
   ```bash
   npm install
   ```

3. **Start the development server**
   ```bash
   npm start
   ```

4. **Access the application**
   - Main app: http://localhost:3000
   - Admin panel: http://localhost:3000/admin

### Default Admin Credentials
- **Username**: dabouz444
- **Password**: dabouz2019

⚠️ **Important**: Change these credentials after first login!

## 🐳 Docker Deployment

### Development
```bash
docker-compose up --build
```

### Production
```bash
# Create .env file
cp env.example .env
# Edit .env with your production values

# Deploy
./deploy.sh
```

## ☁️ DigitalOcean Deployment

### Option 1: App Platform (Recommended)
1. Push code to GitHub
2. Connect repository to DigitalOcean App Platform
3. Set environment variables in dashboard
4. Deploy automatically

### Option 2: Droplet
1. Create DigitalOcean Droplet with Docker
2. Clone repository to server
3. Create `.env` file with production values
4. Run `./deploy.sh`

## 📁 Project Structure

```
stolen-phone-database/
├── server.js                 # Main Express server
├── package.json             # Dependencies and scripts
├── stolen_phones.db         # SQLite database
├── index.html              # Main application interface
├── admin.html              # Admin panel interface
├── styles.css              # Application styles
├── Dockerfile              # Docker configuration
├── docker-compose.yml      # Development Docker setup
├── docker-compose.prod.yml # Production Docker setup
├── nginx.conf              # Nginx reverse proxy config
├── deploy.sh               # Deployment script
├── env.example             # Environment variables template
├── .gitignore              # Git ignore rules
└── README.md               # This file
```

## 🔧 Configuration

### Environment Variables

Create a `.env` file based on `env.example`:

```bash
# Application Configuration
NODE_ENV=production
PORT=3000

# CORS Configuration
ALLOWED_ORIGINS=https://yourdomain.com,https://www.yourdomain.com

# Security Configuration
BCRYPT_ROUNDS=12
SESSION_TIMEOUT=1800
MAX_LOGIN_ATTEMPTS=5
LOCKOUT_TIME=1800

# Database Configuration
DB_PATH=./stolen_phones.db

# Admin Default Credentials
DEFAULT_ADMIN_USERNAME=dabouz444
DEFAULT_ADMIN_PASSWORD=dabouz2019
```

## 📊 API Endpoints

### Public Endpoints
- `POST /api/submit-report` - Submit stolen phone report
- `POST /api/check-imei` - Check if IMEI is reported stolen
- `GET /check-all-stolen` - Get all approved stolen phone reports
- `GET /api/health` - Health check endpoint

### Admin Endpoints (Authentication Required)
- `POST /api/admin/login` - Admin login
- `POST /api/admin/logout` - Admin logout
- `GET /api/admin/pending-reports` - Get pending reports
- `GET /api/admin/approved-reports` - Get approved reports
- `POST /api/admin/approve-report/:id` - Approve a report
- `POST /api/admin/reject-report/:id` - Reject a report
- `DELETE /api/admin/delete-phone/:id` - Delete a phone record
- `POST /api/admin/empty-database` - Clear all data
- `GET /api/analytics/*` - Analytics endpoints

## 🔒 Security Features

- **CORS Protection**: Configurable allowed origins
- **Input Validation**: Server-side validation for all inputs
- **SQL Injection Protection**: Parameterized queries
- **Session Management**: Secure admin sessions with timeouts
- **Rate Limiting**: Login attempt restrictions
- **Password Hashing**: bcrypt with configurable rounds
- **HTTPS Support**: SSL/TLS encryption

## 📈 Monitoring & Maintenance

### Health Check
```bash
curl https://yourdomain.com/api/health
```

### View Logs
```bash
# Application logs
docker-compose -f docker-compose.prod.yml logs -f app

# System monitoring
docker stats
df -h
```

### Database Backup
```bash
cp stolen_phones.db backup_$(date +%Y%m%d_%H%M%S).db
```

## 🚨 Troubleshooting

### Common Issues

1. **Application won't start**
   ```bash
   docker-compose -f docker-compose.prod.yml logs app
   ```

2. **Database issues**
   ```bash
   ls -la stolen_phones.db
   chmod 644 stolen_phones.db
   ```

3. **Permission issues**
   ```bash
   sudo chown -R $USER:$USER .
   ```

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## 📄 License

This project is licensed under the ISC License.

## 🆘 Support

If you encounter any issues:

1. Check the logs: `docker-compose -f docker-compose.prod.yml logs -f`
2. Verify your configuration files
3. Ensure all dependencies are installed
4. Check the troubleshooting section above

## 🎯 Roadmap

- [ ] PostgreSQL migration for better performance
- [ ] User registration and authentication
- [ ] Email notifications
- [ ] Mobile app (React Native)
- [ ] API rate limiting
- [ ] Advanced analytics
- [ ] Multi-language support
- [ ] Automated backups
- [ ] Real-time notifications

---

**Built with ❤️ for better phone security**
