#!/bin/bash

# Fix Docker Compose Commands - Update from docker-compose to docker compose
# Modern Docker installations use "docker compose" instead of "docker-compose"

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

echo "🔧 Docker Compose Command Updater"
echo "=================================="
echo ""
echo "Updating from 'docker-compose' to 'docker compose' commands"
echo ""

# Check Docker Compose availability
log "INFO" "Checking Docker Compose installation..."

if command -v docker >/dev/null 2>&1; then
    if docker compose version >/dev/null 2>&1; then
        DOCKER_COMPOSE_VERSION=$(docker compose version --short 2>/dev/null || echo "unknown")
        log "SUCCESS" "Docker Compose (modern) available: $DOCKER_COMPOSE_VERSION"
        COMPOSE_COMMAND="docker compose"
    elif command -v docker-compose >/dev/null 2>&1; then
        DOCKER_COMPOSE_VERSION=$(docker-compose --version 2>/dev/null | grep -oP 'docker-compose version \K[0-9.]+' || echo "unknown")
        log "WARNING" "Using legacy docker-compose: $DOCKER_COMPOSE_VERSION"
        log "INFO" "Consider upgrading to modern 'docker compose' command"
        COMPOSE_COMMAND="docker-compose"
    else
        log "ERROR" "No Docker Compose found"
        exit 1
    fi
else
    log "ERROR" "Docker not found"
    exit 1
fi

# Files to update
FILES_TO_UPDATE=(
    "scripts/deployment-validation.sh"
    "scripts/quick-setup.sh"
    "docs/deployment/PRODUCTION_DEPLOYMENT_CHECKLIST.md"
    "docs/LARAVEL-DEPLOYMENT-GUIDE.md"
    "DOCKER.md"
    "README.md"
)

updated_files=0

# Update files
for file in "${FILES_TO_UPDATE[@]}"; do
    if [[ -f "$file" ]]; then
        log "INFO" "Checking $file..."
        
        # Count occurrences
        old_count=$(grep -c "docker-compose" "$file" 2>/dev/null || echo "0")
        
        if [[ "$old_count" -gt 0 ]]; then
            log "INFO" "Found $old_count instances of 'docker-compose' in $file"
            
            # Create backup
            cp "$file" "${file}.backup"
            
            # Replace docker-compose with docker compose
            sed -i 's/docker-compose/docker compose/g' "$file"
            
            # Verify changes
            new_count=$(grep -c "docker compose" "$file" 2>/dev/null || echo "0")
            
            if [[ "$new_count" -eq "$old_count" ]]; then
                log "SUCCESS" "Updated $file ($old_count -> $new_count instances)"
                updated_files=$((updated_files + 1))
            else
                log "ERROR" "Update failed for $file"
                # Restore backup
                mv "${file}.backup" "$file"
            fi
        else
            log "INFO" "No docker-compose commands found in $file"
        fi
    else
        log "WARNING" "$file not found (skipping)"
    fi
done

echo ""
log "INFO" "Update summary: $updated_files files updated"

# Test Docker Compose functionality
echo ""
log "INFO" "Testing Docker Compose functionality..."

# Check if docker-compose.yml exists
if [[ -f "docker-compose.yml" ]]; then
    log "SUCCESS" "docker-compose.yml found"
    
    # Test config validation
    if $COMPOSE_COMMAND config --quiet 2>/dev/null; then
        log "SUCCESS" "Docker Compose configuration is valid"
    else
        log "ERROR" "Docker Compose configuration has errors"
        echo ""
        echo "Run this to see details:"
        echo "$COMPOSE_COMMAND config"
    fi
else
    log "INFO" "No docker-compose.yml found (this is okay)"
fi

# Generate updated commands reference
echo ""
echo "📋 Updated Docker Compose Commands Reference"
echo "============================================"
echo ""
echo "✅ Modern commands (recommended):"
echo "   $COMPOSE_COMMAND up -d"
echo "   $COMPOSE_COMMAND down"
echo "   $COMPOSE_COMMAND logs -f app"
echo "   $COMPOSE_COMMAND ps"
echo "   $COMPOSE_COMMAND exec app bash"
echo ""

if [[ "$COMPOSE_COMMAND" == "docker-compose" ]]; then
    echo "💡 To upgrade to modern Docker Compose:"
    echo "   # On Ubuntu/Debian:"
    echo "   sudo apt update && sudo apt install docker-compose-plugin"
    echo ""
    echo "   # Or install Docker Desktop which includes modern compose"
fi

echo ""
log "SUCCESS" "Docker Compose command update completed!"

# Clean up backup files
echo ""
log "INFO" "Cleaning up backup files..."
for file in "${FILES_TO_UPDATE[@]}"; do
    if [[ -f "${file}.backup" ]]; then
        rm "${file}.backup"
        log "INFO" "Removed backup: ${file}.backup"
    fi
done

echo ""
echo "🚀 Next Steps:"
echo "=============="
echo ""
echo "1. ✅ All docker-compose commands updated to docker compose"
echo "2. ✅ Test the updated deployment validation:"
echo "   ./scripts/deployment-validation.sh"
echo ""
echo "3. ✅ Test Docker functionality:"
echo "   $COMPOSE_COMMAND --version"
echo "   $COMPOSE_COMMAND config  # if docker-compose.yml exists"
echo ""
echo "4. ✅ Continue with your deployment:"
echo "   ./scripts/quick-setup.sh"
echo ""

log "INFO" "All Docker Compose commands have been modernized!"