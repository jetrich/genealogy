#!/bin/bash
# =============================================================================
# Backup Script for Laravel Genealogy Application
# =============================================================================
# Comprehensive backup solution for genealogy data, files, and configurations

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
BACKUP_BASE_DIR="${BACKUP_DIR:-/var/backups/genealogy}"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="$BACKUP_BASE_DIR/$TIMESTAMP"
RETENTION_DAYS="${RETENTION_DAYS:-30}"
COMPRESS="${COMPRESS:-true}"
ENCRYPTION="${ENCRYPTION:-false}"
ENCRYPTION_KEY="${ENCRYPTION_KEY:-}"

# Backup types
BACKUP_DATABASE="${BACKUP_DATABASE:-true}"
BACKUP_FILES="${BACKUP_FILES:-true}"
BACKUP_CONFIG="${BACKUP_CONFIG:-true}"
BACKUP_MEDIA="${BACKUP_MEDIA:-true}"

main() {
    log_info "Starting Laravel Genealogy Backup Process..."
    log_info "Backup directory: $BACKUP_DIR"
    
    # Create backup directory
    create_backup_directory
    
    # Pre-backup checks
    pre_backup_checks
    
    # Perform backups
    if [[ "$BACKUP_DATABASE" == "true" ]]; then
        backup_database
    fi
    
    if [[ "$BACKUP_FILES" == "true" ]]; then
        backup_application_files
    fi
    
    if [[ "$BACKUP_CONFIG" == "true" ]]; then
        backup_configuration
    fi
    
    if [[ "$BACKUP_MEDIA" == "true" ]]; then
        backup_media_files
    fi
    
    # Post-backup tasks
    create_backup_manifest
    compress_backup
    encrypt_backup
    cleanup_old_backups
    verify_backup
    
    # Notification
    send_backup_notification
    
    log_success "Backup process completed successfully!"
    log_info "Backup location: $BACKUP_DIR"
}

create_backup_directory() {
    log_info "Creating backup directory structure..."
    
    mkdir -p "$BACKUP_DIR"/{database,files,config,media,logs}
    chmod 750 "$BACKUP_DIR"
    
    log_success "Backup directory created: $BACKUP_DIR"
}

pre_backup_checks() {
    log_info "Performing pre-backup checks..."
    
    # Check available disk space
    local required_space_gb=5  # Minimum 5GB required
    local available_space_gb=$(df "$BACKUP_BASE_DIR" | awk 'NR==2 {printf "%.0f", $4/1024/1024}')
    
    if [[ $available_space_gb -lt $required_space_gb ]]; then
        log_error "Insufficient disk space. Required: ${required_space_gb}GB, Available: ${available_space_gb}GB"
        exit 1
    fi
    
    # Check if application is running
    if docker-compose ps | grep -q "Up"; then
        log_info "Application is running"
    else
        log_warning "Application appears to be down"
    fi
    
    # Check database connectivity
    if docker-compose exec -T database mysqladmin ping >/dev/null 2>&1; then
        log_info "Database is accessible"
    else
        log_error "Database is not accessible"
        exit 1
    fi
    
    log_success "Pre-backup checks completed"
}

backup_database() {
    log_info "Backing up database..."
    
    # Source environment variables
    source "$PROJECT_ROOT/.env" 2>/dev/null || true
    
    local db_name="${DB_DATABASE:-genealogy}"
    local db_user="${DB_USERNAME:-genealogy_user}"
    local db_password="${DB_PASSWORD:-genealogy_pass}"
    
    # Create database backup
    log_info "Creating MySQL dump..."
    if docker-compose exec -T database mysqldump \
        --single-transaction \
        --routines \
        --triggers \
        --events \
        --add-drop-database \
        --add-drop-table \
        --create-options \
        --disable-keys \
        --extended-insert \
        --quick \
        --lock-tables=false \
        -u "$db_user" \
        -p"$db_password" \
        "$db_name" > "$BACKUP_DIR/database/genealogy_dump.sql"; then
        
        log_success "Database backup completed"
        
        # Create a compressed version
        if [[ "$COMPRESS" == "true" ]]; then
            gzip "$BACKUP_DIR/database/genealogy_dump.sql"
            log_info "Database backup compressed"
        fi
    else
        log_error "Database backup failed"
        exit 1
    fi
    
    # Export database schema only
    log_info "Creating schema-only backup..."
    docker-compose exec -T database mysqldump \
        --no-data \
        --routines \
        --triggers \
        --events \
        -u "$db_user" \
        -p"$db_password" \
        "$db_name" > "$BACKUP_DIR/database/schema_only.sql"
    
    # Create database information file
    cat > "$BACKUP_DIR/database/db_info.txt" << EOF
Database Backup Information
==========================
Timestamp: $(date)
Database Name: $db_name
Database User: $db_user
MySQL Version: $(docker-compose exec -T database mysql --version)
Tables: $(docker-compose exec -T database mysql -u "$db_user" -p"$db_password" -e "SELECT COUNT(*) as table_count FROM information_schema.tables WHERE table_schema='$db_name';" 2>/dev/null | tail -n1)
EOF
}

backup_application_files() {
    log_info "Backing up application files..."
    
    # Backup Laravel application code (excluding sensitive and unnecessary files)
    log_info "Creating application files archive..."
    tar \
        --exclude='vendor' \
        --exclude='node_modules' \
        --exclude='storage/logs/*' \
        --exclude='storage/framework/cache/*' \
        --exclude='storage/framework/sessions/*' \
        --exclude='storage/framework/views/*' \
        --exclude='storage/app/public/photos*' \
        --exclude='.git' \
        --exclude='.env' \
        --exclude='*.log' \
        -czf "$BACKUP_DIR/files/application.tar.gz" \
        -C "$PROJECT_ROOT" \
        .
    
    log_success "Application files backup completed"
    
    # Backup composer.lock and package-lock.json for reproducible builds
    if [[ -f "$PROJECT_ROOT/composer.lock" ]]; then
        cp "$PROJECT_ROOT/composer.lock" "$BACKUP_DIR/files/"
    fi
    
    if [[ -f "$PROJECT_ROOT/package-lock.json" ]]; then
        cp "$PROJECT_ROOT/package-lock.json" "$BACKUP_DIR/files/"
    fi
}

backup_configuration() {
    log_info "Backing up configuration files..."
    
    # Backup environment file (encrypted)
    if [[ -f "$PROJECT_ROOT/.env" ]]; then
        if [[ "$ENCRYPTION" == "true" && -n "$ENCRYPTION_KEY" ]]; then
            openssl enc -aes-256-cbc -salt -in "$PROJECT_ROOT/.env" -out "$BACKUP_DIR/config/.env.enc" -k "$ENCRYPTION_KEY"
            log_info "Environment file backed up (encrypted)"
        else
            cp "$PROJECT_ROOT/.env" "$BACKUP_DIR/config/.env.backup"
            chmod 600 "$BACKUP_DIR/config/.env.backup"
            log_info "Environment file backed up"
        fi
    fi
    
    # Backup Docker configurations
    if [[ -d "$PROJECT_ROOT/docker" ]]; then
        tar -czf "$BACKUP_DIR/config/docker_config.tar.gz" -C "$PROJECT_ROOT" docker/
        log_info "Docker configuration backed up"
    fi
    
    # Backup deployment scripts
    if [[ -d "$PROJECT_ROOT/scripts" ]]; then
        tar -czf "$BACKUP_DIR/config/scripts.tar.gz" -C "$PROJECT_ROOT" scripts/
        log_info "Deployment scripts backed up"
    fi
    
    # Create system information
    cat > "$BACKUP_DIR/config/system_info.txt" << EOF
System Information
==================
Hostname: $(hostname)
OS: $(uname -a)
Docker Version: $(docker --version)
Docker Compose Version: $(docker-compose --version)
PHP Version: $(docker-compose exec -T app php --version | head -n1)
Laravel Version: $(docker-compose exec -T app php artisan --version)
Timestamp: $(date)
User: $(whoami)
EOF
    
    log_success "Configuration backup completed"
}

backup_media_files() {
    log_info "Backing up media files..."
    
    # Backup uploaded photos and files
    local storage_dir="$PROJECT_ROOT/storage/app/public"
    
    if [[ -d "$storage_dir" ]]; then
        log_info "Backing up genealogy photos and files..."
        
        # Create incremental backup if previous backup exists
        local previous_backup=$(find "$BACKUP_BASE_DIR" -name "media_files.tar.gz" -type f | sort | tail -n1)
        
        if [[ -n "$previous_backup" && -f "$previous_backup" ]]; then
            # Incremental backup
            tar \
                --listed-incremental="$BACKUP_DIR/media/incremental.snar" \
                -czf "$BACKUP_DIR/media/media_files_incremental.tar.gz" \
                -C "$PROJECT_ROOT" \
                storage/app/public/
            log_info "Incremental media backup completed"
        else
            # Full backup
            tar -czf "$BACKUP_DIR/media/media_files.tar.gz" \
                -C "$PROJECT_ROOT" \
                storage/app/public/
            log_info "Full media backup completed"
        fi
        
        # Create media inventory
        find "$storage_dir" -type f -exec ls -lah {} \; > "$BACKUP_DIR/media/media_inventory.txt"
        
        # Calculate total size
        local media_size=$(du -sh "$storage_dir" 2>/dev/null | awk '{print $1}' || echo "Unknown")
        echo "Total media files size: $media_size" >> "$BACKUP_DIR/media/media_inventory.txt"
    else
        log_warning "Media directory not found: $storage_dir"
    fi
    
    # Backup GEDCOM files
    local gedcom_dir="$PROJECT_ROOT/storage/app/public/gedcom"
    if [[ -d "$gedcom_dir" ]]; then
        tar -czf "$BACKUP_DIR/media/gedcom_files.tar.gz" -C "$PROJECT_ROOT" storage/app/public/gedcom/
        log_info "GEDCOM files backed up"
    fi
    
    log_success "Media files backup completed"
}

create_backup_manifest() {
    log_info "Creating backup manifest..."
    
    # Create detailed manifest
    cat > "$BACKUP_DIR/backup_manifest.txt" << EOF
Laravel Genealogy Backup Manifest
=================================
Backup Timestamp: $TIMESTAMP
Backup Directory: $BACKUP_DIR
Backup Host: $(hostname)
Backup User: $(whoami)

Backup Components:
EOF
    
    if [[ "$BACKUP_DATABASE" == "true" ]]; then
        echo "✓ Database backup included" >> "$BACKUP_DIR/backup_manifest.txt"
    fi
    
    if [[ "$BACKUP_FILES" == "true" ]]; then
        echo "✓ Application files included" >> "$BACKUP_DIR/backup_manifest.txt"
    fi
    
    if [[ "$BACKUP_CONFIG" == "true" ]]; then
        echo "✓ Configuration files included" >> "$BACKUP_DIR/backup_manifest.txt"
    fi
    
    if [[ "$BACKUP_MEDIA" == "true" ]]; then
        echo "✓ Media files included" >> "$BACKUP_DIR/backup_manifest.txt"
    fi
    
    echo "" >> "$BACKUP_DIR/backup_manifest.txt"
    echo "File List:" >> "$BACKUP_DIR/backup_manifest.txt"
    find "$BACKUP_DIR" -type f -exec ls -lah {} \; >> "$BACKUP_DIR/backup_manifest.txt"
    
    echo "" >> "$BACKUP_DIR/backup_manifest.txt"
    echo "Total Backup Size: $(du -sh "$BACKUP_DIR" | awk '{print $1}')" >> "$BACKUP_DIR/backup_manifest.txt"
    
    # Create checksums
    find "$BACKUP_DIR" -type f -not -name "*.md5" -exec md5sum {} \; > "$BACKUP_DIR/checksums.md5"
    
    log_success "Backup manifest created"
}

compress_backup() {
    if [[ "$COMPRESS" == "true" ]]; then
        log_info "Compressing backup archive..."
        
        cd "$BACKUP_BASE_DIR"
        tar -czf "genealogy_backup_$TIMESTAMP.tar.gz" "$TIMESTAMP/"
        
        if [[ $? -eq 0 ]]; then
            # Remove uncompressed directory after successful compression
            rm -rf "$BACKUP_DIR"
            BACKUP_DIR="$BACKUP_BASE_DIR/genealogy_backup_$TIMESTAMP.tar.gz"
            
            log_success "Backup compressed successfully"
        else
            log_error "Backup compression failed"
            exit 1
        fi
    fi
}

encrypt_backup() {
    if [[ "$ENCRYPTION" == "true" && -n "$ENCRYPTION_KEY" ]]; then
        log_info "Encrypting backup..."
        
        if [[ -f "$BACKUP_DIR" ]]; then
            # Encrypt compressed backup
            openssl enc -aes-256-cbc -salt -in "$BACKUP_DIR" -out "${BACKUP_DIR}.enc" -k "$ENCRYPTION_KEY"
            
            if [[ $? -eq 0 ]]; then
                rm "$BACKUP_DIR"
                BACKUP_DIR="${BACKUP_DIR}.enc"
                log_success "Backup encrypted successfully"
            else
                log_error "Backup encryption failed"
                exit 1
            fi
        fi
    fi
}

cleanup_old_backups() {
    log_info "Cleaning up old backups (retention: $RETENTION_DAYS days)..."
    
    # Remove backups older than retention period
    find "$BACKUP_BASE_DIR" -type f -name "genealogy_backup_*.tar.gz*" -mtime +$RETENTION_DAYS -delete
    find "$BACKUP_BASE_DIR" -type d -name "20*" -mtime +$RETENTION_DAYS -exec rm -rf {} \; 2>/dev/null || true
    
    # Keep at least 3 most recent backups regardless of age
    local backup_count=$(find "$BACKUP_BASE_DIR" -type f -name "genealogy_backup_*.tar.gz*" | wc -l)
    if [[ $backup_count -gt 3 ]]; then
        find "$BACKUP_BASE_DIR" -type f -name "genealogy_backup_*.tar.gz*" | sort | head -n -3 | xargs rm -f
    fi
    
    log_success "Old backup cleanup completed"
}

verify_backup() {
    log_info "Verifying backup integrity..."
    
    if [[ -f "$BACKUP_DIR" ]]; then
        # Test compressed archive
        if [[ "$BACKUP_DIR" =~ \.tar\.gz$ ]]; then
            if tar -tzf "$BACKUP_DIR" >/dev/null 2>&1; then
                log_success "Backup archive integrity verified"
            else
                log_error "Backup archive is corrupted"
                exit 1
            fi
        fi
        
        # Test encrypted file
        if [[ "$BACKUP_DIR" =~ \.enc$ ]]; then
            if [[ -n "$ENCRYPTION_KEY" ]]; then
                if openssl enc -d -aes-256-cbc -in "$BACKUP_DIR" -k "$ENCRYPTION_KEY" >/dev/null 2>&1; then
                    log_success "Encrypted backup integrity verified"
                else
                    log_error "Encrypted backup integrity check failed"
                    exit 1
                fi
            else
                log_warning "Cannot verify encrypted backup without encryption key"
            fi
        fi
    fi
}

send_backup_notification() {
    local backup_size=$(du -sh "$BACKUP_DIR" 2>/dev/null | awk '{print $1}' || echo "Unknown")
    
    # Create notification message
    local message="Laravel Genealogy Backup Completed
Timestamp: $TIMESTAMP
Status: Success
Size: $backup_size
Location: $BACKUP_DIR
Host: $(hostname)"
    
    # Send email notification if configured
    if [[ -n "${BACKUP_NOTIFICATION_EMAIL:-}" ]]; then
        echo "$message" | mail -s "Genealogy Backup Completed - $TIMESTAMP" "$BACKUP_NOTIFICATION_EMAIL" 2>/dev/null || true
    fi
    
    # Log notification
    echo "$message" > "$BACKUP_BASE_DIR/last_backup_status.txt"
    
    log_info "Backup notification sent"
}

# Handle script arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        --database-only)
            BACKUP_DATABASE=true
            BACKUP_FILES=false
            BACKUP_CONFIG=false
            BACKUP_MEDIA=false
            shift
            ;;
        --files-only)
            BACKUP_DATABASE=false
            BACKUP_FILES=true
            BACKUP_CONFIG=false
            BACKUP_MEDIA=false
            shift
            ;;
        --no-compress)
            COMPRESS=false
            shift
            ;;
        --encrypt)
            ENCRYPTION=true
            shift
            ;;
        --retention=*)
            RETENTION_DAYS="${1#*=}"
            shift
            ;;
        --backup-dir=*)
            BACKUP_BASE_DIR="${1#*=}"
            shift
            ;;
        --help)
            echo "Laravel Genealogy Backup Script"
            echo
            echo "Usage: $0 [options]"
            echo
            echo "Options:"
            echo "  --database-only      Backup only database"
            echo "  --files-only         Backup only application files"
            echo "  --no-compress        Do not compress backup"
            echo "  --encrypt            Encrypt backup (requires ENCRYPTION_KEY env var)"
            echo "  --retention=DAYS     Set backup retention in days (default: 30)"
            echo "  --backup-dir=PATH    Set backup directory (default: /var/backups/genealogy)"
            echo "  --help               Show this help message"
            echo
            echo "Environment variables:"
            echo "  BACKUP_DIR                  Base backup directory"
            echo "  ENCRYPTION_KEY              Key for backup encryption"
            echo "  BACKUP_NOTIFICATION_EMAIL   Email for backup notifications"
            echo "  RETENTION_DAYS              Backup retention period"
            exit 0
            ;;
        *)
            log_error "Unknown option: $1"
            echo "Use --help for usage information"
            exit 1
            ;;
    esac
done

# Execute main function
main "$@"