# Laravel Genealogy Application Deployment Guide

## Table of Contents

1. [Overview](#overview)
2. [Prerequisites](#prerequisites)
3. [Environment Setup](#environment-setup)
4. [Docker Deployment](#docker-deployment)
5. [Database Setup](#database-setup)
6. [Production Deployment](#production-deployment)
7. [Development Environment](#development-environment)
8. [Backup and Recovery](#backup-and-recovery)
9. [Monitoring and Maintenance](#monitoring-and-maintenance)
10. [Troubleshooting](#troubleshooting)
11. [Security Considerations](#security-considerations)

## Overview

This document provides comprehensive deployment instructions for the Laravel Genealogy application, a TallStack (Tailwind, Alpine.js, Laravel, Livewire) application for managing genealogical data with features including:

- Multi-tenant genealogy management via Laravel Jetstream Teams
- GEDCOM import/export functionality
- Media file management for genealogy photos
- Automated backup system using Spatie Laravel Backup
- Multi-language support (10+ languages)
- Complex genealogical relationships with MySQL recursive CTEs

### Architecture Components

- **Backend**: Laravel 12 with PHP 8.3+
- **Frontend**: TallStack (Tailwind CSS, Alpine.js, Livewire)
- **Database**: MySQL 8.0 with genealogy-optimized configuration
- **Cache/Sessions**: Redis 7
- **Queue System**: Redis-based queues for media processing and backups
- **File Storage**: Local filesystem with Spatie Media Library
- **Containerization**: Docker with multi-stage builds

## Prerequisites

### System Requirements

- **Operating System**: Linux (Ubuntu 20.04+ recommended)
- **Memory**: Minimum 2GB RAM (4GB+ recommended for production)
- **Storage**: Minimum 10GB (50GB+ recommended for genealogy data)
- **Network**: Internet access for downloads and updates

### Software Dependencies

- **Docker**: Version 20.10+
- **Docker Compose**: Version 2.0+
- **Git**: For source code management
- **SSL Certificates**: For production HTTPS (Let's Encrypt recommended)

### Installation Commands

```bash
# Ubuntu/Debian
sudo apt update
sudo apt install -y docker.io docker-compose git curl

# Start Docker service
sudo systemctl enable docker
sudo systemctl start docker

# Add user to docker group
sudo usermod -aG docker $USER
newgrp docker
```

## Environment Setup

### 1. Clone Repository

```bash
git clone https://github.com/your-org/genealogy.git
cd genealogy
```

### 2. Environment Configuration

```bash
# Copy environment template
cp .env.example .env

# Edit environment variables
nano .env
```

### Essential Environment Variables

```env
# Application
APP_NAME=Genealogy
APP_ENV=production
APP_KEY=base64:your-32-character-key-here
APP_DEBUG=false
APP_URL=https://your-domain.com

# Database
DB_CONNECTION=mysql
DB_HOST=database
DB_PORT=3306
DB_DATABASE=genealogy
DB_USERNAME=genealogy_user
DB_PASSWORD=your-secure-password

# Cache and Sessions
CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

# Redis
REDIS_HOST=cache
REDIS_PORT=6379
REDIS_PASSWORD=your-redis-password

# Mail Configuration
MAIL_MAILER=smtp
MAIL_HOST=your-smtp-host
MAIL_PORT=587
MAIL_USERNAME=your-email@domain.com
MAIL_PASSWORD=your-email-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@your-domain.com
MAIL_FROM_NAME="Genealogy Application"

# Backup Configuration
BACKUP_DISK=backups
BACKUP_MAIL_ADDRESS=admin@your-domain.com
```

### 3. Generate Application Key

```bash
# Generate Laravel application key
docker-compose run --rm app php artisan key:generate
```

## Docker Deployment

### Container Architecture

The application uses a multi-container Docker setup:

- **app**: Laravel application (PHP 8.3-FPM + Nginx)
- **database**: MySQL 8.0 with genealogy optimizations
- **cache**: Redis 7 for caching and sessions
- **queue-worker**: Laravel queue processing
- **scheduler**: Laravel task scheduling
- **backup**: Automated backup service

### Docker Configuration Files

```bash
# Main Docker Compose file
docker-compose.yml

# Docker configurations
docker/
├── php/
│   ├── php-prod.ini      # Production PHP settings
│   └── php-dev.ini       # Development PHP settings
├── nginx/
│   └── default.conf      # Nginx configuration
├── mysql/
│   └── conf.d/
│       └── genealogy.cnf # MySQL optimizations
├── redis/
│   └── redis.conf        # Redis configuration
└── supervisor/
    └── supervisord.conf  # Process management
```

### Build and Deploy

```bash
# Production deployment
./scripts/automation/deploy-production.sh

# Development deployment
./scripts/automation/deploy-development.sh
```

## Database Setup

### MySQL Configuration

The application uses MySQL 8.0 with specific optimizations for genealogical data:

```sql
-- Key settings for genealogy performance
innodb_buffer_pool_size = 512M
innodb_log_file_size = 128M
max_connections = 200
character_set_server = utf8mb4
collation_server = utf8mb4_unicode_ci
```

### Migration and Seeding

```bash
# Run database migrations
docker-compose exec app php artisan migrate --force

# Seed with demo data (development only)
docker-compose exec app php artisan db:seed --force

# Create storage link
docker-compose exec app php artisan storage:link
```

### Database Structure

Key tables for genealogy data:

- **people**: Individual genealogy records
- **couples**: Relationship records
- **person_metadata**: Extended person information
- **teams**: Multi-tenant organization
- **media**: File attachments (photos, documents)

## Production Deployment

### 1. Pre-deployment Checklist

```bash
# Security checklist
□ SSL certificates configured
□ Firewall rules set up
□ Strong passwords set
□ APP_DEBUG=false
□ Database backups scheduled
□ Monitoring configured
```

### 2. Production Deployment Script

```bash
# Run production deployment
sudo ./scripts/automation/deploy-production.sh

# Follow prompts for confirmation
# Monitor deployment logs
```

### 3. Post-deployment Verification

```bash
# Check application health
curl http://localhost/health

# Verify database connection
docker-compose exec app php artisan migrate:status

# Check queue workers
docker-compose exec app php artisan queue:monitor

# Test backup system
docker-compose exec app php artisan backup:run
```

### 4. Production Optimizations

```bash
# Laravel optimizations (automatically applied)
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# Composer optimizations
composer dump-autoload --optimize --classmap-authoritative
```

## Development Environment

### Quick Start

```bash
# Clone and setup
git clone https://github.com/your-org/genealogy.git
cd genealogy
cp .env.example .env

# Deploy development environment
./scripts/automation/deploy-development.sh

# Access application
open http://localhost:8000
```

### Development Services

- **Main Application**: http://localhost:8000
- **Vite Dev Server**: http://localhost:5173 (hot reload)
- **Database**: localhost:3306
- **Redis**: localhost:6379

### Development Commands

```bash
# Enter application container
docker-compose exec app bash

# Run artisan commands
docker-compose exec app php artisan [command]

# Run tests
docker-compose exec app php artisan test

# Watch for changes
docker-compose exec app npm run dev

# View logs
docker-compose logs -f app
```

### IDE Configuration

#### PHPStorm/IntelliJ

```xml
<!-- Xdebug configuration -->
<configuration>
  <host>host.docker.internal</host>
  <port>9003</port>
  <idekey>PHPSTORM</idekey>
</configuration>
```

#### VS Code

```json
{
  "php.debug.host": "host.docker.internal",
  "php.debug.port": 9003
}
```

## Backup and Recovery

### Automated Backup System

The application includes comprehensive backup automation:

```bash
# Run manual backup
./scripts/automation/backup-genealogy.sh

# Database only backup
./scripts/automation/backup-genealogy.sh --database-only

# Encrypted backup
ENCRYPTION_KEY="your-key" ./scripts/automation/backup-genealogy.sh --encrypt
```

### Backup Components

1. **Database**: Complete MySQL dump with structure and data
2. **Application Files**: Laravel codebase and configurations
3. **Media Files**: Genealogy photos and uploaded documents
4. **Configuration**: Environment and Docker settings

### Backup Schedule

Production backup schedule (automatically configured):

```bash
# Daily database backup at 23:00
0 23 * * * /path/to/backup-genealogy.sh --database-only

# Weekly full backup on Sunday at 02:00
0 2 * * 0 /path/to/backup-genealogy.sh

# Monthly archive backup
0 1 1 * * /path/to/backup-genealogy.sh --encrypt
```

### Recovery Procedures

#### Database Recovery

```bash
# Stop application
docker-compose down

# Restore database
docker-compose up -d database
cat backup/database/genealogy_dump.sql | docker-compose exec -T database mysql -u root -p genealogy

# Restart application
docker-compose up -d
```

#### Full System Recovery

```bash
# Extract backup
tar -xzf genealogy_backup_20240628_120000.tar.gz

# Restore files
cp -r backup/files/* /path/to/genealogy/
cp backup/config/.env /path/to/genealogy/

# Restore database
# (see database recovery above)

# Rebuild and start
docker-compose build
docker-compose up -d
```

## Monitoring and Maintenance

### Health Monitoring

#### Application Health Checks

```bash
# Built-in health endpoint
curl http://localhost/health

# Detailed status
curl http://localhost/status
```

#### Automated Monitoring

The production deployment includes:

- **Health checks**: Every 5 minutes
- **Disk space monitoring**: Alerts at 90% usage
- **Memory monitoring**: Alerts at 90% usage
- **Log rotation**: Daily rotation with compression

### Log Management

```bash
# View application logs
docker-compose logs -f app

# View specific service logs
docker-compose logs -f database
docker-compose logs -f queue-worker
docker-compose logs -f scheduler

# Laravel application logs
docker-compose exec app tail -f storage/logs/laravel.log
```

### Performance Monitoring

#### Database Performance

```bash
# Check slow queries
docker-compose exec database mysql -e "SHOW PROCESSLIST;"

# MySQL performance metrics
docker-compose exec database mysqladmin extended-status

# Query performance
docker-compose exec app php artisan telescope:install  # If using Telescope
```

#### Application Performance

```bash
# Clear all caches
docker-compose exec app php artisan optimize:clear

# Monitor queue jobs
docker-compose exec app php artisan queue:monitor

# Check failed jobs
docker-compose exec app php artisan queue:failed
```

### Maintenance Tasks

#### Regular Maintenance

```bash
# Weekly maintenance script
#!/bin/bash
# Clear old logs
docker-compose exec app php artisan log:clear

# Optimize database
docker-compose exec database mysqlcheck --optimize --all-databases

# Clear expired sessions
docker-compose exec app php artisan session:gc

# Update packages (staging environment first)
docker-compose exec app composer update
```

#### Security Updates

```bash
# Update base images
docker-compose pull

# Rebuild with latest patches
docker-compose build --no-cache

# Update PHP dependencies
docker-compose exec app composer update

# Update Node dependencies
docker-compose exec app npm update
```

## Troubleshooting

### Common Issues

#### Database Connection Issues

```bash
# Check database status
docker-compose ps database

# Check database logs
docker-compose logs database

# Test connection
docker-compose exec app php artisan migrate:status

# Reset database connection
docker-compose restart database
docker-compose restart app
```

#### File Permission Issues

```bash
# Fix Laravel permissions
docker-compose exec app chown -R www-data:www-data storage bootstrap/cache
docker-compose exec app chmod -R 775 storage bootstrap/cache
```

#### Queue Worker Issues

```bash
# Check queue status
docker-compose exec app php artisan queue:monitor

# Restart queue workers
docker-compose restart queue-worker

# Clear failed jobs
docker-compose exec app php artisan queue:flush

# Process jobs manually
docker-compose exec app php artisan queue:work --once
```

#### Performance Issues

```bash
# Clear all caches
docker-compose exec app php artisan optimize:clear

# Rebuild optimizations
docker-compose exec app php artisan optimize

# Check system resources
docker stats

# Monitor database queries
docker-compose exec database mysql -e "SHOW PROCESSLIST;"
```

### Error Diagnostics

#### Application Errors

```bash
# Check Laravel logs
docker-compose exec app tail -f storage/logs/laravel.log

# Check web server logs
docker-compose logs nginx

# Check PHP-FPM logs
docker-compose logs app
```

#### Container Issues

```bash
# Check container status
docker-compose ps

# Check container resource usage
docker stats

# Restart specific service
docker-compose restart [service-name]

# Rebuild problematic container
docker-compose build --no-cache [service-name]
```

## Security Considerations

### Production Security Checklist

#### Application Security

```bash
□ APP_DEBUG=false in production
□ Strong APP_KEY generated
□ Database credentials secured
□ File permissions properly set (644/755)
□ Storage directory not web accessible
□ .env file permissions set to 600
□ Unnecessary files removed from production image
```

#### Infrastructure Security

```bash
□ SSL/TLS certificates installed and configured
□ Firewall configured (UFW recommended)
□ SSH access secured (key-based authentication)
□ Database ports not exposed externally
□ Redis secured or not exposed
□ Regular security updates applied
□ Backup encryption enabled
□ Log monitoring configured
```

### Security Configuration

#### Firewall Setup (UFW)

```bash
# Basic firewall setup
sudo ufw default deny incoming
sudo ufw default allow outgoing
sudo ufw allow ssh
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw enable
```

#### SSL Certificate Setup (Let's Encrypt)

```bash
# Install certbot
sudo apt install certbot

# Generate certificate
sudo certbot certonly --standalone -d your-domain.com

# Configure auto-renewal
echo "0 12 * * * /usr/bin/certbot renew --quiet && docker-compose restart nginx" | sudo crontab -
```

#### Database Security

```sql
-- Remove test databases and users
DROP DATABASE IF EXISTS test;
DELETE FROM mysql.user WHERE User='';
DELETE FROM mysql.user WHERE User='root' AND Host NOT IN ('localhost', '127.0.0.1');
FLUSH PRIVILEGES;

-- Create application-specific user
CREATE USER 'genealogy_user'@'%' IDENTIFIED BY 'strong-password';
GRANT ALL PRIVILEGES ON genealogy.* TO 'genealogy_user'@'%';
FLUSH PRIVILEGES;
```

### Backup Security

```bash
# Encrypt sensitive backups
export ENCRYPTION_KEY="your-strong-encryption-key"
./scripts/automation/backup-genealogy.sh --encrypt

# Secure backup storage
chmod 700 /var/backups/genealogy
chown backup-user:backup-group /var/backups/genealogy
```

---

## Quick Reference

### Essential Commands

```bash
# Deployment
./scripts/automation/deploy-production.sh    # Production
./scripts/automation/deploy-development.sh   # Development

# Backup
./scripts/automation/backup-genealogy.sh     # Full backup
./scripts/automation/backup-genealogy.sh --database-only  # DB only

# Maintenance
docker-compose logs -f [service]             # View logs
docker-compose restart [service]             # Restart service
docker-compose exec app php artisan [cmd]    # Run artisan command

# Monitoring
curl http://localhost/health                  # Health check
docker-compose ps                            # Service status
docker stats                                 # Resource usage
```

### Important Paths

```bash
/var/www/html                    # Application root
/var/www/html/storage           # Laravel storage
/var/backups/genealogy          # Backup directory
/var/log/genealogy             # Application logs
/etc/nginx/sites-available     # Nginx configuration
```

### Support Contacts

- **Documentation**: This deployment guide
- **Laravel Docs**: https://laravel.com/docs
- **Docker Docs**: https://docs.docker.com
- **Issue Tracking**: GitHub Issues
- **Community**: Laravel community forums

---

*Last updated: 2024-06-28*  
*Version: 1.0.0*