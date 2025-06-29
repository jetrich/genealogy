#!/bin/bash
# =============================================================================
# Development Environment Deployment Script for Laravel Genealogy Application
# =============================================================================
# Sets up a complete development environment with hot reloading and debugging

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

# Development specific environment variables
export ENVIRONMENT=development
export DOCKER_TARGET=development
export APP_ENV=local
export APP_DEBUG=true
export COMPOSE_PROFILES=development

main() {
    log_info "Starting Laravel Genealogy Development Environment Deployment..."
    
    cd "$PROJECT_ROOT"
    
    # Check prerequisites
    check_prerequisites
    
    # Setup development environment
    setup_development_env
    
    # Build and start services
    build_and_start_services
    
    # Initialize Laravel application
    initialize_laravel
    
    # Setup development tools
    setup_development_tools
    
    # Display information
    display_development_info
    
    log_success "Development environment deployment completed!"
}

check_prerequisites() {
    log_info "Checking prerequisites..."
    
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
    
    # Check if ports are available
    local ports=(3306 6379 8000 5173 80)
    for port in "${ports[@]}"; do
        if lsof -i ":$port" >/dev/null 2>&1; then
            log_warning "Port $port is already in use"
        fi
    done
    
    log_success "Prerequisites check completed"
}

setup_development_env() {
    log_info "Setting up development environment..."
    
    # Create .env file if it doesn't exist
    if [[ ! -f "$ENV_FILE" ]]; then
        cp "$PROJECT_ROOT/.env.example" "$ENV_FILE"
        log_info "Created .env file from example"
    fi
    
    # Set development-specific environment variables
    sed -i 's/APP_ENV=.*/APP_ENV=local/' "$ENV_FILE"
    sed -i 's/APP_DEBUG=.*/APP_DEBUG=true/' "$ENV_FILE"
    sed -i 's/LOG_LEVEL=.*/LOG_LEVEL=debug/' "$ENV_FILE"
    
    # Database configuration for Docker
    sed -i 's/DB_HOST=.*/DB_HOST=database/' "$ENV_FILE"
    sed -i 's/DB_PORT=.*/DB_PORT=3306/' "$ENV_FILE"
    sed -i 's/DB_DATABASE=.*/DB_DATABASE=genealogy/' "$ENV_FILE"
    sed -i 's/DB_USERNAME=.*/DB_USERNAME=genealogy_user/' "$ENV_FILE"
    sed -i 's/DB_PASSWORD=.*/DB_PASSWORD=genealogy_pass/' "$ENV_FILE"
    
    # Cache and session configuration
    sed -i 's/CACHE_STORE=.*/CACHE_STORE=redis/' "$ENV_FILE"
    sed -i 's/SESSION_DRIVER=.*/SESSION_DRIVER=redis/' "$ENV_FILE"
    sed -i 's/QUEUE_CONNECTION=.*/QUEUE_CONNECTION=redis/' "$ENV_FILE"
    
    # Redis configuration
    sed -i 's/REDIS_HOST=.*/REDIS_HOST=cache/' "$ENV_FILE"
    sed -i 's/REDIS_PORT=.*/REDIS_PORT=6379/' "$ENV_FILE"
    
    log_success "Development environment configuration completed"
}

build_and_start_services() {
    log_info "Building and starting Docker services..."
    
    # Stop any existing containers
    docker-compose down --remove-orphans 2>/dev/null || true
    
    # Build development image
    log_info "Building development Docker image..."
    docker-compose build --build-arg ENVIRONMENT=development app
    
    # Start core services
    log_info "Starting core services (database, cache)..."
    docker-compose up -d database cache
    
    # Wait for services to be ready
    log_info "Waiting for services to be ready..."
    wait_for_service "database" "mysqladmin ping -h database -u root -pgenealogy_pass" 60
    wait_for_service "cache" "redis-cli -h cache ping" 30
    
    # Start application services
    log_info "Starting application services..."
    docker-compose --profile development up -d
    
    log_success "All services started successfully"
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

initialize_laravel() {
    log_info "Initializing Laravel application..."
    
    # Run Laravel initialization script
    docker-compose exec -T app bash -c "cd /var/www/html && ./scripts/automation/laravel-init.sh --environment=development"
    
    log_success "Laravel application initialized"
}

setup_development_tools() {
    log_info "Setting up development tools..."
    
    # Install development dependencies
    log_info "Installing development dependencies..."
    docker-compose exec -T app composer install --dev --optimize-autoloader
    
    # Install npm dependencies and start development server
    log_info "Installing Node.js dependencies..."
    docker-compose exec -T app npm install
    
    # Generate IDE helper files
    log_info "Generating IDE helper files..."
    docker-compose exec -T app php artisan ide-helper:generate 2>/dev/null || true
    docker-compose exec -T app php artisan ide-helper:models --write 2>/dev/null || true
    docker-compose exec -T app php artisan ide-helper:meta 2>/dev/null || true
    
    # Clear caches for development
    log_info "Clearing caches for development..."
    docker-compose exec -T app php artisan config:clear
    docker-compose exec -T app php artisan route:clear
    docker-compose exec -T app php artisan view:clear
    docker-compose exec -T app php artisan cache:clear
    
    log_success "Development tools setup completed"
}

display_development_info() {
    log_info "Development Environment Information:"
    echo
    echo "🌐 Application URLs:"
    echo "   Main Application: http://localhost:8000"
    echo "   Vite Dev Server:  http://localhost:5173"
    echo "   Nginx (if used):  http://localhost:80"
    echo
    echo "🗄️  Database Information:"
    echo "   Host: localhost:3306"
    echo "   Database: genealogy"
    echo "   Username: genealogy_user"
    echo "   Password: genealogy_pass"
    echo
    echo "🔄 Redis Information:"
    echo "   Host: localhost:6379"
    echo "   No password required"
    echo
    echo "🔧 Development Commands:"
    echo "   View logs:           docker-compose logs -f app"
    echo "   Laravel shell:       docker-compose exec app bash"
    echo "   Run artisan:         docker-compose exec app php artisan [command]"
    echo "   Run tests:           docker-compose exec app php artisan test"
    echo "   Run npm commands:    docker-compose exec app npm [command]"
    echo "   Stop services:       docker-compose down"
    echo
    echo "📝 Log Files:"
    echo "   Laravel logs:        docker-compose exec app tail -f storage/logs/laravel.log"
    echo "   Queue worker logs:   docker-compose logs -f queue-worker"
    echo "   Scheduler logs:      docker-compose logs -f scheduler"
    echo
    echo "🚀 Next Steps:"
    echo "   1. Open http://localhost:8000 in your browser"
    echo "   2. Login with demo credentials (check seeders)"
    echo "   3. Start developing in your IDE with Xdebug support"
    echo "   4. File changes will be automatically detected"
    echo
    echo "🐛 Debugging:"
    echo "   Xdebug is configured for port 9003"
    echo "   IDE key: PHPSTORM"
    echo "   Host: host.docker.internal"
}

# Handle script arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        --rebuild)
            export REBUILD=true
            shift
            ;;
        --no-cache)
            export NO_CACHE=true
            shift
            ;;
        --help)
            echo "Laravel Genealogy Development Deployment Script"
            echo
            echo "Usage: $0 [options]"
            echo
            echo "Options:"
            echo "  --rebuild      Rebuild Docker images from scratch"
            echo "  --no-cache     Build Docker images without cache"
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

# Additional build options
if [[ "${REBUILD:-false}" == "true" ]]; then
    log_info "Rebuilding Docker images from scratch..."
    docker-compose build --no-cache
fi

if [[ "${NO_CACHE:-false}" == "true" ]]; then
    export DOCKER_BUILDKIT=1
    export COMPOSE_DOCKER_CLI_BUILD=1
fi

# Execute main function
main "$@"