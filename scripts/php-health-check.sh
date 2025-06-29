#!/bin/bash

# PHP Health Check for Laravel Genealogy Application
# Quick diagnostic tool for PHP 8.4 PPA installations

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
            echo -e "${GREEN}[✅]${NC} $message"
            ;;
        "WARNING")
            echo -e "${YELLOW}[⚠️ ]${NC} $message"
            ;;
        "ERROR")
            echo -e "${RED}[❌]${NC} $message"
            ;;
    esac
}

echo "🔍 PHP Health Check for Laravel Genealogy"
echo "=========================================="
echo ""

# PHP Version Check
PHP_VERSION=$(php -r "echo PHP_VERSION;" 2>/dev/null || echo "Not found")
PHP_MAJOR_MINOR=$(php -r "echo PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;" 2>/dev/null || echo "0.0")

if [[ "$PHP_VERSION" != "Not found" ]]; then
    log "SUCCESS" "PHP Version: $PHP_VERSION"
else
    log "ERROR" "PHP not found or not working"
    exit 1
fi

echo ""
echo "📦 Critical Extension Check"
echo "==========================="

# Critical extensions for Laravel
CRITICAL_EXTENSIONS=(
    "dom"          # Required for Termwind
    "xml"          # Required for XML processing
    "json"         # Required for JSON handling
    "mbstring"     # Required for string handling
    "openssl"      # Required for encryption
    "pdo"          # Required for database
    "tokenizer"    # Required for Blade
    "fileinfo"     # Required for file handling
    "ctype"        # Required for validation
    "filter"       # Required for input filtering
)

missing_critical=()
for ext in "${CRITICAL_EXTENSIONS[@]}"; do
    if php -m | grep -qi "^$ext$" 2>/dev/null; then
        log "SUCCESS" "$ext"
    else
        log "ERROR" "$ext (MISSING - CRITICAL)"
        missing_critical+=("$ext")
    fi
done

echo ""
echo "🧬 Genealogy-Specific Extensions"
echo "================================"

# Genealogy-specific extensions
GENEALOGY_EXTENSIONS=(
    "gd"           # Image processing
    "zip"          # Archive handling
    "zlib"         # Compression
    "curl"         # HTTP requests
    "intl"         # Internationalization
    "xmlreader"    # GEDCOM reading
    "xmlwriter"    # GEDCOM writing
    "simplexml"    # Simple XML processing
)

missing_genealogy=()
for ext in "${GENEALOGY_EXTENSIONS[@]}"; do
    if php -m | grep -qi "^$ext$" 2>/dev/null; then
        log "SUCCESS" "$ext"
    else
        log "WARNING" "$ext (missing - recommended)"
        missing_genealogy+=("$ext")
    fi
done

echo ""
echo "🔧 Functionality Tests"
echo "======================"

# Test DOM functionality
if php -r "new DOMDocument(); echo 'OK';" >/dev/null 2>&1; then
    log "SUCCESS" "DOM functionality working"
else
    log "ERROR" "DOM functionality broken (DOMDocument class not found)"
fi

# Test JSON functionality
if php -r "json_encode(['test' => 'data']); echo 'OK';" >/dev/null 2>&1; then
    log "SUCCESS" "JSON functionality working"
else
    log "ERROR" "JSON functionality broken"
fi

# Test MySQL PDO
if php -r "new PDO('mysql:host=localhost;dbname=test', 'user', 'pass', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]); echo 'OK';" >/dev/null 2>&1; then
    log "SUCCESS" "MySQL PDO available"
else
    log "WARNING" "MySQL PDO test failed (normal if no database configured)"
fi

# Test image processing
if php -r "imagecreate(1, 1); echo 'OK';" >/dev/null 2>&1; then
    log "SUCCESS" "GD image processing working"
else
    log "WARNING" "GD image processing not available"
fi

echo ""
echo "📋 Package Installation Commands"
echo "================================"

if [[ ${#missing_critical[@]} -gt 0 ]] || [[ ${#missing_genealogy[@]} -gt 0 ]]; then
    echo ""
    log "INFO" "Missing extensions detected. Install commands:"
    echo ""
    
    if [[ ${#missing_critical[@]} -gt 0 ]]; then
        echo "🚨 CRITICAL extensions (install immediately):"
        echo "sudo apt update"
        for ext in "${missing_critical[@]}"; do
            case $ext in
                "pdo")
                    echo "sudo apt install -y php${PHP_MAJOR_MINOR}-mysql"
                    ;;
                "dom"|"xml"|"xmlreader"|"xmlwriter"|"simplexml")
                    echo "sudo apt install -y php${PHP_MAJOR_MINOR}-xml"
                    ;;
                *)
                    echo "sudo apt install -y php${PHP_MAJOR_MINOR}-${ext}"
                    ;;
            esac
        done
        echo ""
    fi
    
    if [[ ${#missing_genealogy[@]} -gt 0 ]]; then
        echo "📝 RECOMMENDED extensions:"
        for ext in "${missing_genealogy[@]}"; do
            case $ext in
                "xmlreader"|"xmlwriter"|"simplexml")
                    echo "sudo apt install -y php${PHP_MAJOR_MINOR}-xml"
                    ;;
                *)
                    echo "sudo apt install -y php${PHP_MAJOR_MINOR}-${ext}"
                    ;;
            esac
        done
        echo ""
    fi
    
    echo "After installation, restart PHP-FPM (if applicable):"
    echo "sudo systemctl restart php${PHP_MAJOR_MINOR}-fpm"
    echo ""
else
    log "SUCCESS" "All extensions are installed!"
fi

echo ""
echo "🏥 System Health Summary"
echo "========================"

if [[ ${#missing_critical[@]} -eq 0 ]]; then
    log "SUCCESS" "PHP is ready for Laravel"
else
    log "ERROR" "PHP has critical issues - ${#missing_critical[@]} missing critical extensions"
fi

if [[ ${#missing_genealogy[@]} -eq 0 ]]; then
    log "SUCCESS" "PHP is ready for genealogy features"
else
    log "WARNING" "PHP is missing ${#missing_genealogy[@]} recommended extensions"
fi

# Test Composer compatibility
echo ""
if command -v composer >/dev/null 2>&1; then
    log "SUCCESS" "Composer is available"
    
    if [[ -f "composer.json" ]]; then
        log "INFO" "Testing Composer platform requirements..."
        if composer check-platform-reqs --no-dev >/dev/null 2>&1; then
            log "SUCCESS" "All Composer platform requirements satisfied"
        else
            log "WARNING" "Some Composer platform requirements not satisfied"
            echo ""
            echo "Run this to see details:"
            echo "composer check-platform-reqs"
        fi
    fi
else
    log "WARNING" "Composer not found"
fi

echo ""
if [[ ${#missing_critical[@]} -eq 0 ]]; then
    echo "✅ Ready to run: ./scripts/quick-setup.sh"
else
    echo "🔧 Fix critical extensions first, then run: ./scripts/quick-setup.sh"
fi

echo ""
log "INFO" "Health check completed."