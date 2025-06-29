#!/bin/bash

# Emergency DOM Extension Fix for Laravel Genealogy Application
# Fixes the specific DOMDocument class not found error

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
            echo -e "${BLUE}[INFO]${NC} $message"
            ;;
        "SUCCESS")
            echo -e "${GREEN}[SUCCESS]${NC} $message"
            ;;
        "WARNING")
            echo -e "${YELLOW}[WARNING]${NC} $message"
            ;;
        "ERROR")
            echo -e "${RED}[ERROR]${NC} $message"
            ;;
    esac
}

echo "🚨 Emergency DOM Extension Fix"
echo "=============================="
echo ""
echo "Fixing: Class \"DOMDocument\" not found error"
echo "This is required for Laravel Termwind package"
echo ""

# Detect PHP version
PHP_VERSION=$(php -r "echo PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;" 2>/dev/null || echo "8.4")
log "INFO" "Detected PHP version: $PHP_VERSION"

# Check if DOM extension is installed
if php -m | grep -qi "^dom$" 2>/dev/null; then
    log "SUCCESS" "DOM extension is already installed"
    
    # Check if it's working
    if php -r "new DOMDocument();" 2>/dev/null; then
        log "SUCCESS" "DOM extension is working correctly"
        log "INFO" "The error might be a temporary issue. Try running composer again."
        exit 0
    else
        log "ERROR" "DOM extension is installed but not working correctly"
    fi
else
    log "WARNING" "DOM extension is not installed"
fi

# Install DOM extension
log "INFO" "Installing DOM and XML extensions for PHP $PHP_VERSION..."

# Check for sudo access and prompt if needed
if [[ $EUID -ne 0 ]]; then
    log "INFO" "This script requires sudo access to install packages."
    
    # Test sudo access and prompt for password if needed
    if ! sudo -v 2>/dev/null; then
        log "ERROR" "Unable to obtain sudo access."
        log "INFO" "Please ensure your user has sudo privileges and try again."
        exit 1
    fi
    
    log "SUCCESS" "Sudo access confirmed"
fi

# Update package list
log "INFO" "Updating package list..."
sudo apt update -qq

# Install required packages
PACKAGES=(
    "php${PHP_VERSION}-dom"
    "php${PHP_VERSION}-xml" 
    "php${PHP_VERSION}-simplexml"
)

log "INFO" "Installing packages: ${PACKAGES[*]}"

if sudo apt install -y "${PACKAGES[@]}"; then
    log "SUCCESS" "Successfully installed DOM and XML extensions"
else
    log "ERROR" "Failed to install some packages"
    exit 1
fi

# Restart PHP-FPM if running
if systemctl is-active --quiet "php${PHP_VERSION}-fpm" 2>/dev/null; then
    log "INFO" "Restarting PHP-FPM..."
    sudo systemctl restart "php${PHP_VERSION}-fpm"
    log "SUCCESS" "PHP-FPM restarted"
fi

# Test DOM extension
log "INFO" "Testing DOM extension..."
if php -r "
try {
    \$dom = new DOMDocument();
    echo 'DOM extension working correctly';
} catch (Error \$e) {
    echo 'ERROR: ' . \$e->getMessage();
    exit(1);
}
" 2>/dev/null; then
    log "SUCCESS" "DOM extension is now working correctly"
else
    log "ERROR" "DOM extension installation failed or not working"
    exit 1
fi

# Clear any PHP caches
if command -v opcache_reset >/dev/null 2>&1; then
    log "INFO" "Clearing OPCache..."
    php -r "opcache_reset();" 2>/dev/null || true
fi

echo ""
log "SUCCESS" "DOM extension fix completed!"
echo ""
echo "🚀 Next Steps:"
echo "=============="
echo ""
echo "1. Run Composer again:"
echo "   composer install"
echo ""
echo "2. If you still get errors, try:"
echo "   composer clear-cache"
echo "   composer install --no-cache"
echo ""
echo "3. Generate Laravel application key:"
echo "   php artisan key:generate"
echo ""
echo "4. Test Laravel functionality:"
echo "   php artisan --version"
echo ""

# Verify Composer can run
log "INFO" "Testing Composer compatibility..."
if composer check-platform-reqs --no-dev >/dev/null 2>&1; then
    log "SUCCESS" "All Composer platform requirements are now satisfied"
    echo ""
    echo "✅ Ready to run: composer install"
else
    log "WARNING" "Some platform requirements may still be missing"
    echo ""
    echo "ℹ️  Run this to see any remaining issues:"
    echo "   composer check-platform-reqs"
fi

echo ""
log "INFO" "DOM extension fix completed. You should now be able to run composer install successfully."