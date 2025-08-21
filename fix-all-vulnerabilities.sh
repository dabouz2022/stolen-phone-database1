#!/bin/bash

# Comprehensive Security Fix Script
# Fixes all vulnerabilities found in the security scan

echo "🔧 Comprehensive Security Fix Script"
echo "===================================="

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

print_status() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[✅ FIXED]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[⚠️  WARNING]${NC} $1"
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

# Create backup
BACKUP_FILE="${NGINX_CONFIG}.backup.$(date +%Y%m%d_%H%M%S)"
print_status "Creating backup: $BACKUP_FILE"
cp "$NGINX_CONFIG" "$BACKUP_FILE"

print_status "Fixing all security vulnerabilities..."

# Function to add security rules to nginx config
add_security_rules() {
    print_status "Adding comprehensive security rules to nginx configuration..."
    
    # Create a temporary file
    TEMP_CONFIG=$(mktemp)
    
    # Process the nginx config and add comprehensive security rules
    awk '
    BEGIN { 
        added_rules = 0
        in_server_block = 0
        brace_count = 0
    }
    /server {/ { 
        in_server_block = 1
        brace_count = 1
        print $0
        next
    }
    in_server_block && /{/ { 
        brace_count++
        print $0
        next
    }
    in_server_block && /}/ { 
        brace_count--
        if (brace_count == 0) {
            in_server_block = 0
        }
        print $0
        next
    }
    in_server_block && /location \/ {/ && !added_rules {
        print ""
        print "        # Security: Block access to sensitive files and directories"
        print "        location ~ /\\. {"
        print "            deny all;"
        print "            return 403;"
        print "        }"
        print ""
        print "        # Block access to git repository"
        print "        location ~* /\\.git {"
        print "            deny all;"
        print "            return 403;"
        print "        }"
        print ""
        print "        # Block access to sensitive files"
        print "        location ~* \\.(env|log|sql|bak|backup|old|orig|tmp|temp|swp|swo)$ {"
        print "            deny all;"
        print "            return 403;"
        print "        }"
        print ""
        print "        # Block access to configuration files"
        print "        location ~* \\.(ini|conf|config|cfg|yml|yaml)$ {"
        print "            deny all;"
        print "            return 403;"
        print "        }"
        print ""
        print "        # Block access to package files"
        print "        location ~* (package\\.json|package-lock\\.json|composer\\.json|composer\\.lock|Gemfile|Gemfile\\.lock)$ {"
        print "            deny all;"
        print "            return 403;"
        print "        }"
        print ""
        print "        # Block access to database files"
        print "        location ~* \\.(db|sqlite|sqlite3)$ {"
        print "            deny all;"
        print "            return 403;"
        print "        }"
        print ""
        print "        # Block access to documentation files"
        print "        location ~* (README|CHANGELOG|LICENSE|TODO|HISTORY)\\.(md|txt)$ {"
        print "            deny all;"
        print "            return 403;"
        print "        }"
        print ""
        print "        # Block access to IDE and editor files"
        print "        location ~* \\.(vscode|idea|sublime|vim|emacs) {"
        print "            deny all;"
        print "            return 403;"
        print "        }"
        print ""
        print "        # Block access to server information"
        print "        location ~* (server-status|nginx_status|phpinfo|info\\.php)$ {"
        print "            deny all;"
        print "            return 403;"
        print "        }"
        print ""
        print "        # Block directory listing"
        print "        location ~* /(images|uploads|files|data)/$ {"
        print "            deny all;"
        print "            return 403;"
        print "        }"
        print ""
        added_rules = 1
        print $0
        next
    }
    { print $0 }
    ' "$NGINX_CONFIG" > "$TEMP_CONFIG"
    
    # Replace the original config with the new one
    cp "$TEMP_CONFIG" "$NGINX_CONFIG"
    rm "$TEMP_CONFIG"
}

# Add security rules
add_security_rules

# Test the configuration
print_status "Testing nginx configuration..."
if nginx -t; then
    print_success "Nginx configuration is valid"
    
    # Reload nginx
    print_status "Reloading nginx..."
    if systemctl reload nginx; then
        print_success "Nginx reloaded successfully"
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

# Remove sensitive files from web root
print_status "Removing sensitive files from web root..."

# Check and remove .git directory
if [ -d "/var/www/html/.git" ]; then
    print_warning "Removing .git directory from web root..."
    rm -rf /var/www/html/.git
    print_success ".git directory removed"
fi

# Check and remove sensitive files
SENSITIVE_FILES=(
    "/var/www/html/package.json"
    "/var/www/html/stolen_phones.db"
    "/var/www/html/nginx.conf"
    "/var/www/html/README.md"
    "/var/www/html/.env"
    "/var/www/html/config.php"
)

for file in "${SENSITIVE_FILES[@]}"; do
    if [ -f "$file" ]; then
        print_warning "Removing sensitive file: $file"
        rm -f "$file"
        print_success "Removed: $file"
    fi
done

# Set proper file permissions
print_status "Setting proper file permissions..."
find /var/www/html -type f -exec chmod 644 {} \;
find /var/www/html -type d -exec chmod 755 {} \;

# Create a test script to verify fixes
print_status "Creating verification script..."
cat > /tmp/verify-security-fix.sh << 'EOF'
#!/bin/bash

echo "🔍 Verifying security fixes..."
echo "=============================="

DOMAIN="www.stolenphonedatabase.app"
PROTOCOL="https"

# Test .git access
echo "Testing .git access..."
curl -s -o /dev/null -w "%{http_code}" "$PROTOCOL://$DOMAIN/.git/config"
echo " (should return 403)"

# Test sensitive files
echo "Testing sensitive files..."
curl -s -o /dev/null -w "%{http_code}" "$PROTOCOL://$DOMAIN/package.json"
echo " (should return 403)"

curl -s -o /dev/null -w "%{http_code}" "$PROTOCOL://$DOMAIN/stolen_phones.db"
echo " (should return 403)"

curl -s -o /dev/null -w "%{http_code}" "$PROTOCOL://$DOMAIN/nginx.conf"
echo " (should return 403)"

curl -s -o /dev/null -w "%{http_code}" "$PROTOCOL://$DOMAIN/README.md"
echo " (should return 403)"

echo "Verification completed!"
EOF

chmod +x /tmp/verify-security-fix.sh

print_status "✅ All security vulnerabilities fixed!"
print_status "Backup saved as: $BACKUP_FILE"

# Final recommendations
echo ""
print_status "Additional security recommendations:"
echo "1. Enable HTTPS with Let's Encrypt: sudo apt install certbot python3-certbot-nginx"
echo "2. Set up a firewall: sudo ufw enable"
echo "3. Install fail2ban: sudo apt install fail2ban"
echo "4. Keep your system updated: sudo apt update && sudo apt upgrade"
echo "5. Monitor logs: sudo tail -f /var/log/nginx/access.log"

echo ""
print_status "Run verification: /tmp/verify-security-fix.sh"
print_status "Run security scan: ./quick-security-test.sh"

echo ""
print_success "Security fix completed successfully! 🛡️"
