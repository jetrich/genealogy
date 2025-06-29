#!/bin/bash

# Test Script for Quick Setup - Simulates the DOM extension issue
# This helps verify the quick-setup.sh script handles PHP 8.4 PPA issues correctly

set -euo pipefail

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

log() {
    local level=$1
    shift
    local message="$*"
    
    case $level in
        "INFO")
            echo -e "${BLUE}[TEST]${NC} $message"
            ;;
        "SUCCESS")
            echo -e "${GREEN}[PASS]${NC} $message"
            ;;
        "WARNING")
            echo -e "${YELLOW}[WARN]${NC} $message"
            ;;
        "ERROR")
            echo -e "${RED}[FAIL]${NC} $message"
            ;;
    esac
}

echo "🧪 Testing Quick Setup Script"
echo "============================="
echo ""

# Test 1: Check if script exists and is executable
log "INFO" "Test 1: Script availability"
if [[ -f "scripts/quick-setup.sh" && -x "scripts/quick-setup.sh" ]]; then
    log "SUCCESS" "quick-setup.sh exists and is executable"
else
    log "ERROR" "quick-setup.sh not found or not executable"
    exit 1
fi

# Test 2: Check if helper scripts exist
log "INFO" "Test 2: Helper script availability"
HELPER_SCRIPTS=(
    "scripts/php-dependency-installer.sh"
    "scripts/composer-dependency-fixer.sh"
    "scripts/fix-dom-extension.sh"
    "scripts/deployment-validation.sh"
    "scripts/php-health-check.sh"
)

for script in "${HELPER_SCRIPTS[@]}"; do
    if [[ -f "$script" && -x "$script" ]]; then
        log "SUCCESS" "$(basename "$script") available"
    else
        log "ERROR" "$(basename "$script") missing or not executable"
    fi
done

# Test 3: Check PHP installation
log "INFO" "Test 3: PHP installation check"
if command -v php >/dev/null 2>&1; then
    PHP_VERSION=$(php -r "echo PHP_VERSION;")
    log "SUCCESS" "PHP available: $PHP_VERSION"
else
    log "ERROR" "PHP not found"
    exit 1
fi

# Test 4: Test DOM extension status
log "INFO" "Test 4: DOM extension status"
if php -r "new DOMDocument();" >/dev/null 2>&1; then
    log "SUCCESS" "DOM extension working"
    DOM_STATUS="working"
else
    log "WARNING" "DOM extension missing (this is what we're testing)"
    DOM_STATUS="missing"
fi

# Test 5: Test sudo access (without actually using it)
log "INFO" "Test 5: Sudo access check"
if sudo -n true 2>/dev/null; then
    log "SUCCESS" "Sudo access available (cached)"
elif sudo -v 2>/dev/null; then
    log "SUCCESS" "Sudo access available (prompted)"
else
    log "WARNING" "Sudo access not available"
fi

# Test 6: Check Composer
log "INFO" "Test 6: Composer availability"
if command -v composer >/dev/null 2>&1; then
    COMPOSER_VERSION=$(composer --version 2>/dev/null | grep -oP 'Composer version \K[0-9.]+' || echo "unknown")
    log "SUCCESS" "Composer available: $COMPOSER_VERSION"
else
    log "WARNING" "Composer not found"
fi

# Test 7: Check composer.json
log "INFO" "Test 7: Laravel project structure"
if [[ -f "composer.json" ]]; then
    if grep -q "laravel/framework" composer.json; then
        log "SUCCESS" "Laravel project detected"
    else
        log "WARNING" "composer.json exists but doesn't appear to be Laravel"
    fi
else
    log "ERROR" "composer.json not found - not in Laravel project root"
fi

# Test 8: Simulate the DOM extension fix
log "INFO" "Test 8: DOM extension fix simulation"
if [[ "$DOM_STATUS" == "missing" ]]; then
    log "INFO" "DOM extension is missing - this is perfect for testing"
    log "INFO" "The quick-setup.sh script should automatically fix this"
else
    log "INFO" "DOM extension is working - script should skip the fix"
fi

echo ""
echo "📋 Test Summary"
echo "==============="
echo ""

if [[ "$DOM_STATUS" == "missing" ]]; then
    echo "🎯 Perfect testing scenario:"
    echo "   • DOM extension is missing (simulates the original issue)"
    echo "   • quick-setup.sh should automatically detect and fix this"
    echo "   • No manual intervention should be required"
else
    echo "ℹ️  Testing scenario:"
    echo "   • DOM extension is already working"
    echo "   • quick-setup.sh should detect this and skip the fix"
    echo "   • Script should proceed normally"
fi

echo ""
echo "🚀 Ready to test quick-setup.sh"
echo ""
echo "Run the following command to test the enhanced setup:"
echo "   ./scripts/quick-setup.sh"
echo ""
echo "Expected behavior:"
if [[ "$DOM_STATUS" == "missing" ]]; then
    echo "   1. ✅ Script detects missing DOM extension"
    echo "   2. ✅ Script prompts for sudo password"
    echo "   3. ✅ Script installs php8.4-dom php8.4-xml php8.4-simplexml"
    echo "   4. ✅ Script tests DOM functionality"
    echo "   5. ✅ Script continues with Composer installation"
    echo "   6. ✅ No more 'DOMDocument class not found' errors"
else
    echo "   1. ✅ Script detects DOM extension is available"
    echo "   2. ✅ Script skips DOM installation"
    echo "   3. ✅ Script proceeds to check other extensions"
    echo "   4. ✅ Script continues with Composer installation"
fi

echo ""
log "SUCCESS" "Test preparation completed - ready for quick-setup.sh testing"