# DigitalOcean Deployment Guide

This guide will help you deploy your Stolen Phone Database application to DigitalOcean.

## Prerequisites

1. **DigitalOcean Account**: Sign up at [digitalocean.com](https://digitalocean.com)
2. **Domain Name** (optional but recommended)
3. **SSH Key** for secure server access

## Step 1: Create a DigitalOcean Droplet

### Option A: Using DigitalOcean App Platform (Recommended)

1. **Login to DigitalOcean**
   - Go to [cloud.digitalocean.com](https://cloud.digitalocean.com)
   - Sign in to your account

2. **Create App**
   - Click "Create" → "Apps"
   - Connect your GitHub repository or upload your code
   - Select "Docker" as the source type

3. **Configure App**
   - **Name**: `stolen-phone-database`
   - **Region**: Choose closest to your users
   - **Branch**: `main` or `master`
   - **Build Command**: Leave empty (uses Dockerfile)
   - **Run Command**: Leave empty (uses CMD in Dockerfile)

4. **Environment Variables**
   - `NODE_ENV`: `production`
   - `PORT`: `3000`

5. **Deploy**
   - Click "Create Resources"
   - Wait for deployment to complete

### Option B: Using DigitalOcean Droplet (Manual)

1. **Create Droplet**
   - Click "Create" → "Droplets"
   - Choose "Marketplace" → "Docker"
   - Select plan: Basic → $6/month (1GB RAM, 1 CPU)
   - Choose datacenter region
   - Add SSH key
   - Click "Create Droplet"

2. **Connect to Droplet**
   ```bash
   ssh root@YOUR_DROPLET_IP
   ```

3. **Install Dependencies**
   ```bash
   # Update system
   apt update && apt upgrade -y
   
   # Install Docker (if not pre-installed)
   curl -fsSL https://get.docker.com -o get-docker.sh
   sh get-docker.sh
   
   # Install Docker Compose
   curl -L "https://github.com/docker/compose/releases/download/v2.20.0/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose
   chmod +x /usr/local/bin/docker-compose
   ```

## Step 2: Deploy Your Application

### For App Platform Users

Your app will be automatically deployed. You can access it at the provided URL.

### For Droplet Users

1. **Upload Your Code**
   ```bash
   # Clone your repository or upload files
   git clone YOUR_REPOSITORY_URL
   cd YOUR_PROJECT_DIRECTORY
   ```

2. **Make Deploy Script Executable**
   ```bash
   chmod +x deploy.sh
   ```

3. **Run Deployment**
   ```bash
   ./deploy.sh
   ```

## Step 3: Configure Domain (Optional)

1. **Add Domain to DigitalOcean**
   - Go to "Networking" → "Domains"
   - Add your domain
   - Point it to your droplet IP or app URL

2. **Update DNS Records**
   - Add A record pointing to your droplet IP
   - Or use CNAME for app platform

## Step 4: SSL Certificate (Recommended)

### For App Platform
SSL is automatically provided by DigitalOcean.

### For Droplet
Install Let's Encrypt SSL:

```bash
# Install Certbot
apt install certbot python3-certbot-nginx -y

# Get SSL certificate
certbot --nginx -d yourdomain.com

# Auto-renewal
crontab -e
# Add: 0 12 * * * /usr/bin/certbot renew --quiet
```

## Step 5: Monitoring and Maintenance

### View Logs
```bash
# App Platform: Use the dashboard
# Droplet:
docker-compose -f docker-compose.prod.yml logs -f
```

### Update Application
```bash
# Pull latest changes
git pull origin main

# Redeploy
./deploy.sh
```

### Backup Database
```bash
# Create backup
cp stolen_phones.db backup_$(date +%Y%m%d_%H%M%S).db

# Or use automated backup
docker exec -it $(docker ps -q --filter "name=app") sqlite3 /app/stolen_phones.db ".backup /app/backup.db"
```

## Step 6: Security Considerations

1. **Firewall Setup**
   ```bash
   # Allow only necessary ports
   ufw allow 22    # SSH
   ufw allow 80    # HTTP
   ufw allow 443   # HTTPS
   ufw enable
   ```

2. **Regular Updates**
   ```bash
   # Update system packages
   apt update && apt upgrade -y
   
   # Update Docker images
   docker-compose -f docker-compose.prod.yml pull
   ```

3. **Database Security**
   - Consider migrating from SQLite to PostgreSQL for production
   - Implement regular backups
   - Use environment variables for sensitive data

## Troubleshooting

### Common Issues

1. **Application Won't Start**
   ```bash
   # Check logs
   docker-compose -f docker-compose.prod.yml logs app
   
   # Check if port is in use
   netstat -tulpn | grep :3000
   ```

2. **Database Issues**
   ```bash
   # Check database file permissions
   ls -la stolen_phones.db
   
   # Fix permissions if needed
   chmod 644 stolen_phones.db
   ```

3. **Nginx Issues**
   ```bash
   # Check nginx logs
   docker-compose -f docker-compose.prod.yml logs nginx
   
   # Test nginx configuration
   docker exec -it $(docker ps -q --filter "name=nginx") nginx -t
   ```

### Performance Optimization

1. **Enable Gzip Compression**
   - Already configured in nginx.conf

2. **Database Optimization**
   ```sql
   -- Run these in your SQLite database
   VACUUM;
   ANALYZE;
   ```

3. **Monitor Resources**
   ```bash
   # Check resource usage
   docker stats
   
   # Monitor disk space
   df -h
   ```

## Support

If you encounter issues:

1. Check the logs: `docker-compose -f docker-compose.prod.yml logs -f`
2. Verify your configuration files
3. Ensure all dependencies are installed
4. Check DigitalOcean's status page for any service issues

## Cost Optimization

- **App Platform**: Pay per app usage
- **Droplet**: $6/month for basic plan
- **Bandwidth**: First 1TB free, then $0.01/GB
- **Storage**: Included in droplet price

## Next Steps

1. Set up monitoring with DigitalOcean's built-in tools
2. Configure automated backups
3. Set up CI/CD pipeline for automatic deployments
4. Consider using DigitalOcean's managed database service
5. Implement rate limiting and additional security measures
