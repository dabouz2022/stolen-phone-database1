#!/bin/bash

# Nginx .git Vulnerability Fix Script
# This script modifies /etc/nginx/nginx.conf to block .git access

echo "🔒 Fixing .git vulnerability in nginx configuration..."

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

print_status() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Check if running as root
if [ "$EUID" -ne 0 ]; then
    print_error "Please run this script as root (use sudo)"
    exit 1
fi

NGINX_CONFIG="/etc/nginx/nginx.conf"

# Check if nginx config exists
if [ ! -f "$NGINX_CONFIG" ]; then
    print_error "Nginx configuration file not found at $NGINX_CONFIG"
    exit 1
fi

print_status "Found nginx configuration at: $NGINX_CONFIG"

# Check if security rules already exist
if grep -q "location ~ /\\." "$NGINX_CONFIG"; then
    print_warning "Security rules already exist in nginx configuration"
    print_status "Checking if they're working..."
    
    # Test the current configuration
    if nginx -t; then
        print_status "Current configuration is valid"
        systemctl reload nginx
        print_status "✅ Security rules are already active!"
        
        # Test the fix
        print_status "Testing .git access..."
        HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" http://localhost/.git/config 2>/dev/null || echo "000")
        if [ "$HTTP_CODE" = "403" ]; then
            print_status "✅ .git access blocked successfully (403)"
        else
            print_warning "⚠️  .git access returned $HTTP_CODE (expected 403)"
        fi
        exit 0
    else
        print_error "Current configuration has errors"
        exit 1
    fi
fi

# Create backup
BACKUP_FILE="${NGINX_CONFIG}.backup.$(date +%Y%m%d_%H%M%S)"
print_status "Creating backup: $BACKUP_FILE"
cp "$NGINX_CONFIG" "$BACKUP_FILE"

# Add security rules to the configuration
print_status "Adding security rules to nginx configuration..."

# Create a temporary file
TEMP_CONFIG=$(mktemp)

# Process the nginx config and add security rules
awk '
BEGIN { added_rules = 0 }
/server {/ { 
    print $0
    if (!added_rules) {
        print ""
        print "        # Security: Block access to sensitive files and directories"
        print "        location ~ /\. {"
        print "            deny all;"
        print "            return 403;"
        print "        }"
        print ""
        print "        # Block access to git repository"
        print "        location ~* /\.git {"
        print "            deny all;"
        print "            return 403;"
        print "        }"
        print ""
        added_rules = 1
    }
    next
}
{ print $0 }
' "$NGINX_CONFIG" > "$TEMP_CONFIG"

# Replace the original config with the new one
cp "$TEMP_CONFIG" "$NGINX_CONFIG"
rm "$TEMP_CONFIG"

# Test the configuration
print_status "Testing nginx configuration..."
if nginx -t; then
    print_status "Configuration is valid"
    
    # Reload nginx
    print_status "Reloading nginx..."
    if systemctl reload nginx; then
        print_status "✅ Security rules applied successfully!"
        
        # Test the fix
        print_status "Testing security fix..."
        sleep 2
        
        # Test .git access (should return 403)
        HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" http://localhost/.git/config 2>/dev/null || echo "000")
        if [ "$HTTP_CODE" = "403" ]; then
            print_status "✅ .git access blocked successfully (403)"
        else
            print_warning "⚠️  .git access returned $HTTP_CODE (expected 403)"
        fi
        
        # Test website still works
        WEBSITE_CODE=$(curl -s -o /dev/null -w "%{http_code}" http://localhost/ 2>/dev/null || echo "000")
        if [ "$WEBSITE_CODE" = "200" ] || [ "$WEBSITE_CODE" = "302" ] || [ "$WEBSITE_CODE" = "301" ]; then
            print_status "✅ Website is still accessible (HTTP $WEBSITE_CODE)"
        else
            print_warning "⚠️  Website returned HTTP $WEBSITE_CODE"
        fi
        
    else
        print_error "Failed to reload nginx. Restoring backup..."
        cp "$BACKUP_FILE" "$NGINX_CONFIG"
        systemctl reload nginx
        exit 1
    fi
else
    print_error "Configuration is invalid. Restoring backup..."
    cp "$BACKUP_FILE" "$NGINX_CONFIG"
    systemctl reload nginx
    exit 1
fi

# Remove .git directory from web root if it exists
if [ -d "/var/www/html/.git" ]; then
    print_warning "Found .git directory in web root. Removing it..."
    rm -rf /var/www/html/.git
fi

# Check for other sensitive files
print_status "Checking for sensitive files in web root..."
find /var/www/html -name "*.env" -o -name "*.log" -o -name "*.sql" -o -name "*.bak" -o -name "*.backup" 2>/dev/null | while read file; do
    print_warning "Found sensitive file: $file"
done

print_status "✅ Security fix completed successfully!"
print_status "Backup saved as: $BACKUP_FILE"

# Final recommendations
echo ""
print_status "Additional security recommendations:"
echo "1. Enable HTTPS: sudo apt install certbot python3-certbot-nginx"
echo "2. Set up firewall: sudo ufw enable"
echo "3. Monitor logs: sudo tail -f /var/log/nginx/access.log"

echo ""
print_status "Test these URLs - they should return 403:"
echo "- https://your-domain.com/.git/config"
echo "- https://your-domain.com/.env"
echo "- https://your-domain.com/package.json"

echo ""
print_status "Script completed successfully! 🛡️"
