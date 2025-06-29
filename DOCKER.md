# Docker Setup for Laravel Genealogy Application

This document provides comprehensive instructions for setting up and running the Laravel Genealogy application using Docker.

## Overview

The Docker setup includes:
- **Laravel Application** (PHP 8.4 + Nginx)
- **MySQL 8.0.1+** (with Recursive CTE support for genealogy queries)
- **Redis** (for caching and sessions)
- **Queue Workers** (for background genealogy processing)
- **Scheduler** (for automated backups and maintenance)
- **Development Tools** (Vite, phpMyAdmin, Redis Commander)

## Prerequisites

- Docker Engine 20.10+
- Docker Compose 2.0+
- At least 4GB of available RAM
- 10GB of available disk space

## Quick Start

### Development Environment

1. **Clone and setup:**
   ```bash
   git clone <repository-url>
   cd genealogy
   cp .env.docker .env
   ```

2. **Generate application key:**
   ```bash
   docker-compose run --rm app php artisan key:generate
   ```

3. **Start services:**
   ```bash
   docker-compose up -d
   ```

4. **Run migrations:**
   ```bash
   docker-compose exec app php artisan migrate --seed
   ```

5. **Access the application:**
   - Main app: http://localhost:8000
   - phpMyAdmin: http://localhost:8080
   - Redis Commander: http://localhost:8081

### Production Environment

1. **Setup environment:**
   ```bash
   cp .env.example .env.production
   # Edit .env.production with production values
   ```

2. **Deploy:**
   ```bash
   docker-compose -f docker-compose.yml -f docker-compose.prod.yml up -d
   ```

## Services

### Application (app)
- **Port:** 8000
- **Technology:** PHP 8.4 + Nginx
- **Purpose:** Main Laravel application

**Health Check:** http://localhost:8000/health

### Database (mysql)
- **Port:** 3306
- **Technology:** MySQL 8.0
- **Purpose:** Primary database with genealogy data

**Connection Details:**
- Host: mysql (internal) / localhost:3306 (external)
- Database: genealogy
- Username: genealogy_user
- Password: genealogy_password

### Cache (redis)
- **Port:** 6379
- **Technology:** Redis 7
- **Purpose:** Session storage, caching, queue backend

### Development Tools

#### phpMyAdmin
- **Port:** 8080
- **Purpose:** Database management
- **Profile:** development only

#### Redis Commander
- **Port:** 8081
- **Purpose:** Redis monitoring and management
- **Profile:** development only

#### Vite Dev Server
- **Port:** 5173
- **Purpose:** Hot-reloading for CSS/JS development
- **Profile:** development only

## Volume Management

### Persistent Volumes

| Volume | Purpose | Path |
|--------|---------|------|
| `genealogy_mysql_data` | Database storage | `/var/lib/mysql` |
| `genealogy_redis_data` | Redis persistence | `/data` |
| `genealogy_storage` | Laravel storage | `/var/www/html/storage/app` |
| `genealogy_logs` | Application logs | `/var/www/html/storage/logs` |
| `genealogy_photos` | Genealogy photos | `/var/www/html/storage/app/public/photos` |
| `genealogy_gedcom` | GEDCOM files | `/var/www/html/storage/app/public/gedcom` |
| `genealogy_backups` | Database backups | `/var/www/html/storage/app/backups` |

### Backup Volumes

```bash
# Create backup of all volumes
docker run --rm -v genealogy_mysql_data:/data:ro -v $(pwd):/backup alpine tar czf /backup/mysql-backup.tar.gz /data

# Restore from backup
docker run --rm -v genealogy_mysql_data:/data -v $(pwd):/backup alpine tar xzf /backup/mysql-backup.tar.gz -C /
```

## Common Commands

### Application Management

```bash
# View logs
docker-compose logs -f app

# Execute artisan commands
docker-compose exec app php artisan migrate
docker-compose exec app php artisan queue:work
docker-compose exec app php artisan backup:run

# Access application shell
docker-compose exec app bash

# Restart specific service
docker-compose restart app
```

### Development Workflow

```bash
# Start development environment
docker-compose --profile development up -d

# Watch for file changes (if using Vite)
docker-compose run --rm vite

# Run tests
docker-compose exec app php artisan test

# Generate IDE helper files
docker-compose exec app php artisan ide-helper:generate
```

### Database Operations

```bash
# Database shell
docker-compose exec mysql mysql -u genealogy_user -p genealogy

# Import SQL file
docker-compose exec -T mysql mysql -u genealogy_user -p genealogy < backup.sql

# Create database backup
docker-compose exec mysql mysqldump -u genealogy_user -p genealogy > backup.sql
```

### Cache Operations

```bash
# Clear Laravel caches
docker-compose exec app php artisan cache:clear
docker-compose exec app php artisan config:clear
docker-compose exec app php artisan view:clear

# Redis operations
docker-compose exec redis redis-cli
docker-compose exec redis redis-cli FLUSHALL
```

## Environment Configuration

### Development (.env.docker)
- Debug enabled
- Detailed logging
- Development tools enabled
- Hot-reloading support

### Production (.env.production)
- Debug disabled
- Error logging only
- Security headers enabled
- Performance optimizations

## File Permissions

The Docker setup automatically handles Laravel file permissions:

```bash
# Manual permission fix if needed
docker-compose exec app chown -R www-data:www-data storage bootstrap/cache
docker-compose exec app chmod -R 775 storage bootstrap/cache
```

## Performance Optimization

### Development
- Xdebug enabled for debugging
- File watching for hot-reloading
- Detailed error reporting

### Production
- OPcache optimization
- Asset compilation and minification
- Database query optimization
- Redis persistence

## Troubleshooting

### Common Issues

**Port conflicts:**
```bash
# Check port usage
sudo netstat -tlnp | grep :8000

# Change ports in docker-compose.yml if needed
```

**Permission issues:**
```bash
# Fix Laravel permissions
docker-compose exec app chmod -R 775 storage bootstrap/cache
docker-compose exec app chown -R www-data:www-data storage bootstrap/cache
```

**Database connection issues:**
```bash
# Check MySQL status
docker-compose logs mysql

# Verify environment variables
docker-compose exec app env | grep DB_
```

**Memory issues:**
```bash
# Increase Docker memory limits
# Edit Docker Desktop settings or docker-compose.yml
```

### Log Locations

- Application logs: `storage/logs/`
- Nginx logs: `/var/log/nginx/`
- MySQL logs: `/var/log/mysql/`
- Supervisor logs: `/var/log/supervisor/`

## Security Considerations

### Development
- Default passwords (change for production)
- Debug mode enabled
- Open database ports

### Production
- Strong passwords required
- Security headers enabled
- Restricted file access
- SSL/TLS encryption recommended

## Updating

### Application Updates
```bash
# Pull latest changes
git pull origin main

# Rebuild containers
docker-compose build --no-cache

# Update dependencies
docker-compose exec app composer update
docker-compose exec app npm update && npm run build

# Run migrations
docker-compose exec app php artisan migrate
```

### System Updates
```bash
# Update base images
docker-compose pull
docker-compose up -d --force-recreate
```

## Monitoring

### Health Checks
All services include health checks:
- Application: HTTP endpoint
- MySQL: Connection test
- Redis: Ping command

### Metrics
- Container resource usage
- Application performance
- Database query performance
- Queue processing metrics

For production monitoring, consider integrating:
- Prometheus + Grafana
- ELK Stack (Elasticsearch, Logstash, Kibana)
- Laravel Telescope
- New Relic or similar APM tools