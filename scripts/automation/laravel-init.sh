#!/bin/bash
# =============================================================================
# Laravel Genealogy Application Initialization Script
# =============================================================================
# This script handles Laravel-specific initialization tasks for deployment
# Supports Docker, local development, and production environments

set -euo pipefail

# Color output functions
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

log_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Script configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"
ENV_FILE="$PROJECT_ROOT/.env"
SKIP_COMPOSER=${SKIP_COMPOSER:-false}
SKIP_NPM=${SKIP_NPM:-false}
ENVIRONMENT=${ENVIRONMENT:-development}

# Function definitions
check_prerequisites() {
    log_info "Checking prerequisites..."
    
    # Check if we're in a Laravel project
    if [[ ! -f "$PROJECT_ROOT/artisan" ]]; then
        log_error "Laravel artisan command not found. Are we in a Laravel project?"
        exit 1
    fi
    
    # Check PHP version
    if command -v php >/dev/null 2>&1; then
        PHP_VERSION=$(php -r "echo PHP_VERSION;")
        log_info "PHP version: $PHP_VERSION"
        
        # Check minimum PHP version (8.3 for Laravel 12)
        if ! php -r "exit(version_compare(PHP_VERSION, '8.3.0', '>=') ? 0 : 1);"; then
            log_error "PHP 8.3+ is required for Laravel 12"
            exit 1
        fi
    else
        log_warning "PHP not found in PATH"
    fi
    
    log_success "Prerequisites check completed"
}

setup_environment() {
    log_info "Setting up environment configuration..."
    
    # Create .env file if it doesn't exist
    if [[ ! -f "$ENV_FILE" ]]; then
        if [[ -f "$PROJECT_ROOT/.env.example" ]]; then
            cp "$PROJECT_ROOT/.env.example" "$ENV_FILE"
            log_info "Created .env file from .env.example"
        else
            log_error ".env.example file not found"
            exit 1
        fi
    fi
    
    # Generate application key if not set
    if ! grep -q "^APP_KEY=base64:" "$ENV_FILE"; then
        log_info "Generating application key..."
        php artisan key:generate --ansi
    fi
    
    # Set environment-specific configurations
    case "$ENVIRONMENT" in
        "production")
            log_info "Configuring for production environment..."
            sed -i 's/APP_ENV=.*/APP_ENV=production/' "$ENV_FILE"
            sed -i 's/APP_DEBUG=.*/APP_DEBUG=false/' "$ENV_FILE"
            sed -i 's/LOG_LEVEL=.*/LOG_LEVEL=error/' "$ENV_FILE"
            ;;
        "staging")
            log_info "Configuring for staging environment..."
            sed -i 's/APP_ENV=.*/APP_ENV=staging/' "$ENV_FILE"
            sed -i 's/APP_DEBUG=.*/APP_DEBUG=false/' "$ENV_FILE"
            sed -i 's/LOG_LEVEL=.*/LOG_LEVEL=warning/' "$ENV_FILE"
            ;;
        "development")
            log_info "Configuring for development environment..."
            sed -i 's/APP_ENV=.*/APP_ENV=local/' "$ENV_FILE"
            sed -i 's/APP_DEBUG=.*/APP_DEBUG=true/' "$ENV_FILE"
            sed -i 's/LOG_LEVEL=.*/LOG_LEVEL=debug/' "$ENV_FILE"
            ;;
    esac
    
    log_success "Environment configuration completed"
}

install_dependencies() {
    if [[ "$SKIP_COMPOSER" != "true" ]]; then
        log_info "Installing PHP dependencies..."
        
        if [[ "$ENVIRONMENT" == "production" ]]; then
            composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist
        else
            composer install --optimize-autoloader --no-interaction
        fi
        
        log_success "PHP dependencies installed"
    else
        log_info "Skipping Composer installation"
    fi
    
    if [[ "$SKIP_NPM" != "true" ]] && [[ -f "$PROJECT_ROOT/package.json" ]]; then
        log_info "Installing Node.js dependencies..."
        
        if command -v npm >/dev/null 2>&1; then
            if [[ "$ENVIRONMENT" == "production" ]]; then
                npm ci --only=production
                npm run build
            else
                npm install
                npm run dev
            fi
            log_success "Node.js dependencies installed and assets built"
        else
            log_warning "npm not found, skipping frontend build"
        fi
    else
        log_info "Skipping npm installation"
    fi
}

setup_database() {
    log_info "Setting up database..."
    
    # Wait for database connection
    DB_HOST=$(grep "^DB_HOST=" "$ENV_FILE" | cut -d'=' -f2)
    DB_PORT=$(grep "^DB_PORT=" "$ENV_FILE" | cut -d'=' -f2)
    DB_DATABASE=$(grep "^DB_DATABASE=" "$ENV_FILE" | cut -d'=' -f2)
    
    if [[ -n "$DB_HOST" && "$DB_HOST" != "127.0.0.1" && "$DB_HOST" != "localhost" ]]; then
        log_info "Waiting for database connection to $DB_HOST:$DB_PORT..."
        timeout=60
        while ! nc -z "$DB_HOST" "$DB_PORT" && [[ $timeout -gt 0 ]]; do
            sleep 1
            ((timeout--))
        done
        
        if [[ $timeout -eq 0 ]]; then
            log_error "Database connection timeout"
            exit 1
        fi
        
        log_success "Database connection established"
    fi
    
    # Run migrations
    log_info "Running database migrations..."
    php artisan migrate --force
    
    # Run seeders based on environment
    if [[ "$ENVIRONMENT" == "development" || "$ENVIRONMENT" == "staging" ]]; then
        log_info "Running database seeders..."
        php artisan db:seed --force
    fi
    
    log_success "Database setup completed"
}

setup_storage() {
    log_info "Setting up storage directories and permissions..."
    
    # Create storage directories
    mkdir -p "$PROJECT_ROOT/storage/logs"
    mkdir -p "$PROJECT_ROOT/storage/framework/cache/data"
    mkdir -p "$PROJECT_ROOT/storage/framework/sessions"
    mkdir -p "$PROJECT_ROOT/storage/framework/views"
    mkdir -p "$PROJECT_ROOT/storage/app/public/photos"
    mkdir -p "$PROJECT_ROOT/storage/app/public/gedcom"
    mkdir -p "$PROJECT_ROOT/storage/app/public/profile-photos"
    mkdir -p "$PROJECT_ROOT/storage/app/backups"
    mkdir -p "$PROJECT_ROOT/bootstrap/cache"
    
    # Set proper permissions
    chmod -R 775 "$PROJECT_ROOT/storage"
    chmod -R 775 "$PROJECT_ROOT/bootstrap/cache"
    
    # Create storage link for public files
    if [[ ! -L "$PROJECT_ROOT/public/storage" ]]; then
        log_info "Creating storage link..."
        php artisan storage:link
    fi
    
    log_success "Storage setup completed"
}

optimize_application() {
    log_info "Optimizing Laravel application..."
    
    if [[ "$ENVIRONMENT" == "production" ]]; then
        # Production optimizations
        php artisan config:cache
        php artisan route:cache
        php artisan view:cache
        php artisan event:cache
        
        # Only cache icons if Filament is properly installed
        if php artisan list | grep -q "icons:cache"; then
            php artisan icons:cache
        fi
        
        log_success "Production optimizations applied"
    else
        # Development environment - clear caches
        php artisan config:clear
        php artisan route:clear
        php artisan view:clear
        php artisan cache:clear
        
        log_success "Development caches cleared"
    fi
}

setup_queue_workers() {
    log_info "Setting up queue workers..."
    
    # Create queue worker supervisor configuration if in production
    if [[ "$ENVIRONMENT" == "production" && -d "/etc/supervisor/conf.d" ]]; then
        cat > /etc/supervisor/conf.d/laravel-worker.conf << EOF
[program:laravel-worker]
process_name=%(program_name)s_%(process_num)02d
command=php $PROJECT_ROOT/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=$PROJECT_ROOT/storage/logs/worker.log
stopwaitsecs=3600
EOF
        
        if command -v supervisorctl >/dev/null 2>&1; then
            supervisorctl reread
            supervisorctl update
            supervisorctl start laravel-worker:*
        fi
        
        log_success "Queue workers configured"
    else
        log_info "Queue workers configuration skipped (not in production)"
    fi
}

setup_cron_jobs() {
    log_info "Setting up Laravel scheduler..."
    
    # Add Laravel scheduler to crontab if not already present
    CRON_JOB="* * * * * cd $PROJECT_ROOT && php artisan schedule:run >> /dev/null 2>&1"
    
    if command -v crontab >/dev/null 2>&1; then
        (crontab -l 2>/dev/null || true; echo "$CRON_JOB") | sort -u | crontab -
        log_success "Laravel scheduler configured"
    else
        log_warning "crontab not available, scheduler not configured"
        log_info "To enable the Laravel scheduler, add this to your crontab:"
        echo "$CRON_JOB"
    fi
}

run_health_checks() {
    log_info "Running health checks..."
    
    # Check database connection
    if php artisan migrate:status >/dev/null 2>&1; then
        log_success "Database connection: OK"
    else
        log_error "Database connection: FAILED"
        exit 1
    fi
    
    # Check storage permissions
    if [[ -w "$PROJECT_ROOT/storage/logs" ]]; then
        log_success "Storage permissions: OK"
    else
        log_error "Storage permissions: FAILED"
        exit 1
    fi
    
    # Check queue connection
    if php artisan queue:monitor --once >/dev/null 2>&1; then
        log_success "Queue connection: OK"
    else
        log_warning "Queue connection: Not configured or failed"
    fi
    
    log_success "Health checks completed"
}

# Main execution flow
main() {
    log_info "Starting Laravel Genealogy Application initialization..."
    log_info "Environment: $ENVIRONMENT"
    log_info "Project root: $PROJECT_ROOT"
    
    cd "$PROJECT_ROOT"
    
    check_prerequisites
    setup_environment
    install_dependencies
    setup_storage
    setup_database
    optimize_application
    
    if [[ "$ENVIRONMENT" == "production" ]]; then
        setup_queue_workers
        setup_cron_jobs
    fi
    
    run_health_checks
    
    log_success "Laravel Genealogy Application initialization completed successfully!"
    
    # Show next steps
    echo
    log_info "Next steps:"
    echo "  1. Configure your web server to point to $PROJECT_ROOT/public"
    echo "  2. Set up SSL certificates for production"
    echo "  3. Configure backup schedule using: php artisan backup:run"
    echo "  4. Monitor application logs in: $PROJECT_ROOT/storage/logs"
    
    if [[ "$ENVIRONMENT" == "development" ]]; then
        echo "  5. Start development server: php artisan serve"
        echo "  6. Access application at: http://localhost:8000"
    fi
}

# Handle script arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        --environment=*)
            ENVIRONMENT="${1#*=}"
            shift
            ;;
        --skip-composer)
            SKIP_COMPOSER=true
            shift
            ;;
        --skip-npm)
            SKIP_NPM=true
            shift
            ;;
        --help)
            echo "Laravel Genealogy Application Initialization Script"
            echo
            echo "Usage: $0 [options]"
            echo
            echo "Options:"
            echo "  --environment=ENV    Set environment (development|staging|production)"
            echo "  --skip-composer      Skip Composer dependency installation"
            echo "  --skip-npm           Skip npm dependency installation"
            echo "  --help               Show this help message"
            echo
            echo "Environment variables:"
            echo "  ENVIRONMENT          Default environment (default: development)"
            echo "  SKIP_COMPOSER        Skip Composer installation (default: false)"
            echo "  SKIP_NPM             Skip npm installation (default: false)"
            exit 0
            ;;
        *)
            log_error "Unknown option: $1"
            echo "Use --help for usage information"
            exit 1
            ;;
    esac
done

# Validate environment
case "$ENVIRONMENT" in
    "development"|"staging"|"production")
        ;;
    *)
        log_error "Invalid environment: $ENVIRONMENT"
        log_error "Valid environments: development, staging, production"
        exit 1
        ;;
esac

# Execute main function
main "$@"