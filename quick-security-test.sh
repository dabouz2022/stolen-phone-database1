#!/bin/bash

# Quick Security Test for stolenphonedatabase.app
# Tests the most critical vulnerabilities

echo "🔍 Quick Security Test for stolenphonedatabase.app"
echo "=================================================="

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

print_success() {
    echo -e "${GREEN}[✅ SECURE]${NC} $1"
}

print_vulnerability() {
    echo -e "${RED}[🚨 VULNERABILITY]${NC} $1"
}

print_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

DOMAIN="www.stolenphonedatabase.app"
PROTOCOL="https"

echo "Testing domain: $PROTOCOL://$DOMAIN"
echo ""

# Function to test URL
test_url() {
    local url="$1"
    local expected_status="$2"
    local description="$3"
    
    HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" "$url" 2>/dev/null || echo "000")
    
    if [ "$HTTP_CODE" = "$expected_status" ]; then
        print_success "$description - HTTP $HTTP_CODE"
        return 0
    else
        print_vulnerability "$description - HTTP $HTTP_CODE (Expected: $expected_status)"
        return 1
    fi
}

# Function to test file access
test_file_access() {
    local url="$1"
    local description="$2"
    
    HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" "$url" 2>/dev/null || echo "000")
    
    if [ "$HTTP_CODE" = "200" ]; then
        print_vulnerability "$description - File is accessible (HTTP 200)"
        return 1
    else
        print_success "$description - File is not accessible (HTTP $HTTP_CODE)"
        return 0
    fi
}

VULNERABILITIES=0
SECURE_ITEMS=0

echo "🔒 Testing .git Repository (Critical)"
echo "-------------------------------------"
test_url "$PROTOCOL://$DOMAIN/.git/config" "403" ".git/config access"
if [ $? -eq 1 ]; then ((VULNERABILITIES++)); else ((SECURE_ITEMS++)); fi

test_url "$PROTOCOL://$DOMAIN/.git/HEAD" "403" ".git/HEAD access"
if [ $? -eq 1 ]; then ((VULNERABILITIES++)); else ((SECURE_ITEMS++)); fi

test_url "$PROTOCOL://$DOMAIN/.git/index" "403" ".git/index access"
if [ $? -eq 1 ]; then ((VULNERABILITIES++)); else ((SECURE_ITEMS++)); fi

echo ""
echo "📁 Testing Sensitive Files (Critical)"
echo "-------------------------------------"
test_file_access "$PROTOCOL://$DOMAIN/.env" ".env file"
if [ $? -eq 1 ]; then ((VULNERABILITIES++)); else ((SECURE_ITEMS++)); fi

test_file_access "$PROTOCOL://$DOMAIN/package.json" "package.json file"
if [ $? -eq 1 ]; then ((VULNERABILITIES++)); else ((SECURE_ITEMS++)); fi

test_file_access "$PROTOCOL://$DOMAIN/stolen_phones.db" "stolen_phones.db file"
if [ $? -eq 1 ]; then ((VULNERABILITIES++)); else ((SECURE_ITEMS++)); fi

test_file_access "$PROTOCOL://$DOMAIN/config.php" "config.php file"
if [ $? -eq 1 ]; then ((VULNERABILITIES++)); else ((SECURE_ITEMS++)); fi

echo ""
echo "🔧 Testing Configuration Files"
echo "------------------------------"
test_file_access "$PROTOCOL://$DOMAIN/nginx.conf" "nginx.conf file"
if [ $? -eq 1 ]; then ((VULNERABILITIES++)); else ((SECURE_ITEMS++)); fi

test_file_access "$PROTOCOL://$DOMAIN/.htaccess" ".htaccess file"
if [ $? -eq 1 ]; then ((VULNERABILITIES++)); else ((SECURE_ITEMS++)); fi

echo ""
echo "📄 Testing Documentation Files"
echo "------------------------------"
test_file_access "$PROTOCOL://$DOMAIN/README.md" "README.md file"
if [ $? -eq 1 ]; then ((VULNERABILITIES++)); else ((SECURE_ITEMS++)); fi

test_file_access "$PROTOCOL://$DOMAIN/CHANGELOG.md" "CHANGELOG.md file"
if [ $? -eq 1 ]; then ((VULNERABILITIES++)); else ((SECURE_ITEMS++)); fi

echo ""
echo "🌐 Testing Server Information"
echo "-----------------------------"
test_url "$PROTOCOL://$DOMAIN/server-status" "403" "Apache server-status"
if [ $? -eq 1 ]; then ((VULNERABILITIES++)); else ((SECURE_ITEMS++)); fi

test_url "$PROTOCOL://$DOMAIN/phpinfo.php" "403" "PHP info"
if [ $? -eq 1 ]; then ((VULNERABILITIES++)); else ((SECURE_ITEMS++)); fi

echo ""
echo "🔐 Testing Security Headers"
echo "---------------------------"
HEADERS=$(curl -s -I "$PROTOCOL://$DOMAIN/" 2>/dev/null)

if echo "$HEADERS" | grep -q "X-Frame-Options"; then
    print_success "X-Frame-Options header present"
    ((SECURE_ITEMS++))
else
    print_vulnerability "X-Frame-Options header missing"
    ((VULNERABILITIES++))
fi

if echo "$HEADERS" | grep -q "X-Content-Type-Options"; then
    print_success "X-Content-Type-Options header present"
    ((SECURE_ITEMS++))
else
    print_vulnerability "X-Content-Type-Options header missing"
    ((VULNERABILITIES++))
fi

if echo "$HEADERS" | grep -q "X-XSS-Protection"; then
    print_success "X-XSS-Protection header present"
    ((SECURE_ITEMS++))
else
    print_vulnerability "X-XSS-Protection header missing"
    ((VULNERABILITIES++))
fi

echo ""
echo "📊 Quick Security Summary"
echo "========================="
echo "Total items tested: $((VULNERABILITIES + SECURE_ITEMS))"
echo "Secure items: $SECURE_ITEMS"
echo "Vulnerabilities found: $VULNERABILITIES"

if [ $VULNERABILITIES -eq 0 ]; then
    echo ""
    print_success "🎉 No vulnerabilities detected! Your server is secure."
else
    echo ""
    print_vulnerability "⚠️  $VULNERABILITIES vulnerability(ies) found. Please fix them immediately!"
fi

echo ""
echo "🔧 Quick Recommendations:"
if [ $VULNERABILITIES -gt 0 ]; then
    echo "1. Fix the vulnerabilities found above"
    echo "2. Run the comprehensive scan: ./security-vulnerability-scanner.sh $DOMAIN https"
fi
echo "3. Enable HTTPS (already enabled ✅)"
echo "4. Set up firewall: sudo ufw enable"
echo "5. Install fail2ban: sudo apt install fail2ban"

echo ""
print_info "Quick security test completed! 🛡️"
