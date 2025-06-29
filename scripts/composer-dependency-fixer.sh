#!/bin/bash

# Composer Dependency Fixer for Laravel Genealogy Application
# Specifically handles common Composer issues with PHP 8.4 PPA installations

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

# Check if Composer is installed
check_composer() {
    log "INFO" "Checking Composer installation..."
    
    if ! command -v composer >/dev/null 2>&1; then
        log "WARNING" "Composer not found. Installing Composer..."
        
        # Download and install Composer
        curl -sS https://getcomposer.org/installer | php
        sudo mv composer.phar /usr/local/bin/composer
        sudo chmod +x /usr/local/bin/composer
        
        log "SUCCESS" "Composer installed successfully"
    else
        local composer_version=$(composer --version 2>/dev/null | grep -oP 'Composer version \K[0-9.]+' || echo "unknown")
        log "SUCCESS" "Composer found (version: $composer_version)"
    fi
}

# Detect and fix common Composer issues
fix_composer_issues() {
    log "INFO" "Analyzing Composer configuration and dependencies..."
    
    # Check if composer.json exists
    if [[ ! -f "composer.json" ]]; then
        log "ERROR" "No composer.json found in current directory"
        log "INFO" "Please run this script from your Laravel project root"
        exit 1
    fi
    
    # Update Composer to latest version
    log "INFO" "Updating Composer to latest version..."
    composer self-update --quiet 2>/dev/null || true
    
    # Clear Composer cache
    log "INFO" "Clearing Composer cache..."
    composer clear-cache --quiet
    
    # Check for platform requirements
    log "INFO" "Checking platform requirements..."
    
    # Create temporary composer.json backup
    cp composer.json composer.json.backup
    
    # Update PHP version requirement if needed
    local php_version=$(php -r "echo PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;")
    log "INFO" "Detected PHP version: $php_version"
    
    # Common fixes for Laravel genealogy application
    log "INFO" "Applying common fixes for Laravel applications..."
    
    # Fix PHP version constraints
    if [[ "$php_version" == "8.4" ]]; then
        log "INFO" "Adjusting PHP version constraints for PHP 8.4..."
        
        # Use jq if available, otherwise use sed
        if command -v jq >/dev/null 2>&1; then
            # Update PHP requirement to allow 8.4
            jq '.require.php = "^8.1|^8.2|^8.3|^8.4"' composer.json > composer.json.tmp
            mv composer.json.tmp composer.json
        else
            # Fallback to sed
            sed -i 's/"php": "[^"]*"/"php": "^8.1|^8.2|^8.3|^8.4"/' composer.json
        fi
    fi
    
    # Install dependencies with appropriate flags
    log "INFO" "Installing Composer dependencies..."
    
    # Try different installation strategies
    if composer install --no-scripts --no-autoloader 2>/dev/null; then
        log "SUCCESS" "Dependencies installed without scripts"
        
        # Generate autoloader
        composer dump-autoload --optimize
        
        # Run post-install scripts
        composer run-script post-install-cmd --no-interaction 2>/dev/null || true
        
    elif composer install --ignore-platform-reqs --no-scripts 2>/dev/null; then
        log "WARNING" "Dependencies installed ignoring platform requirements"
        log "WARNING" "Some platform checks were bypassed - verify compatibility"
        
        # Generate autoloader
        composer dump-autoload --optimize
        
    elif composer update --with-dependencies --ignore-platform-reqs 2>/dev/null; then
        log "WARNING" "Dependencies updated ignoring platform requirements"
        log "WARNING" "Package versions may have changed"
        
    else
        log "ERROR" "Failed to install dependencies with all strategies"
        
        # Restore backup
        mv composer.json.backup composer.json
        
        log "INFO" "Restored original composer.json"
        log "INFO" "Please check the specific error messages above"
        exit 1
    fi
    
    # Clean up backup
    rm -f composer.json.backup
    
    log "SUCCESS" "Composer dependencies resolved"
}

# Verify Laravel requirements
verify_laravel() {
    log "INFO" "Verifying Laravel requirements..."
    
    # Check if Laravel is properly installed
    if [[ -f "artisan" ]]; then
        log "SUCCESS" "Laravel detected"
        
        # Try to run Laravel commands to verify installation
        if php artisan --version >/dev/null 2>&1; then
            local laravel_version=$(php artisan --version | grep -oP 'Laravel Framework \K[0-9.]+' || echo "unknown")
            log "SUCCESS" "Laravel is functional (version: $laravel_version)"
        else
            log "ERROR" "Laravel installation appears corrupted"
            return 1
        fi
    else
        log "WARNING" "This doesn't appear to be a Laravel project"
    fi
    
    # Check key Laravel dependencies
    local key_packages=("laravel/framework" "laravel/tinker" "laravel/jetstream")
    
    for package in "${key_packages[@]}"; do
        if composer show "$package" >/dev/null 2>&1; then
            local version=$(composer show "$package" 2>/dev/null | grep 'versions' | head -1 | awk '{print $3}' || echo "unknown")
            log "SUCCESS" "$package installed (version: $version)"
        else
            log "WARNING" "$package not found (may be optional)"
        fi
    done
}

# Test genealogy-specific requirements
test_genealogy_requirements() {
    log "INFO" "Testing genealogy application specific requirements..."
    
    # Test GEDCOM processing requirements
    php -r "
    \$extensions = ['xml', 'xmlreader', 'xmlwriter', 'zip', 'gd'];
    \$missing = [];
    
    foreach (\$extensions as \$ext) {
        if (!extension_loaded(\$ext)) {
            \$missing[] = \$ext;
        }
    }
    
    if (empty(\$missing)) {
        echo 'SUCCESS: All genealogy extensions available';
    } else {
        echo 'ERROR: Missing extensions: ' . implode(', ', \$missing);
        exit(1);
    }
    " && log "SUCCESS" "Genealogy extensions verified" || log "ERROR" "Missing genealogy extensions"
    
    # Test image processing
    php -r "
    if (extension_loaded('gd')) {
        \$info = gd_info();
        if (\$info['JPEG Support'] && \$info['PNG Support']) {
            echo 'SUCCESS: Image processing ready';
        } else {
            echo 'WARNING: Limited image format support';
        }
    } else {
        echo 'ERROR: GD extension not available';
        exit(1);
    }
    " && log "SUCCESS" "Image processing verified" || log "WARNING" "Image processing issues detected"
}

# Generate environment recommendations
generate_recommendations() {
    log "INFO" "Generating environment recommendations..."
    
    echo ""
    echo "🎯 ENVIRONMENT SETUP RECOMMENDATIONS"
    echo "====================================="
    echo ""
    
    # Check .env file
    if [[ -f ".env" ]]; then
        log "SUCCESS" ".env file exists"
    else
        log "WARNING" ".env file missing"
        echo "📝 Run: cp .env.example .env && php artisan key:generate"
    fi
    
    # Check for required directories
    local dirs=("storage/logs" "storage/framework/cache" "storage/framework/sessions" "storage/framework/views" "bootstrap/cache")
    
    for dir in "${dirs[@]}"; do
        if [[ -d "$dir" ]]; then
            log "SUCCESS" "Directory exists: $dir"
        else
            log "WARNING" "Directory missing: $dir"
            echo "📝 Run: mkdir -p $dir"
        fi
    done
    
    # Check permissions
    if [[ -w "storage" ]] && [[ -w "bootstrap/cache" ]]; then
        log "SUCCESS" "Write permissions correct"
    else
        log "WARNING" "Write permissions may be incorrect"
        echo "📝 Run: sudo chown -R \$USER:www-data storage bootstrap/cache"
        echo "📝 Run: chmod -R 775 storage bootstrap/cache"
    fi
    
    echo ""
    echo "🚀 NEXT STEPS FOR DEPLOYMENT"
    echo "=============================="
    echo ""
    echo "1. ✅ Dependencies installed - Run: composer install"
    echo "2. 📝 Setup environment - Run: cp .env.example .env"
    echo "3. 🔑 Generate app key - Run: php artisan key:generate"
    echo "4. 🗄️  Setup database - Run: php artisan migrate --seed"
    echo "5. 🔗 Link storage - Run: php artisan storage:link"
    echo "6. 🎨 Build assets - Run: npm install && npm run build"
    echo "7. 🚀 Start server - Run: php artisan serve"
    echo ""
}

# Main execution
main() {
    echo "🔧 Composer Dependency Fixer"
    echo "=============================="
    echo ""
    echo "Laravel Genealogy Application"
    echo "PHP 8.4 PPA Compatibility Edition"
    echo ""
    
    # Check Composer
    check_composer
    
    # Fix Composer issues
    fix_composer_issues
    
    # Verify Laravel
    verify_laravel
    
    # Test genealogy requirements
    test_genealogy_requirements
    
    # Generate recommendations
    generate_recommendations
    
    echo ""
    log "SUCCESS" "Composer dependency fixing completed!"
    echo ""
}

# Check if running from correct directory
if [[ ! -f "composer.json" ]]; then
    echo ""
    log "ERROR" "No composer.json found in current directory"
    log "INFO" "Please run this script from your Laravel project root directory"
    echo ""
    exit 1
fi

# Run main function
main "$@"