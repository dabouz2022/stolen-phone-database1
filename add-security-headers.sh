#!/bin/bash

# Add Security Headers Script
# Adds security headers to nginx configuration

echo "🔐 Adding Security Headers to Nginx"
echo "==================================="

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
    echo -e "${GREEN}[✅ ADDED]${NC} $1"
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

print_status "Adding security headers to nginx configuration..."

# Function to add security headers to nginx config
add_security_headers() {
    print_status "Adding security headers to nginx configuration..."
    
    # Create a temporary file
    TEMP_CONFIG=$(mktemp)
    
    # Process the nginx config and add security headers
    awk '
    BEGIN { 
        added_headers = 0
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
    in_server_block && /location \/ {/ && !added_headers {
        print ""
        print "        # Security Headers"
        print "        add_header X-Frame-Options \"SAMEORIGIN\" always;"
        print "        add_header X-Content-Type-Options \"nosniff\" always;"
        print "        add_header X-XSS-Protection \"1; mode=block\" always;"
        print "        add_header Referrer-Policy \"strict-origin-when-cross-origin\" always;"
        print "        add_header Content-Security-Policy \"default-src '\''self'\''; script-src '\''self'\'' '\''unsafe-inline'\'' '\''unsafe-eval'\''; style-src '\''self'\'' '\''unsafe-inline'\''; img-src '\''self'\'' data: https:; font-src '\''self'\'' data:; connect-src '\''self'\'';\" always;"
        print "        add_header Strict-Transport-Security \"max-age=31536000; includeSubDomains\" always;"
        print ""
        added_headers = 1
        print $0
        next
    }
    { print $0 }
    ' "$NGINX_CONFIG" > "$TEMP_CONFIG"
    
    # Replace the original config with the new one
    cp "$TEMP_CONFIG" "$NGINX_CONFIG"
    rm "$TEMP_CONFIG"
}

# Add security headers
add_security_headers

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

print_status "✅ Security headers added successfully!"
print_status "Backup saved as: $BACKUP_FILE"

# Test the headers
print_status "Testing security headers..."
sleep 2

HEADERS=$(curl -s -I "https://www.stolenphonedatabase.app/" 2>/dev/null)

echo ""
print_status "Security Headers Test Results:"

if echo "$HEADERS" | grep -q "X-Frame-Options"; then
    print_success "X-Frame-Options header present"
else
    print_warning "X-Frame-Options header missing"
fi

if echo "$HEADERS" | grep -q "X-Content-Type-Options"; then
    print_success "X-Content-Type-Options header present"
else
    print_warning "X-Content-Type-Options header missing"
fi

if echo "$HEADERS" | grep -q "X-XSS-Protection"; then
    print_success "X-XSS-Protection header present"
else
    print_warning "X-XSS-Protection header missing"
fi

if echo "$HEADERS" | grep -q "Content-Security-Policy"; then
    print_success "Content-Security-Policy header present"
else
    print_warning "Content-Security-Policy header missing"
fi

if echo "$HEADERS" | grep -q "Strict-Transport-Security"; then
    print_success "Strict-Transport-Security header present"
else
    print_warning "Strict-Transport-Security header missing"
fi

echo ""
print_success "Security headers fix completed! 🛡️"
