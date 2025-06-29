#!/bin/bash

# PHP Dependency Auto-Detection & Installation Script
# For Laravel Genealogy Application on Debian/Ubuntu Systems
# Supports PHP 8.1, 8.2, 8.3, 8.4 (including PPA installations)

set -euo pipefail

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
SCRIPT_NAME="PHP Dependency Installer"
LOG_FILE="/tmp/php-dependency-install.log"
DRY_RUN=false
VERBOSE=false
AUTO_CONFIRM=false

# PHP version detection
PHP_VERSION=""
PHP_BINARY=""

# Laravel genealogy application specific requirements
REQUIRED_EXTENSIONS=(
    "bcmath"       # For precise calculations
    "ctype"        # Character type checking
    "curl"         # HTTP requests
    "dom"          # XML/HTML manipulation (CRITICAL for Laravel)
    "fileinfo"     # File type detection
    "filter"       # Input filtering
    "gd"           # Image processing for genealogy photos
    "hash"         # Hashing functions
    "intl"         # Internationalization (multi-language support)
    "json"         # JSON handling
    "libxml"       # XML support
    "mbstring"     # Multi-byte string handling
    "openssl"      # SSL/TLS support
    "pcre"         # Regular expressions
    "pdo"          # Database abstraction
    "pdo_mysql"    # MySQL support for genealogy data
    "session"      # Session management
    "tokenizer"    # PHP tokenizer
    "xml"          # XML processing for GEDCOM files
    "xmlreader"    # XML reading for GEDCOM import
    "xmlwriter"    # XML writing for GEDCOM export
    "zip"          # Archive handling for backups/exports
    "zlib"         # Compression support
    "simplexml"    # Simple XML processing (required by many Laravel packages)
)

# Optional extensions for enhanced functionality
OPTIONAL_EXTENSIONS=(
    "imagick"      # Advanced image processing
    "redis"        # Redis caching
    "memcached"    # Memcached support
    "opcache"      # PHP opcode caching
    "xdebug"       # Development debugging
    "soap"         # SOAP web services
    "exif"         # Image metadata reading
    "calendar"     # Calendar functions for dates
)

# Composer requirements detection
COMPOSER_REQUIREMENTS=()

# Logging function
log() {
    local level=$1
    shift
    local message="$*"
    local timestamp=$(date '+%Y-%m-%d %H:%M:%S')
    
    case $level in
        "INFO")
            if [[ "$VERBOSE" == true ]]; then
                echo -e "${BLUE}[INFO]${NC} $message"
            fi
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
    
    echo "[$timestamp] [$level] $message" >> "$LOG_FILE"
}

# Help function
show_help() {
    cat << EOF
$SCRIPT_NAME

USAGE:
    $0 [OPTIONS]

OPTIONS:
    -h, --help          Show this help message
    -v, --verbose       Enable verbose output
    -n, --dry-run       Show what would be done without making changes
    -y, --yes           Auto-confirm all prompts
    --php-version VER   Force specific PHP version (e.g., 8.4)
    --skip-optional     Skip optional extensions
    --composer-only     Only install Composer requirements

EXAMPLES:
    $0                              # Interactive installation
    $0 -v -y                        # Verbose auto-confirm installation
    $0 --php-version 8.4 -y         # Force PHP 8.4 with auto-confirm
    $0 --dry-run                    # Preview what would be installed
    $0 --composer-only              # Only check/install Composer deps

DESCRIPTION:
    Automatically detects and installs PHP extensions required for 
    Laravel genealogy applications. Supports PHP 8.1+ including 
    PPA installations on Debian/Ubuntu systems.

    The script will:
    1. Detect your PHP version and installation method
    2. Check for missing extensions
    3. Install missing system packages
    4. Enable PHP extensions
    5. Verify Composer requirements
    6. Test the installation

EOF
}

# PHP version detection
detect_php_version() {
    log "INFO" "Detecting PHP installation..."
    
    # Try different PHP binaries
    for php_bin in php php8.4 php8.3 php8.2 php8.1; do
        if command -v $php_bin >/dev/null 2>&1; then
            PHP_BINARY=$php_bin
            PHP_VERSION=$($php_bin -r "echo PHP_VERSION;")
            log "SUCCESS" "Found PHP $PHP_VERSION at $(which $php_bin)"
            break
        fi
    done
    
    if [[ -z "$PHP_BINARY" ]]; then
        log "ERROR" "No PHP installation found. Please install PHP first."
        exit 1
    fi
    
    # Extract major.minor version
    PHP_MAJOR_MINOR=$(echo $PHP_VERSION | cut -d. -f1,2)
    log "INFO" "Using PHP $PHP_MAJOR_MINOR"
}

# Check if extension is installed
is_extension_installed() {
    local extension=$1
    $PHP_BINARY -m | grep -qi "^$extension$" 2>/dev/null
}

# Get package name for extension
get_package_name() {
    local extension=$1
    local php_version=$PHP_MAJOR_MINOR
    
    case $extension in
        "imagick")
            echo "php${php_version}-imagick"
            ;;
        "redis")
            echo "php${php_version}-redis"
            ;;
        "memcached")
            echo "php${php_version}-memcached"
            ;;
        "xdebug")
            echo "php${php_version}-xdebug"
            ;;
        "pdo_mysql")
            echo "php${php_version}-mysql"
            ;;
        "dom")
            echo "php${php_version}-dom php${php_version}-xml"
            ;;
        "xml"|"simplexml"|"xmlreader"|"xmlwriter")
            echo "php${php_version}-xml"
            ;;
        "gd")
            echo "php${php_version}-gd"
            ;;
        "opcache")
            echo "php${php_version}-opcache"
            ;;
        "soap")
            echo "php${php_version}-soap"
            ;;
        *)
            echo "php${php_version}-${extension}"
            ;;
    esac
}

# Check system requirements
check_system() {
    log "INFO" "Checking system requirements..."
    
    # Check if running as root or with sudo access
    if [[ $EUID -ne 0 ]]; then
        log "INFO" "This script requires sudo access to install packages."
        
        # Test sudo access and prompt for password if needed
        if ! sudo -v 2>/dev/null; then
            log "ERROR" "Unable to obtain sudo access."
            log "ERROR" "Please ensure your user has sudo privileges and try again."
            exit 1
        fi
        
        log "SUCCESS" "Sudo access confirmed"
    fi
    
    # Check if apt is available
    if ! command -v apt >/dev/null 2>&1; then
        log "ERROR" "This script requires apt package manager (Debian/Ubuntu)."
        exit 1
    fi
    
    # Update package list
    log "INFO" "Updating package list..."
    if [[ "$DRY_RUN" == false ]]; then
        sudo apt update >/dev/null 2>&1 || {
            log "ERROR" "Failed to update package list"
            exit 1
        }
    fi
    
    log "SUCCESS" "System requirements check passed"
}

# Detect Composer requirements
detect_composer_requirements() {
    log "INFO" "Analyzing Composer requirements..."
    
    if [[ ! -f "composer.json" ]]; then
        log "WARNING" "No composer.json found in current directory"
        return
    fi
    
    # Parse composer.json for PHP extension requirements
    if command -v jq >/dev/null 2>&1; then
        # Use jq if available for precise parsing
        local php_requirements=$(cat composer.json | jq -r '.require // {} | to_entries[] | select(.key | startswith("ext-")) | .key' 2>/dev/null | sed 's/^ext-//')
        while IFS= read -r ext; do
            if [[ -n "$ext" ]]; then
                COMPOSER_REQUIREMENTS+=("$ext")
            fi
        done <<< "$php_requirements"
    else
        # Fallback to grep-based parsing
        local php_requirements=$(grep -oP '"ext-\K[^"]+' composer.json 2>/dev/null || true)
        while IFS= read -r ext; do
            if [[ -n "$ext" ]]; then
                COMPOSER_REQUIREMENTS+=("$ext")
            fi
        done <<< "$php_requirements"
    fi
    
    if [[ ${#COMPOSER_REQUIREMENTS[@]} -gt 0 ]]; then
        log "INFO" "Found Composer PHP extension requirements: ${COMPOSER_REQUIREMENTS[*]}"
    else
        log "INFO" "No explicit PHP extension requirements found in composer.json"
    fi
}

# Check missing extensions
check_missing_extensions() {
    local extensions=("$@")
    local missing=()
    
    log "INFO" "Checking for missing PHP extensions..."
    
    for ext in "${extensions[@]}"; do
        if ! is_extension_installed "$ext"; then
            missing+=("$ext")
            log "WARNING" "Missing extension: $ext"
        else
            log "INFO" "Extension already installed: $ext"
        fi
    done
    
    echo "${missing[@]}"
}

# Install missing extensions
install_extensions() {
    local extensions=("$@")
    
    if [[ ${#extensions[@]} -eq 0 ]]; then
        log "SUCCESS" "All required extensions are already installed"
        return
    fi
    
    log "INFO" "Installing missing PHP extensions: ${extensions[*]}"
    
    local packages=()
    for ext in "${extensions[@]}"; do
        local package=$(get_package_name "$ext")
        packages+=("$package")
    done
    
    if [[ "$DRY_RUN" == true ]]; then
        log "INFO" "[DRY RUN] Would install packages: ${packages[*]}"
        return
    fi
    
    # Install packages
    log "INFO" "Installing packages: ${packages[*]}"
    if sudo apt install -y "${packages[@]}" >> "$LOG_FILE" 2>&1; then
        log "SUCCESS" "Successfully installed packages"
    else
        log "ERROR" "Failed to install some packages. Check $LOG_FILE for details."
        
        # Try installing packages individually
        log "INFO" "Attempting individual package installation..."
        for package in "${packages[@]}"; do
            if sudo apt install -y "$package" >> "$LOG_FILE" 2>&1; then
                log "SUCCESS" "Installed $package"
            else
                log "ERROR" "Failed to install $package"
            fi
        done
    fi
    
    # Restart PHP-FPM if running
    if systemctl is-active --quiet php*-fpm 2>/dev/null; then
        log "INFO" "Restarting PHP-FPM services..."
        sudo systemctl restart php*-fpm
    fi
}

# Verify installation
verify_installation() {
    log "INFO" "Verifying PHP extension installation..."
    
    local all_extensions=("${REQUIRED_EXTENSIONS[@]}")
    if [[ ${#COMPOSER_REQUIREMENTS[@]} -gt 0 ]]; then
        all_extensions+=("${COMPOSER_REQUIREMENTS[@]}")
    fi
    
    local missing=()
    for ext in "${all_extensions[@]}"; do
        if ! is_extension_installed "$ext"; then
            missing+=("$ext")
        fi
    done
    
    if [[ ${#missing[@]} -eq 0 ]]; then
        log "SUCCESS" "All required extensions are now installed"
    else
        log "WARNING" "Some extensions are still missing: ${missing[*]}"
    fi
    
    # Test PHP configuration
    log "INFO" "Testing PHP configuration..."
    if $PHP_BINARY -v >/dev/null 2>&1; then
        log "SUCCESS" "PHP configuration is valid"
    else
        log "ERROR" "PHP configuration has errors"
    fi
}

# Test Laravel requirements
test_laravel_requirements() {
    log "INFO" "Testing Laravel-specific requirements..."
    
    # Test database connection requirements
    if is_extension_installed "pdo" && is_extension_installed "pdo_mysql"; then
        log "SUCCESS" "Database extensions available"
    else
        log "ERROR" "Database extensions missing (pdo, pdo_mysql)"
    fi
    
    # Test image processing
    if is_extension_installed "gd"; then
        log "SUCCESS" "Image processing (GD) available"
    else
        log "WARNING" "Image processing (GD) not available"
    fi
    
    # Test advanced image processing
    if is_extension_installed "imagick"; then
        log "SUCCESS" "Advanced image processing (ImageMagick) available"
    else
        log "INFO" "Advanced image processing (ImageMagick) not available (optional)"
    fi
    
    # Test XML processing for GEDCOM
    if is_extension_installed "xml" && is_extension_installed "xmlreader" && is_extension_installed "xmlwriter"; then
        log "SUCCESS" "XML processing for GEDCOM files available"
    else
        log "ERROR" "XML processing extensions missing (required for GEDCOM)"
    fi
    
    # Test compression
    if is_extension_installed "zip" && is_extension_installed "zlib"; then
        log "SUCCESS" "Compression support available"
    else
        log "WARNING" "Compression support incomplete"
    fi
    
    # Test internationalization
    if is_extension_installed "intl"; then
        log "SUCCESS" "Internationalization support available"
    else
        log "WARNING" "Internationalization support missing (recommended for multi-language)"
    fi
}

# Run Composer check
test_composer() {
    log "INFO" "Testing Composer compatibility..."
    
    if ! command -v composer >/dev/null 2>&1; then
        log "WARNING" "Composer not found. Please install Composer."
        return
    fi
    
    if [[ -f "composer.json" ]]; then
        log "INFO" "Running Composer check..."
        if composer check-platform-reqs --no-dev >> "$LOG_FILE" 2>&1; then
            log "SUCCESS" "All Composer platform requirements satisfied"
        else
            log "WARNING" "Some Composer platform requirements not satisfied. Check $LOG_FILE"
        fi
    else
        log "INFO" "No composer.json found, skipping Composer check"
    fi
}

# Generate summary report
generate_report() {
    echo ""
    echo "=========================================="
    echo "        PHP DEPENDENCY REPORT"
    echo "=========================================="
    echo ""
    echo "PHP Version: $PHP_VERSION"
    echo "PHP Binary: $PHP_BINARY"
    echo ""
    echo "INSTALLED EXTENSIONS:"
    echo "--------------------"
    
    for ext in "${REQUIRED_EXTENSIONS[@]}"; do
        if is_extension_installed "$ext"; then
            echo "✅ $ext"
        else
            echo "❌ $ext (MISSING)"
        fi
    done
    
    echo ""
    echo "OPTIONAL EXTENSIONS:"
    echo "-------------------"
    
    for ext in "${OPTIONAL_EXTENSIONS[@]}"; do
        if is_extension_installed "$ext"; then
            echo "✅ $ext"
        else
            echo "⚪ $ext (not installed)"
        fi
    done
    
    echo ""
    echo "COMPOSER REQUIREMENTS:"
    echo "---------------------"
    
    if [[ ${#COMPOSER_REQUIREMENTS[@]} -gt 0 ]]; then
        for ext in "${COMPOSER_REQUIREMENTS[@]}"; do
            if is_extension_installed "$ext"; then
                echo "✅ $ext"
            else
                echo "❌ $ext (MISSING)"
            fi
        done
    else
        echo "No explicit extension requirements found"
    fi
    
    echo ""
    echo "Log file: $LOG_FILE"
    echo ""
}

# Main execution function
main() {
    echo "🔧 $SCRIPT_NAME"
    echo "=================================="
    echo ""
    
    # Initialize log
    echo "Starting PHP dependency installation at $(date)" > "$LOG_FILE"
    
    # Detect PHP
    detect_php_version
    
    # Check system requirements
    check_system
    
    # Detect Composer requirements
    detect_composer_requirements
    
    # Combine all required extensions
    local all_required=("${REQUIRED_EXTENSIONS[@]}")
    if [[ ${#COMPOSER_REQUIREMENTS[@]} -gt 0 ]]; then
        all_required+=("${COMPOSER_REQUIREMENTS[@]}")
    fi
    
    # Remove duplicates
    local unique_required=($(printf "%s\n" "${all_required[@]}" | sort -u))
    
    # Check missing extensions
    local missing_extensions
    IFS=' ' read -ra missing_extensions <<< "$(check_missing_extensions "${unique_required[@]}")"
    
    if [[ ${#missing_extensions[@]} -eq 0 ]]; then
        log "SUCCESS" "All required extensions are already installed!"
    else
        if [[ "$AUTO_CONFIRM" == false && "$DRY_RUN" == false ]]; then
            echo ""
            echo "The following extensions need to be installed:"
            printf "  • %s\n" "${missing_extensions[@]}"
            echo ""
            read -p "Do you want to install these extensions? [Y/n] " -n 1 -r
            echo ""
            if [[ ! $REPLY =~ ^[Yy]?$ ]]; then
                log "INFO" "Installation cancelled by user"
                exit 0
            fi
        fi
        
        # Install missing extensions
        install_extensions "${missing_extensions[@]}"
    fi
    
    # Verify installation
    verify_installation
    
    # Test Laravel requirements
    test_laravel_requirements
    
    # Test Composer
    test_composer
    
    # Generate report
    generate_report
    
    log "SUCCESS" "PHP dependency installation completed!"
}

# Parse command line arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        -h|--help)
            show_help
            exit 0
            ;;
        -v|--verbose)
            VERBOSE=true
            shift
            ;;
        -n|--dry-run)
            DRY_RUN=true
            shift
            ;;
        -y|--yes)
            AUTO_CONFIRM=true
            shift
            ;;
        --php-version)
            if [[ -n "${2:-}" ]]; then
                PHP_MAJOR_MINOR="$2"
                shift 2
            else
                log "ERROR" "--php-version requires a version number"
                exit 1
            fi
            ;;
        --skip-optional)
            OPTIONAL_EXTENSIONS=()
            shift
            ;;
        --composer-only)
            REQUIRED_EXTENSIONS=()
            shift
            ;;
        *)
            log "ERROR" "Unknown option: $1"
            show_help
            exit 1
            ;;
    esac
done

# Run main function
main "$@"