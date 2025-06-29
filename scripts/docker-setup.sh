#!/bin/bash

# Docker Setup Script for Laravel Genealogy Application
# Complete containerized deployment with MySQL, Redis, and Laravel app

set -euo pipefail

# Setup logging
SETUP_LOG_DIR="logs/setup"
SETUP_LOG_FILE="$SETUP_LOG_DIR/docker-setup-$(date +%Y%m%d_%H%M%S).log"
ERROR_LOG_FILE="$SETUP_LOG_DIR/docker-errors-$(date +%Y%m%d_%H%M%S).log"

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

echo "🐳 Laravel Genealogy Application - Docker Setup"
echo "================================================"
echo ""
echo "This script will:"
echo "  1. Stop any existing containers"
echo "  2. Update .env for Docker configuration"
echo "  3. Build the Laravel application image"
echo "  4. Start all services (MySQL, Redis, Laravel)"
echo "  5. Run database migrations"
echo "  6. Verify deployment"
echo ""
echo "📝 Logs will be saved to:"
echo "   Main log: $SETUP_LOG_FILE"
echo "   Error log: $ERROR_LOG_FILE"
echo ""

read -p "Continue with Docker setup? [Y/n] " -n 1 -r
echo ""

if [[ $REPLY =~ ^[Nn]$ ]]; then
    log "INFO" "Docker setup cancelled by user"
    exit 0
fi

log "INFO" "Starting Docker deployment setup..."

# Step 1: Check Docker and Docker Compose
log "INFO" "Step 1/8: Verifying Docker installation..."

if ! command -v docker >/dev/null 2>&1; then
    log "ERROR" "Docker not found. Please install Docker first."
    exit 1
fi

if ! docker compose version >/dev/null 2>&1; then
    log "ERROR" "Docker Compose not found. Please install Docker Compose plugin."
    exit 1
fi

DOCKER_VERSION=$(docker --version | grep -oP 'Docker version \K[0-9.]+')
COMPOSE_VERSION=$(docker compose version --short)
log "SUCCESS" "Docker $DOCKER_VERSION and Compose $COMPOSE_VERSION detected"

# Step 2: Stop existing containers
log "INFO" "Step 2/8: Stopping existing containers..."

# Stop the standalone MySQL container if running
if docker ps -q -f name=genealogy-mysql | grep -q .; then
    log "INFO" "Stopping standalone MySQL container..."
    log_command "docker stop genealogy-mysql && docker rm genealogy-mysql" "Stop standalone MySQL container"
fi

# Stop any existing compose services
if docker compose ps -q | grep -q .; then
    log "INFO" "Stopping existing Docker Compose services..."
    log_command "docker compose down" "Stop existing services"
fi

# Step 3: Update .env for Docker configuration
log "INFO" "Step 3/8: Configuring environment for Docker..."

# Backup current .env
cp .env .env.backup.$(date +%Y%m%d_%H%M%S)

# Update .env for Docker configuration
cat > .env.docker << 'EOF'
APP_NAME=Genealogy
APP_ENV=local
APP_KEY=base64:bnEDr6sy2qxzpbIFhbEEUSp7OoLIPF5B6sFbgpv8TW4=
APP_DEBUG=true
APP_URL=http://localhost

APP_LOCALE=en
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=en_US

APP_MAINTENANCE_DRIVER=file
APP_MAINTENANCE_STORE=database

BCRYPT_ROUNDS=12

LOG_CHANNEL=daily
LOG_STACK=single
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=debug

# Docker Database Configuration
DB_CONNECTION=mysql
DB_HOST=database
DB_PORT=3306
DB_DATABASE=genealogy
DB_USERNAME=genealogy_user
DB_PASSWORD=genealogy_pass
DB_ROOT_PASSWORD=genealogy_root_pass

# Docker Cache and Session Configuration
SESSION_DRIVER=redis
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=null

BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local
QUEUE_CONNECTION=redis

CACHE_STORE=redis
CACHE_PREFIX=

MEMCACHED_HOST=127.0.0.1

# Docker Redis Configuration
REDIS_CLIENT=phpredis
REDIS_HOST=cache
REDIS_PASSWORD=null
REDIS_PORT=6379

MAIL_MAILER=log
MAIL_HOST=127.0.0.1
MAIL_PORT=2525
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS="noreply@yourdomain.com"
MAIL_FROM_NAME="${APP_NAME}"

AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=
AWS_USE_PATH_STYLE_ENDPOINT=false

VITE_APP_NAME="${APP_NAME}"

# --------------------------------------------------------
# custom
# --------------------------------------------------------
BACKUP_DISK="backups"
BACKUP_DAILY_CLEANUP="22:30"
BACKUP_DAILY_RUN="23:00"
BACKUP_MAIL_ADDRESS="webmaster@yourdomain.com"
BACKUP_DUMP_PATH=

# Docker specific
APP_PORT=8080
DOCKER_TARGET=development
ENVIRONMENT=development
EOF

# Replace current .env with Docker configuration
mv .env.docker .env

log "SUCCESS" "Environment configured for Docker deployment"

# Step 4: Create necessary directories
log "INFO" "Step 4/8: Creating required directories..."

directories=(
    "docker/mysql/conf.d"
    "docker/mysql/init"
    "docker/redis"
    "docker/nginx/conf.d"
    "docker/nginx"
    "docker/php"
    "docker/supervisor"
    "docker/ssl"
    "storage/app/public/photos"
    "storage/app/public/gedcom"
    "storage/app/backups"
    "storage/logs"
)

for dir in "${directories[@]}"; do
    mkdir -p "$dir"
done

log "SUCCESS" "Required directories created"

# Step 5: Create Docker configuration files
log "INFO" "Step 5/8: Creating Docker configuration files..."

# Create MySQL configuration
cat > docker/mysql/conf.d/genealogy.cnf << 'EOF'
[mysqld]
# Genealogy application optimizations
innodb_buffer_pool_size = 256M
innodb_log_file_size = 64M
innodb_flush_log_at_trx_commit = 2
innodb_file_per_table = 1
max_connections = 200
table_open_cache = 400
query_cache_size = 32M
tmp_table_size = 64M
max_heap_table_size = 64M

# Character set
character-set-server = utf8mb4
collation-server = utf8mb4_unicode_ci

# Performance Schema
performance_schema = ON
EOF

# Create Redis configuration
cat > docker/redis/redis.conf << 'EOF'
# Redis configuration for Laravel Genealogy
bind 0.0.0.0
port 6379
timeout 0
databases 16
save 900 1
save 300 10
save 60 10000
rdbcompression yes
dbfilename dump.rdb
dir /data
maxmemory 256mb
maxmemory-policy allkeys-lru
EOF

# Create Nginx configuration
cat > docker/nginx/default.conf << 'EOF'
server {
    listen 80;
    server_name localhost;
    root /var/www/html/public;
    index index.php index.html;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.ht {
        deny all;
    }

    # Health check endpoint
    location /health {
        access_log off;
        return 200 "healthy\n";
        add_header Content-Type text/plain;
    }
}
EOF

# Create PHP development configuration
cat > docker/php/php-dev.ini << 'EOF'
[PHP]
memory_limit = 512M
upload_max_filesize = 50M
post_max_size = 50M
max_execution_time = 300
max_input_vars = 3000
display_errors = On
error_reporting = E_ALL
log_errors = On
error_log = /var/log/php_errors.log

[Date]
date.timezone = UTC

[opcache]
opcache.enable = 1
opcache.memory_consumption = 128
opcache.interned_strings_buffer = 8
opcache.max_accelerated_files = 4000
opcache.revalidate_freq = 2
opcache.fast_shutdown = 1

[xdebug]
xdebug.mode = debug
xdebug.client_host = host.docker.internal
xdebug.client_port = 9003
EOF

# Create supervisor configuration
cat > docker/supervisor/supervisord.conf << 'EOF'
[unix_http_server]
file=/var/run/supervisor.sock
chmod=0700

[supervisord]
logfile=/var/log/supervisor/supervisord.log
pidfile=/var/run/supervisord.pid
childlogdir=/var/log/supervisor/
nodaemon=true
user=root

[rpcinterface:supervisor]
supervisor.rpcinterface_factory = supervisor.rpcinterface:make_main_rpcinterface

[supervisorctl]
serverurl=unix:///var/run/supervisor.sock

[program:nginx]
command=nginx -g "daemon off;"
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0
autorestart=true
startretries=3

[program:php-fpm]
command=php-fpm
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0
autorestart=true
startretries=3
EOF

log "SUCCESS" "Docker configuration files created"

# Step 6: Build and start services
log "INFO" "Step 6/8: Building and starting Docker services..."

if log_command "docker compose up -d --build database cache" "Start database and cache services"; then
    log "SUCCESS" "Database and cache services started"
else
    log "ERROR" "Failed to start database and cache services"
    exit 1
fi

# Wait for database to be ready
log "INFO" "Waiting for database to be ready..."
sleep 10

if log_command "docker compose up -d --build app" "Build and start application service"; then
    log "SUCCESS" "Application service started"
else
    log "ERROR" "Failed to start application service"
    exit 1
fi

# Step 7: Run database migrations
log "INFO" "Step 7/8: Running database migrations..."

# Wait for app to be ready
sleep 15

if log_command "docker compose exec -T app php artisan migrate:fresh --seed --force" "Database migration and seeding"; then
    log "SUCCESS" "Database setup completed"
else
    log "WARNING" "Database setup had issues - checking if app is ready..."
    
    # Try to wait longer and retry
    sleep 30
    if log_command "docker compose exec -T app php artisan migrate:fresh --seed --force" "Retry database migration"; then
        log "SUCCESS" "Database setup completed on retry"
    else
        log "ERROR" "Database setup failed - manual intervention may be required"
    fi
fi

# Step 8: Verify deployment
log "INFO" "Step 8/8: Verifying deployment..."

# Check service status
if log_command "docker compose ps" "Check service status"; then
    log "SUCCESS" "Service status checked"
fi

# Test application response
sleep 5
if curl -f http://localhost:8080/health >/dev/null 2>&1; then
    log "SUCCESS" "Application health check passed"
else
    log "WARNING" "Application health check failed - service may still be starting"
fi

echo ""
echo "🎉 DOCKER DEPLOYMENT COMPLETE!"
echo "=============================="
echo ""
echo "✅ Your Laravel Genealogy Application is running in Docker!"
echo ""
echo "📋 Service Information:"
echo "  • Application: http://localhost:8080"
echo "  • MySQL Database: localhost:3306"
echo "  • Redis Cache: localhost:6379"
echo ""
echo "🔧 Useful Docker Commands:"
echo "  • View logs: docker compose logs -f app"
echo "  • Access app container: docker compose exec app bash"
echo "  • View all services: docker compose ps"
echo "  • Stop services: docker compose down"
echo "  • Restart services: docker compose restart"
echo ""
echo "📝 Setup Logs:"
echo "  • Full log: $SETUP_LOG_FILE"
if [[ -f "$ERROR_LOG_FILE" && -s "$ERROR_LOG_FILE" ]]; then
    echo "  • Error log: $ERROR_LOG_FILE"
    echo ""
    echo "⚠️  Some errors occurred during setup. Review the error log for details."
else
    echo "  • No errors detected during setup"
fi
echo ""

log "SUCCESS" "Docker deployment completed successfully!"