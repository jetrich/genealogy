#!/bin/bash
# =============================================================================
# Production Environment Deployment Script for Laravel Genealogy Application
# =============================================================================
# Secure production deployment with optimizations and monitoring

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

# Configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"
COMPOSE_FILE="$PROJECT_ROOT/docker-compose.yml"
ENV_FILE="$PROJECT_ROOT/.env"
BACKUP_DIR="${BACKUP_DIR:-/var/backups/genealogy}"
DEPLOY_USER="${DEPLOY_USER:-www-data}"

# Production specific environment variables
export ENVIRONMENT=production
export DOCKER_TARGET=production
export APP_ENV=production
export APP_DEBUG=false
export COMPOSE_PROFILES=production

main() {
    log_info "Starting Laravel Genealogy Production Environment Deployment..."
    
    cd "$PROJECT_ROOT"
    
    # Security and prerequisite checks
    check_prerequisites
    security_checks
    
    # Backup existing data
    backup_existing_data
    
    # Setup production environment
    setup_production_env
    
    # Build and deploy services
    build_and_deploy_services
    
    # Initialize and optimize Laravel
    initialize_laravel_production
    
    # Setup monitoring and security
    setup_monitoring
    setup_security
    
    # Health checks
    run_health_checks
    
    # Display production information
    display_production_info
    
    log_success "Production deployment completed successfully!"
}

check_prerequisites() {
    log_info "Checking production prerequisites..."
    
    # Check if running as appropriate user
    if [[ $EUID -eq 0 ]]; then
        log_warning "Running as root. Consider using a dedicated deployment user."
    fi
    
    # Check Docker
    if ! command -v docker >/dev/null 2>&1; then
        log_error "Docker is not installed"
        exit 1
    fi
    
    # Check Docker Compose
    if ! command -v docker-compose >/dev/null 2>&1; then
        log_error "Docker Compose is not installed"
        exit 1
    fi
    
    # Check SSL certificates
    if [[ -d "$PROJECT_ROOT/docker/ssl" ]]; then
        log_info "SSL certificates directory found"
    else
        log_warning "SSL certificates not found. HTTPS will not be available."
    fi
    
    # Check available disk space
    local available_space=$(df / | awk 'NR==2 {print $4}')
    if [[ $available_space -lt 1048576 ]]; then  # Less than 1GB
        log_warning "Less than 1GB disk space available"
    fi
    
    # Check available memory
    local available_memory=$(free -m | awk 'NR==2{printf "%.0f", $7}')
    if [[ $available_memory -lt 512 ]]; then  # Less than 512MB
        log_warning "Less than 512MB memory available"
    fi
    
    log_success "Prerequisites check completed"
}

security_checks() {
    log_info "Performing security checks..."
    
    # Check environment file permissions
    if [[ -f "$ENV_FILE" ]]; then
        local env_perms=$(stat -c "%a" "$ENV_FILE")
        if [[ "$env_perms" != "600" ]]; then
            log_warning "Environment file has insecure permissions ($env_perms). Setting to 600."
            chmod 600 "$ENV_FILE"
        fi
    fi
    
    # Check for default passwords
    if grep -q "password\|secret\|key" "$ENV_FILE" 2>/dev/null; then
        if grep -q "password123\|secret123\|default" "$ENV_FILE" 2>/dev/null; then
            log_error "Default passwords detected in .env file. Please change them."
            exit 1
        fi
    fi
    
    # Check APP_KEY is set
    if ! grep -q "^APP_KEY=base64:" "$ENV_FILE" 2>/dev/null; then
        log_error "APP_KEY is not set. Run 'php artisan key:generate' first."
        exit 1
    fi
    
    log_success "Security checks completed"
}

backup_existing_data() {
    log_info "Creating backup of existing data..."
    
    # Create backup directory
    mkdir -p "$BACKUP_DIR/$(date +%Y%m%d_%H%M%S)"
    local backup_path="$BACKUP_DIR/$(date +%Y%m%d_%H%M%S)"
    
    # Backup database if running
    if docker-compose ps | grep -q "database.*Up"; then
        log_info "Backing up existing database..."
        docker-compose exec -T database mysqldump -u root -p"$DB_PASSWORD" genealogy > "$backup_path/database_backup.sql" || true
    fi
    
    # Backup storage data
    if [[ -d "$PROJECT_ROOT/storage/app/public" ]]; then
        log_info "Backing up storage files..."
        tar -czf "$backup_path/storage_backup.tar.gz" -C "$PROJECT_ROOT" storage/app/public || true
    fi
    
    # Backup environment file
    if [[ -f "$ENV_FILE" ]]; then
        cp "$ENV_FILE" "$backup_path/.env.backup"
    fi
    
    log_success "Backup completed: $backup_path"
}

setup_production_env() {
    log_info "Setting up production environment..."
    
    # Ensure .env file exists
    if [[ ! -f "$ENV_FILE" ]]; then
        log_error ".env file not found. Please create it from .env.example"
        exit 1
    fi
    
    # Set production-specific environment variables
    sed -i 's/APP_ENV=.*/APP_ENV=production/' "$ENV_FILE"
    sed -i 's/APP_DEBUG=.*/APP_DEBUG=false/' "$ENV_FILE"
    sed -i 's/LOG_LEVEL=.*/LOG_LEVEL=error/' "$ENV_FILE"
    
    # Ensure secure session settings
    if ! grep -q "SESSION_SECURE_COOKIE" "$ENV_FILE"; then
        echo "SESSION_SECURE_COOKIE=true" >> "$ENV_FILE"
    fi
    
    # Set proper file permissions
    find "$PROJECT_ROOT" -type f -exec chmod 644 {} \;
    find "$PROJECT_ROOT" -type d -exec chmod 755 {} \;
    chmod -R 775 "$PROJECT_ROOT/storage"
    chmod -R 775 "$PROJECT_ROOT/bootstrap/cache"
    chmod 600 "$ENV_FILE"
    
    log_success "Production environment setup completed"
}

build_and_deploy_services() {
    log_info "Building and deploying production services..."
    
    # Stop existing services
    docker-compose down --remove-orphans 2>/dev/null || true
    
    # Pull latest base images
    log_info "Pulling latest base images..."
    docker-compose pull database cache nginx
    
    # Build production image
    log_info "Building production Docker image..."
    docker-compose build --build-arg ENVIRONMENT=production --no-cache app
    
    # Start database and cache first
    log_info "Starting database and cache services..."
    docker-compose up -d database cache
    
    # Wait for services
    wait_for_service "database" "mysqladmin ping -h database -u root -p\$MYSQL_ROOT_PASSWORD" 60
    wait_for_service "cache" "redis-cli -h cache ping" 30
    
    # Start application services
    log_info "Starting application services..."
    docker-compose --profile production up -d
    
    # Start monitoring services if available
    if docker-compose config --services | grep -q monitoring; then
        log_info "Starting monitoring services..."
        docker-compose --profile monitoring up -d
    fi
    
    log_success "All services deployed successfully"
}

wait_for_service() {
    local service_name="$1"
    local health_check="$2"
    local timeout="$3"
    local counter=0
    
    log_info "Waiting for $service_name to be ready..."
    
    while ! docker-compose exec -T "$service_name" sh -c "$health_check" >/dev/null 2>&1; do
        counter=$((counter + 1))
        if [[ $counter -gt $timeout ]]; then
            log_error "$service_name failed to start within $timeout seconds"
            docker-compose logs "$service_name"
            exit 1
        fi
        sleep 1
    done
    
    log_success "$service_name is ready"
}

initialize_laravel_production() {
    log_info "Initializing Laravel for production..."
    
    # Run Laravel initialization
    docker-compose exec -T app bash -c "cd /var/www/html && ./scripts/automation/laravel-init.sh --environment=production"
    
    # Additional production optimizations
    log_info "Applying production optimizations..."
    docker-compose exec -T app php artisan config:cache
    docker-compose exec -T app php artisan route:cache
    docker-compose exec -T app php artisan view:cache
    docker-compose exec -T app php artisan event:cache
    
    # Optimize autoloader
    docker-compose exec -T app composer dump-autoload --optimize --classmap-authoritative
    
    # Generate sitemap if available
    docker-compose exec -T app php artisan sitemap:generate 2>/dev/null || true
    
    log_success "Laravel production initialization completed"
}

setup_monitoring() {
    log_info "Setting up monitoring and logging..."
    
    # Setup log rotation
    cat > /etc/logrotate.d/genealogy << EOF
/var/log/genealogy/*.log {
    daily
    missingok
    rotate 52
    compress
    delaycompress
    notifempty
    create 644 www-data www-data
    postrotate
        docker-compose restart app
    endscript
}
EOF

    # Setup monitoring alerts
    setup_health_monitoring
    
    log_success "Monitoring setup completed"
}

setup_health_monitoring() {
    log_info "Setting up health monitoring..."
    
    # Create health check script
    cat > "$PROJECT_ROOT/scripts/automation/health-check.sh" << 'EOF'
#!/bin/bash
# Health check script for Laravel Genealogy Application

HEALTH_URL="http://localhost/health"
LOG_FILE="/var/log/genealogy/health-check.log"

# Function to log with timestamp
log_with_timestamp() {
    echo "$(date '+%Y-%m-%d %H:%M:%S') - $1" >> "$LOG_FILE"
}

# Check application health
if curl -f -s "$HEALTH_URL" >/dev/null; then
    log_with_timestamp "Health check passed"
else
    log_with_timestamp "Health check failed - Application may be down"
    # Send alert (implement your notification system here)
    # systemctl restart genealogy || true
fi

# Check disk space
DISK_USAGE=$(df / | awk 'NR==2 {print $5}' | sed 's/%//')
if [[ $DISK_USAGE -gt 90 ]]; then
    log_with_timestamp "WARNING: Disk usage is ${DISK_USAGE}%"
fi

# Check memory usage
MEMORY_USAGE=$(free | awk 'NR==2{printf "%.0f", $3*100/$2}')
if [[ $MEMORY_USAGE -gt 90 ]]; then
    log_with_timestamp "WARNING: Memory usage is ${MEMORY_USAGE}%"
fi
EOF

    chmod +x "$PROJECT_ROOT/scripts/automation/health-check.sh"
    
    # Add to crontab
    (crontab -l 2>/dev/null || true; echo "*/5 * * * * $PROJECT_ROOT/scripts/automation/health-check.sh") | sort -u | crontab -
    
    log_success "Health monitoring configured"
}

setup_security() {
    log_info "Setting up security measures..."
    
    # Setup firewall rules (example for ufw)
    if command -v ufw >/dev/null 2>&1; then
        ufw allow 22/tcp    # SSH
        ufw allow 80/tcp    # HTTP
        ufw allow 443/tcp   # HTTPS
        # Block direct access to database and cache ports from external
        ufw deny 3306/tcp
        ufw deny 6379/tcp
    fi
    
    # Setup fail2ban for SSH protection
    if command -v fail2ban-client >/dev/null 2>&1; then
        systemctl enable fail2ban
        systemctl start fail2ban
    fi
    
    # Set up SSL certificate renewal (if using Let's Encrypt)
    setup_ssl_renewal
    
    log_success "Security measures configured"
}

setup_ssl_renewal() {
    if command -v certbot >/dev/null 2>&1; then
        log_info "Setting up SSL certificate auto-renewal..."
        
        # Add certificate renewal to crontab
        (crontab -l 2>/dev/null || true; echo "0 12 * * * /usr/bin/certbot renew --quiet && docker-compose restart nginx") | sort -u | crontab -
        
        log_success "SSL auto-renewal configured"
    fi
}

run_health_checks() {
    log_info "Running production health checks..."
    
    # Check application response
    local max_attempts=30
    local attempt=0
    
    while [[ $attempt -lt $max_attempts ]]; do
        if curl -f -s http://localhost/health >/dev/null; then
            log_success "Application health check passed"
            break
        fi
        
        attempt=$((attempt + 1))
        if [[ $attempt -eq $max_attempts ]]; then
            log_error "Application health check failed after $max_attempts attempts"
            exit 1
        fi
        
        log_info "Waiting for application to be ready... (attempt $attempt/$max_attempts)"
        sleep 5
    done
    
    # Check database connectivity
    if docker-compose exec -T app php artisan migrate:status >/dev/null 2>&1; then
        log_success "Database connectivity check passed"
    else
        log_error "Database connectivity check failed"
        exit 1
    fi
    
    # Check Redis connectivity
    if docker-compose exec -T cache redis-cli ping | grep -q PONG; then
        log_success "Redis connectivity check passed"
    else
        log_error "Redis connectivity check failed"
        exit 1
    fi
    
    # Check queue workers
    if docker-compose ps | grep -q "queue-worker.*Up"; then
        log_success "Queue workers are running"
    else
        log_warning "Queue workers may not be running properly"
    fi
    
    log_success "All health checks completed"
}

display_production_info() {
    log_info "Production Deployment Information:"
    echo
    echo "🚀 Application Status:"
    echo "   Environment: Production"
    echo "   Debug Mode: Disabled"
    echo "   Application URL: $(grep APP_URL "$ENV_FILE" | cut -d'=' -f2 || echo 'Not configured')"
    echo
    echo "🔧 Services Status:"
    docker-compose ps
    echo
    echo "📊 System Resources:"
    echo "   Disk Usage: $(df -h / | awk 'NR==2 {print $5}')"
    echo "   Memory Usage: $(free -h | awk 'NR==2{printf "%.1f%%", $3*100/$2}')"
    echo "   Load Average: $(uptime | awk -F'load average:' '{print $2}')"
    echo
    echo "🗂️  Important Paths:"
    echo "   Application: $PROJECT_ROOT"
    echo "   Backups: $BACKUP_DIR"
    echo "   Logs: docker-compose logs [service]"
    echo
    echo "🔒 Security:"
    echo "   SSL Status: $(if [[ -d "$PROJECT_ROOT/docker/ssl" ]]; then echo "Configured"; else echo "Not configured"; fi)"
    echo "   Firewall: $(if command -v ufw >/dev/null 2>&1; then ufw status; else echo "Not configured"; fi)"
    echo
    echo "📝 Monitoring:"
    echo "   Health checks: Configured (every 5 minutes)"
    echo "   Log rotation: Configured"
    echo "   Disk monitoring: Enabled"
    echo
    echo "🚨 Emergency Commands:"
    echo "   View logs: docker-compose logs -f [service]"
    echo "   Restart app: docker-compose restart app"
    echo "   Full restart: docker-compose down && docker-compose up -d"
    echo "   Backup now: php artisan backup:run"
    echo "   Rollback: Restore from $BACKUP_DIR"
}

# Handle script arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        --skip-backup)
            SKIP_BACKUP=true
            shift
            ;;
        --force)
            FORCE=true
            shift
            ;;
        --help)
            echo "Laravel Genealogy Production Deployment Script"
            echo
            echo "Usage: $0 [options]"
            echo
            echo "Options:"
            echo "  --skip-backup  Skip backup creation"
            echo "  --force        Force deployment without confirmations"
            echo "  --help         Show this help message"
            exit 0
            ;;
        *)
            log_error "Unknown option: $1"
            echo "Use --help for usage information"
            exit 1
            ;;
    esac
done

# Production deployment confirmation
if [[ "${FORCE:-false}" != "true" ]]; then
    echo
    log_warning "You are about to deploy to PRODUCTION environment."
    read -p "Are you sure you want to continue? (yes/no): " -r
    if [[ ! $REPLY =~ ^[Yy][Ee][Ss]$ ]]; then
        log_info "Deployment cancelled"
        exit 0
    fi
fi

# Execute main function
main "$@"