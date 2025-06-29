#!/bin/bash

# Quick Setup Script for Laravel Genealogy Application
# One-command setup for development environment

set -euo pipefail

# Setup logging
SETUP_LOG_DIR="logs/setup"
SETUP_LOG_FILE="$SETUP_LOG_DIR/quick-setup-$(date +%Y%m%d_%H%M%S).log"
ERROR_LOG_FILE="$SETUP_LOG_DIR/errors-$(date +%Y%m%d_%H%M%S).log"

# Create log directory
mkdir -p "$SETUP_LOG_DIR"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Enhanced logging function
log() {
    local level=$1
    shift
    local message="$*"
    local timestamp=$(date '+%Y-%m-%d %H:%M:%S')
    
    # Log to file with timestamp
    echo "[$timestamp] [$level] $message" >> "$SETUP_LOG_FILE"
    
    # Also log errors to separate error file
    if [[ "$level" == "ERROR" ]]; then
        echo "[$timestamp] [ERROR] $message" >> "$ERROR_LOG_FILE"
    fi
    
    # Display to console with colors
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

# Function to log command output
log_command() {
    local command="$1"
    local description="$2"
    
    log "INFO" "Executing: $description"
    log "INFO" "Command: $command"
    
    # Execute command and capture both stdout and stderr
    if eval "$command" >> "$SETUP_LOG_FILE" 2>&1; then
        log "SUCCESS" "$description completed successfully"
        return 0
    else
        local exit_code=$?
        log "ERROR" "$description failed with exit code $exit_code"
        
        # Capture the last few lines of output for immediate display
        echo "--- Last 10 lines of output ---" >> "$ERROR_LOG_FILE"
        tail -10 "$SETUP_LOG_FILE" >> "$ERROR_LOG_FILE"
        echo "--- End of output ---" >> "$ERROR_LOG_FILE"
        
        return $exit_code
    fi
}

# Function to log system information
log_system_info() {
    log "INFO" "=== SYSTEM INFORMATION ==="
    log "INFO" "Date: $(date)"
    log "INFO" "User: $(whoami)"
    log "INFO" "Working Directory: $(pwd)"
    log "INFO" "PHP Version: $(php --version | head -1 2>/dev/null || echo 'PHP not found')"
    log "INFO" "Composer Version: $(composer --version 2>/dev/null || echo 'Composer not found')"
    log "INFO" "Node Version: $(node --version 2>/dev/null || echo 'Node not found')"
    log "INFO" "NPM Version: $(npm --version 2>/dev/null || echo 'NPM not found')"
    log "INFO" "Docker Version: $(docker --version 2>/dev/null || echo 'Docker not found')"
    log "INFO" "Docker Compose: $(docker compose version 2>/dev/null || echo 'Docker Compose not found')"
    log "INFO" "OS: $(uname -a)"
    log "INFO" "=== END SYSTEM INFORMATION ==="
}

# Docker setup function
docker_setup() {
    log "INFO" "=== DOCKER DEPLOYMENT SETUP ==="
    
    # Step 1: Stop existing containers
    log "INFO" "Step 1/6: Stopping existing containers..."
    
    # Stop standalone containers if running
    for container in genealogy-mysql genealogy-mariadb; do
        if docker ps -q -f name=$container | grep -q .; then
            log "INFO" "Stopping standalone $container container..."
            log_command "docker stop $container && docker rm $container" "Stop standalone $container"
        fi
    done
    
    # Stop any existing compose services
    if docker compose ps -q | grep -q .; then
        log "INFO" "Stopping existing Docker Compose services..."
        log_command "docker compose down" "Stop existing services"
    fi
    
    # Step 2: Configure environment for Docker
    log "INFO" "Step 2/6: Configuring environment for Docker..."
    
    # Backup current .env
    cp .env .env.backup.$(date +%Y%m%d_%H%M%S)
    
    # Generate secure database credentials if not already set
    if ! grep -q "DB_ROOT_PASSWORD=" .env || [[ $(grep "DB_ROOT_PASSWORD=" .env | cut -d'=' -f2) == "" ]]; then
        DB_ROOT_PASS=$(openssl rand -base64 32 | tr -d "=+/" | cut -c1-25)
        log "INFO" "Generated secure database root password"
    else
        DB_ROOT_PASS=$(grep "DB_ROOT_PASSWORD=" .env | cut -d'=' -f2)
        log "INFO" "Using existing database root password"
    fi
    
    if ! grep -q "DB_PASSWORD=" .env || [[ $(grep "DB_PASSWORD=" .env | cut -d'=' -f2) == "" ]]; then
        DB_USER_PASS=$(openssl rand -base64 32 | tr -d "=+/" | cut -c1-25)
        log "INFO" "Generated secure database user password"
    else
        DB_USER_PASS=$(grep "DB_PASSWORD=" .env | cut -d'=' -f2)
        log "INFO" "Using existing database user password"
    fi
    
    # Update .env for Docker networking and secure credentials
    sed -i 's/DB_HOST=.*/DB_HOST=database/' .env
    sed -i 's/REDIS_HOST=.*/REDIS_HOST=cache/' .env
    sed -i 's/CACHE_STORE=.*/CACHE_STORE=redis/' .env
    sed -i 's/SESSION_DRIVER=.*/SESSION_DRIVER=redis/' .env
    sed -i 's/QUEUE_CONNECTION=.*/QUEUE_CONNECTION=redis/' .env
    
    # Ensure database credentials are set
    sed -i "s/DB_ROOT_PASSWORD=.*/DB_ROOT_PASSWORD=${DB_ROOT_PASS}/" .env
    sed -i "s/DB_PASSWORD=.*/DB_PASSWORD=${DB_USER_PASS}/" .env
    sed -i 's/DB_DATABASE=.*/DB_DATABASE=genealogy/' .env
    sed -i 's/DB_USERNAME=.*/DB_USERNAME=genealogy_user/' .env
    
    log "SUCCESS" "Environment configured for Docker with secure credentials"
    
    # Step 3: Create Docker configurations
    log "INFO" "Step 3/6: Creating Docker configurations..."
    
    # Create MariaDB directory
    mkdir -p docker/mariadb/conf.d
    
    # Create MariaDB configuration
    cat > docker/mariadb/conf.d/genealogy.cnf << 'EOF'
[mariadb]
# Genealogy application optimizations for MariaDB
innodb_buffer_pool_size = 256M
innodb_log_file_size = 64M
innodb_flush_log_at_trx_commit = 2
innodb_file_per_table = 1
max_connections = 200
table_open_cache = 400
tmp_table_size = 64M
max_heap_table_size = 64M

# Character set
character-set-server = utf8mb4
collation-server = utf8mb4_unicode_ci

# Performance optimizations
query_cache_type = 1
query_cache_size = 32M
thread_cache_size = 8
key_buffer_size = 64M
EOF
    
    log "SUCCESS" "Docker configurations created"
    
    # Step 4: Start database and cache services
    log "INFO" "Step 4/6: Starting MariaDB and Redis services..."
    
    if log_command "docker compose up -d database cache" "Start database and cache services"; then
        log "SUCCESS" "Database and cache services started"
        
        # Wait for database to be ready
        log "INFO" "Waiting for MariaDB to be ready..."
        sleep 15
        
        # Check if database is responding
        for i in {1..30}; do
            if docker compose exec -T database mariadb -u root -p${DB_ROOT_PASS} -e "SELECT 1;" >/dev/null 2>&1; then
                log "SUCCESS" "MariaDB is ready"
                break
            fi
            if [[ $i -eq 30 ]]; then
                log "ERROR" "MariaDB failed to start properly"
                return 1
            fi
            sleep 2
        done
    else
        log "ERROR" "Failed to start database and cache services"
        return 1
    fi
    
    # Step 5: Build and start application
    log "INFO" "Step 5/6: Building and starting application..."
    
    if log_command "docker compose up -d --build app" "Build and start application service"; then
        log "SUCCESS" "Application service started"
        
        # Wait for app to be ready
        log "INFO" "Waiting for application to be ready..."
        sleep 20
        
        # Run migrations inside container
        log "INFO" "Running database migrations in container..."
        if log_command "docker compose exec -T app php artisan migrate:fresh --seed --force" "Database migration and seeding"; then
            log "SUCCESS" "Database setup completed"
        else
            log "WARNING" "Database setup had issues - trying once more..."
            sleep 10
            if log_command "docker compose exec -T app php artisan migrate:fresh --seed --force" "Retry database migration"; then
                log "SUCCESS" "Database setup completed on retry"
            else
                log "ERROR" "Database setup failed"
            fi
        fi
    else
        log "ERROR" "Failed to start application service"
        return 1
    fi
    
    # Step 6: Verify deployment
    log "INFO" "Step 6/6: Verifying Docker deployment..."
    
    # Check service status
    log_command "docker compose ps" "Check service status"
    
    # Test application response
    sleep 5
    if curl -f http://localhost:8080 >/dev/null 2>&1; then
        log "SUCCESS" "Application is responding"
    else
        log "WARNING" "Application may still be starting up"
    fi
    
    echo ""
    echo "🐳 DOCKER DEPLOYMENT COMPLETE!"
    echo "=============================="
    echo ""
    echo "✅ Your Laravel Genealogy Application is running in Docker!"
    echo ""
    echo "📋 Service Information:"
    echo "  • Application: http://localhost:8080"
    echo "  • MariaDB Database: localhost:3306"
    echo "  • Redis Cache: localhost:6379"
    echo ""
    echo "🔧 Useful Docker Commands:"
    echo "  • View logs: docker compose logs -f app"
    echo "  • Access app container: docker compose exec app bash"
    echo "  • View all services: docker compose ps"
    echo "  • Stop services: docker compose down"
    echo "  • Restart services: docker compose restart"
}

# Local setup function
local_setup() {
    log "INFO" "=== LOCAL DEVELOPMENT SETUP ==="
    
    # Step 1: Critical PHP extension check and fix
    log "INFO" "Step 1/7: Checking critical PHP extensions..."
    
    # Check for DOM extension first (most common issue with PHP 8.4 PPA)
    if ! php -r "new DOMDocument();" >/dev/null 2>&1; then
        log "WARNING" "DOM extension missing - this is critical for Laravel!"
        log "INFO" "Installing DOM and XML extensions..."
        
        PHP_VER=$(php -r "echo PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;")
        
        # Update package list
        log "INFO" "Updating package list..."
        sudo apt update -qq
        
        # Install DOM and XML extensions directly
        if sudo apt install -y "php${PHP_VER}-dom" "php${PHP_VER}-xml" "php${PHP_VER}-simplexml"; then
            log "SUCCESS" "DOM and XML extensions installed"
            
            # Restart PHP-FPM if running
            if systemctl is-active --quiet "php${PHP_VER}-fpm" 2>/dev/null; then
                log "INFO" "Restarting PHP-FPM..."
                sudo systemctl restart "php${PHP_VER}-fpm"
            fi
            
            # Test DOM functionality
            if php -r "new DOMDocument();" >/dev/null 2>&1; then
                log "SUCCESS" "DOM extension is now working"
            else
                log "ERROR" "DOM extension installation failed"
                exit 1
            fi
        else
            log "ERROR" "Failed to install DOM extension"
            exit 1
        fi
    else
        log "SUCCESS" "DOM extension is available"
    fi
    
    # Step 2: Install remaining PHP extensions
    log "INFO" "Step 2/7: Installing remaining PHP extensions..."
    if log_command "./scripts/php-dependency-installer.sh -y" "PHP extensions installation"; then
        log "SUCCESS" "PHP extensions installed"
    else
        log "WARNING" "PHP extension installation had issues (continuing...)"
    fi
    
    # Step 3: Fix Composer dependencies  
    log "INFO" "Step 3/7: Fixing Composer dependencies..."
    if log_command "./scripts/composer-dependency-fixer.sh" "Composer dependency fixing"; then
        log "SUCCESS" "Composer dependencies resolved"
    else
        log "ERROR" "Composer dependency resolution failed"
        
        # Try emergency Composer fix
        log "INFO" "Attempting emergency Composer recovery..."
        if log_command "composer clear-cache && composer install --ignore-platform-reqs --no-scripts" "Emergency Composer installation"; then
            log "WARNING" "Composer installed with platform requirement bypass"
            log "WARNING" "Some compatibility checks were skipped"
        else
            log "ERROR" "Composer installation failed completely"
            exit 1
        fi
    fi
    
    # Step 4: Setup Laravel environment
    log "INFO" "Step 4/7: Setting up Laravel environment..."
    
    # Copy environment file
    if [[ ! -f ".env" ]]; then
        cp .env.example .env
        log "SUCCESS" "Environment file created"
    else
        log "INFO" "Environment file already exists"
    fi
    
    # Configure for local development with Docker database
    sed -i 's/DB_HOST=.*/DB_HOST=127.0.0.1/' .env
    sed -i 's/REDIS_HOST=.*/REDIS_HOST=127.0.0.1/' .env
    
    # Test Laravel functionality before proceeding
    log "INFO" "Testing Laravel installation..."
    if php artisan --version >/dev/null 2>&1; then
        log "SUCCESS" "Laravel is functional"
    else
        log "ERROR" "Laravel installation appears broken"
        log "INFO" "This might be due to missing extensions or Composer issues"
        
        # Try to run artisan with more verbose error reporting
        log "INFO" "Attempting to diagnose Laravel issue..."
        php artisan --version 2>&1 | head -5
        exit 1
    fi
    
    # Generate application key
    if log_command "php artisan key:generate --force" "Laravel application key generation"; then
        log "SUCCESS" "Application key generated"
    else
        log "ERROR" "Failed to generate application key"
        exit 1
    fi
    
    # Create storage link
    if log_command "php artisan storage:link" "Laravel storage link creation"; then
        log "SUCCESS" "Storage linked"
    else
        log "INFO" "Storage link creation failed or already exists (continuing...)"
    fi
    
    # Step 5: Start MariaDB Docker container
    log "INFO" "Step 5/7: Starting MariaDB Docker container..."
    
    # Generate secure database credentials if not already set
    if ! grep -q "DB_ROOT_PASSWORD=" .env || [[ $(grep "DB_ROOT_PASSWORD=" .env | cut -d'=' -f2) == "" ]]; then
        DB_ROOT_PASS=$(openssl rand -base64 32 | tr -d "=+/" | cut -c1-25)
        sed -i "s/DB_ROOT_PASSWORD=.*/DB_ROOT_PASSWORD=${DB_ROOT_PASS}/" .env || echo "DB_ROOT_PASSWORD=${DB_ROOT_PASS}" >> .env
        log "INFO" "Generated secure database root password"
    else
        DB_ROOT_PASS=$(grep "DB_ROOT_PASSWORD=" .env | cut -d'=' -f2)
        log "INFO" "Using existing database root password"
    fi
    
    if ! grep -q "DB_PASSWORD=" .env || [[ $(grep "DB_PASSWORD=" .env | cut -d'=' -f2) == "" ]]; then
        DB_USER_PASS=$(openssl rand -base64 32 | tr -d "=+/" | cut -c1-25)
        sed -i "s/DB_PASSWORD=.*/DB_PASSWORD=${DB_USER_PASS}/" .env || echo "DB_PASSWORD=${DB_USER_PASS}" >> .env
        log "INFO" "Generated secure database user password"
    else
        DB_USER_PASS=$(grep "DB_PASSWORD=" .env | cut -d'=' -f2)
        log "INFO" "Using existing database user password"
    fi
    
    # Stop any existing database containers
    for container in genealogy-mysql genealogy-mariadb; do
        if docker ps -q -f name=$container | grep -q .; then
            log "INFO" "Stopping existing $container container..."
            docker stop $container && docker rm $container
        fi
    done
    
    # Start MariaDB container with secure credentials
    if log_command "docker run -d --name genealogy-mariadb -e MARIADB_ROOT_PASSWORD=${DB_ROOT_PASS} -e MARIADB_DATABASE=genealogy -e MARIADB_USER=genealogy_user -e MARIADB_PASSWORD=${DB_USER_PASS} -p 3306:3306 mariadb:11.4" "Start MariaDB container"; then
        log "SUCCESS" "MariaDB container started"
        
        # Wait for database to be ready
        log "INFO" "Waiting for MariaDB to be ready..."
        sleep 15
        
        # Test database connection
        for i in {1..30}; do
            if docker exec genealogy-mariadb mariadb -u root -p${DB_ROOT_PASS} -e "SELECT 1;" >/dev/null 2>&1; then
                log "SUCCESS" "MariaDB is ready"
                break
            fi
            if [[ $i -eq 30 ]]; then
                log "ERROR" "MariaDB failed to start properly"
                exit 1
            fi
            sleep 2
        done
    else
        log "ERROR" "Failed to start MariaDB container"
        exit 1
    fi
    
    # Step 6: Configure database
    log "INFO" "Step 6/7: Configuring database..."
    
    # Run migrations and seeders
    log "INFO" "Running database migrations..."
    if log_command "php artisan migrate:fresh --seed --force" "Database migration and seeding"; then
        log "SUCCESS" "Database setup completed"
    else
        log "WARNING" "Database setup had issues (you may need to configure database manually)"
    fi
    
    # Step 7: Install frontend dependencies
    log "INFO" "Step 7/7: Installing frontend dependencies..."
    
    if command -v npm >/dev/null 2>&1; then
        log "INFO" "Installing Node.js dependencies..."
        if log_command "npm install" "Node.js dependencies installation"; then
            log "SUCCESS" "Node.js dependencies installed"
            
            log "INFO" "Building frontend assets..."
            if log_command "npm run build" "Frontend assets compilation"; then
                log "SUCCESS" "Frontend assets built successfully"
            else
                log "WARNING" "Frontend build failed - but continuing..."
            fi
        else
            log "WARNING" "npm install failed - but continuing..."
        fi
    else
        log "WARNING" "npm not found - frontend dependencies not installed"
        log "INFO" "Install Node.js and run: npm install && npm run build"
    fi
    
    echo ""
    echo "🖥️  LOCAL DEVELOPMENT SETUP COMPLETE!"
    echo "====================================="
    echo ""
    echo "✅ Your Laravel Genealogy Application is ready!"
    echo ""
    echo "📋 Service Information:"
    echo "  • Application: http://localhost:8000 (after running 'php artisan serve')"
    echo "  • MariaDB Database: localhost:3306"
    echo ""
    echo "🔧 Next Steps:"
    echo "  1. Start the development server: php artisan serve"
    echo "  2. Visit http://localhost:8000 in your browser"
    echo "  3. Register a new account to start using the application"
    echo ""
    echo "🔧 Useful Commands:"
    echo "  • Start server: php artisan serve"
    echo "  • View database logs: docker logs genealogy-mariadb"
    echo "  • Stop database: docker stop genealogy-mariadb"
    echo "  • Start database: docker start genealogy-mariadb"
}

echo "🚀 Laravel Genealogy Application - Quick Setup"
echo "==============================================="
echo ""
echo "Choose your deployment method:"
echo ""
echo "  1. 🐳 Docker Deployment (Recommended)"
echo "     • Complete containerized setup with MariaDB & Redis"
echo "     • No local dependencies required"
echo "     • Production-ready configuration"
echo ""
echo "  2. 🖥️  Local Development Setup"
echo "     • Install PHP extensions & dependencies locally"
echo "     • Use local PHP with Docker database"
echo "     • Good for development and testing"
echo ""

read -p "Select deployment method [1=Docker, 2=Local]: " -n 1 -r
echo ""

DEPLOYMENT_METHOD=""
if [[ $REPLY =~ ^[1]$ ]]; then
    DEPLOYMENT_METHOD="docker"
    echo "🐳 Selected: Docker Deployment"
elif [[ $REPLY =~ ^[2]$ ]]; then
    DEPLOYMENT_METHOD="local"
    echo "🖥️  Selected: Local Development Setup"
else
    log "INFO" "Invalid selection. Defaulting to Docker deployment."
    DEPLOYMENT_METHOD="docker"
fi

echo ""
read -p "Continue with $DEPLOYMENT_METHOD setup? [Y/n] " -n 1 -r
echo ""

if [[ $REPLY =~ ^[Nn]$ ]]; then
    log "INFO" "Setup cancelled by user"
    exit 0
fi

# Check requirements based on deployment method
if [[ "$DEPLOYMENT_METHOD" == "docker" ]]; then
    # Check Docker requirements
    log "INFO" "Checking Docker requirements..."
    
    if ! command -v docker >/dev/null 2>&1; then
        log "ERROR" "Docker not found. Please install Docker first."
        log "INFO" "Visit: https://docs.docker.com/get-docker/"
        exit 1
    fi
    
    if ! docker compose version >/dev/null 2>&1; then
        log "ERROR" "Docker Compose not found. Please install Docker Compose plugin."
        exit 1
    fi
    
    DOCKER_VERSION=$(docker --version | grep -oP 'Docker version \K[0-9.]+')
    COMPOSE_VERSION=$(docker compose version --short)
    log "SUCCESS" "Docker $DOCKER_VERSION and Compose $COMPOSE_VERSION detected"
    
else
    # Check sudo access for local setup
    log "INFO" "Checking sudo access for local setup..."
    if sudo -v; then
        log "SUCCESS" "Sudo access confirmed"
    else
        log "ERROR" "Sudo access required to install PHP extensions"
        log "INFO" "Please ensure your user has sudo privileges"
        exit 1
    fi
fi

echo ""
echo "📝 Logs will be saved to:"
echo "   Main log: $SETUP_LOG_FILE"
echo "   Error log: $ERROR_LOG_FILE"
echo ""

log "INFO" "Starting automatic setup..."

# Log system information
log_system_info

echo ""

# Route to appropriate setup method
if [[ "$DEPLOYMENT_METHOD" == "docker" ]]; then
    log "INFO" "Starting Docker deployment setup..."
    
    # Docker-specific setup steps
    docker_setup
    
else
    log "INFO" "Starting local development setup..."
    
    # Local setup steps
    local_setup
fi