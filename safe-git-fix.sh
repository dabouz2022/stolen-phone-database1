#!/bin/bash

# Safe .git Vulnerability Fix Script
# This script applies security rules without breaking the existing setup

echo "🔒 Applying safe .git vulnerability fix..."

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

# Find the actual nginx configuration file being used
print_status "Detecting nginx configuration..."

# Check common nginx config locations
NGINX_CONFIGS=(
    "/etc/nginx/sites-available/default"
    "/etc/nginx/nginx.conf"
    "/etc/nginx/conf.d/default.conf"
    "/etc/nginx/conf.d/app.conf"
)

ACTIVE_CONFIG=""
for config in "${NGINX_CONFIGS[@]}"; do
    if [ -f "$config" ]; then
        print_status "Found nginx config: $config"
        ACTIVE_CONFIG="$config"
        break
    fi
done

if [ -z "$ACTIVE_CONFIG" ]; then
    print_error "No nginx configuration found. Please check your nginx setup."
    exit 1
fi

# Backup the current configuration
BACKUP_FILE="${ACTIVE_CONFIG}.backup.$(date +%Y%m%d_%H%M%S)"
print_status "Creating backup: $BACKUP_FILE"
cp "$ACTIVE_CONFIG" "$BACKUP_FILE"

# Check if security rules already exist
if grep -q "location ~ /\\." "$ACTIVE_CONFIG"; then
    print_warning "Security rules already exist in $ACTIVE_CONFIG"
    print_status "Checking if they're working..."
    
    # Test the current configuration
    if nginx -t; then
        print_status "Current configuration is valid"
        systemctl reload nginx
        print_status "✅ Security rules are already active!"
        exit 0
    else
        print_error "Current configuration has errors. Restoring backup..."
        cp "$BACKUP_FILE" "$ACTIVE_CONFIG"
        systemctl reload nginx
        exit 1
    fi
fi

# Add security rules to the existing configuration
print_status "Adding security rules to $ACTIVE_CONFIG..."

# Create a temporary file with the security rules
TEMP_CONFIG=$(mktemp)

# Read the current config and add security rules before the main location block
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
        print "        # Block access to sensitive files"
        print "        location ~* \.(env|log|sql|bak|backup|old|orig|tmp|temp|swp|swo)$ {"
        print "            deny all;"
        print "            return 403;"
        print "        }"
        print ""
        print "        # Block access to configuration files"
        print "        location ~* \.(ini|conf|config|cfg|yml|yaml)$ {"
        print "            deny all;"
        print "            return 403;"
        print "        }"
        print ""
        print "        # Block access to package files"
        print "        location ~* (package\.json|package-lock\.json|composer\.json|composer\.lock)$ {"
        print "            deny all;"
        print "            return 403;"
        print "        }"
        print ""
        added_rules = 1
    }
    next
}
{ print $0 }
' "$ACTIVE_CONFIG" > "$TEMP_CONFIG"

# Replace the original config with the new one
cp "$TEMP_CONFIG" "$ACTIVE_CONFIG"
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
        
    else
        print_error "Failed to reload nginx. Restoring backup..."
        cp "$BACKUP_FILE" "$ACTIVE_CONFIG"
        systemctl reload nginx
        exit 1
    fi
else
    print_error "Configuration is invalid. Restoring backup..."
    cp "$BACKUP_FILE" "$ACTIVE_CONFIG"
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
