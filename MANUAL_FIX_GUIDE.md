# Manual Fix Guide for .git Vulnerability

## Immediate Steps to Fix the Vulnerability

### Step 1: Connect to your Digital Ocean server
```bash
ssh root@your-server-ip
```

### Step 2: Check which web server you're using
```bash
# Check if nginx is running
systemctl is-active nginx

# Check if Apache is running
systemctl is-active apache2
```

### Step 3: Backup your current configuration
```bash
# For nginx
cp /etc/nginx/sites-available/default /etc/nginx/sites-available/default.backup

# For Apache
cp /etc/apache2/sites-available/000-default.conf /etc/apache2/sites-available/000-default.conf.backup
```

### Step 4: Apply the security fix

#### For Nginx:
```bash
# Edit the nginx configuration
nano /etc/nginx/sites-available/default
```

Add these security rules **BEFORE** your main location block:
```nginx
# Security: Block access to sensitive files and directories
location ~ /\. {
    deny all;
    return 403;
}

# Block access to git repository
location ~* /\.git {
    deny all;
    return 403;
}

# Block access to version control directories
location ~* /\.(svn|hg|bzr|cvs) {
    deny all;
    return 403;
}

# Block access to sensitive files
location ~* \.(env|log|sql|bak|backup|old|orig|tmp|temp|swp|swo)$ {
    deny all;
    return 403;
}

# Block access to configuration files
location ~* \.(ini|conf|config|cfg|yml|yaml)$ {
    deny all;
    return 403;
}

# Block access to package files
location ~* (package\.json|package-lock\.json|composer\.json|composer\.lock|Gemfile|Gemfile\.lock)$ {
    deny all;
    return 403;
}
```

#### For Apache:
```bash
# Create .htaccess file
nano /var/www/html/.htaccess
```

Add this content:
```apache
# Security: Block access to sensitive files and directories
<Files ~ "^\.">
    Order allow,deny
    Deny from all
</Files>

# Block access to git repository
<DirectoryMatch "^/.*/\.git">
    Order allow,deny
    Deny from all
</DirectoryMatch>

# Block access to sensitive files
<FilesMatch "\.(env|log|sql|bak|backup|old|orig|tmp|temp|swp|swo|ini|conf|config|cfg|yml|yaml)$">
    Order allow,deny
    Deny from all
</FilesMatch>
```

### Step 5: Test and reload the configuration

#### For Nginx:
```bash
# Test configuration
nginx -t

# If test passes, reload nginx
systemctl reload nginx
```

#### For Apache:
```bash
# Test configuration
apache2ctl configtest

# If test passes, reload Apache
systemctl reload apache2
```

### Step 6: Remove .git directory from web root (if it exists)
```bash
# Check if .git exists in web root
ls -la /var/www/html/

# If .git exists, remove it
rm -rf /var/www/html/.git
```

### Step 7: Test the fix
```bash
# Test .git access (should return 403)
curl -I http://your-domain.com/.git/config

# Test other sensitive files (should return 403)
curl -I http://your-domain.com/.env
curl -I http://your-domain.com/package.json
```

## Quick Automated Fix

If you prefer to use the automated script:

1. Download the script to your server:
```bash
wget https://raw.githubusercontent.com/your-repo/fix-git-vulnerability.sh
```

2. Make it executable and run it:
```bash
chmod +x fix-git-vulnerability.sh
sudo ./fix-git-vulnerability.sh
```

## Additional Security Recommendations

1. **Enable HTTPS**:
```bash
sudo apt install certbot python3-certbot-nginx
sudo certbot --nginx -d your-domain.com
```

2. **Set up a firewall**:
```bash
sudo ufw enable
sudo ufw allow ssh
sudo ufw allow 'Nginx Full'
```

3. **Install fail2ban for additional protection**:
```bash
sudo apt install fail2ban
sudo systemctl enable fail2ban
sudo systemctl start fail2ban
```

4. **Keep your system updated**:
```bash
sudo apt update && sudo apt upgrade
```

## Verification

After applying the fix, test these URLs - they should all return 403 Forbidden:
- `https://your-domain.com/.git/config`
- `https://your-domain.com/.git/HEAD`
- `https://your-domain.com/.env`
- `https://your-domain.com/package.json`

## Emergency Rollback

If something goes wrong, restore your backup:
```bash
# For nginx
cp /etc/nginx/sites-available/default.backup /etc/nginx/sites-available/default
systemctl reload nginx

# For Apache
cp /etc/apache2/sites-available/000-default.conf.backup /etc/apache2/sites-available/000-default.conf
systemctl reload apache2
```
