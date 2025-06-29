# Multi-stage Dockerfile for Laravel Genealogy Application
# =========================================================

# Stage 1: Base PHP 8.4 with system dependencies
FROM php:8.4-fpm-alpine AS base

# Set working directory
WORKDIR /var/www/html

# Install system dependencies
RUN apk add --no-cache \
    # Core system packages
    bash \
    curl \
    wget \
    unzip \
    git \
    nginx \
    supervisor \
    # Image processing (for GD extension and genealogy photos)
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    libwebp-dev \
    libxpm-dev \
    # MySQL client
    mysql-client \
    # Additional libraries for Laravel
    libzip-dev \
    libxml2-dev \
    libcurl4-openssl-dev \
    oniguruma-dev \
    icu-dev \
    # Redis tools
    redis \
    # Process management
    procps \
    # File permissions and ownership tools
    shadow

# Configure and install PHP extensions required for Laravel and genealogy features
RUN docker-php-ext-configure gd \
    --with-freetype \
    --with-jpeg \
    --with-webp \
    --with-xpm \
    && docker-php-ext-install -j$(nproc) \
    # Core Laravel extensions
    pdo_mysql \
    mysqli \
    zip \
    curl \
    xml \
    mbstring \
    intl \
    bcmath \
    # Image processing for genealogy photos
    gd \
    # Additional extensions for performance
    opcache \
    # File info for GEDCOM processing
    fileinfo \
    # JSON for API responses
    json

# Install Redis extension
RUN pecl install redis \
    && docker-php-ext-enable redis

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Stage 2: Development environment
FROM base AS development

# Install Xdebug for development
RUN pecl install xdebug \
    && docker-php-ext-enable xdebug

# Copy development PHP configuration
COPY docker/php/php-dev.ini /usr/local/etc/php/conf.d/99-custom.ini

# Copy application code
COPY . .

# Set proper ownership and permissions for Laravel
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 775 /var/www/html/storage \
    && chmod -R 775 /var/www/html/bootstrap/cache

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

# Install Node.js and npm for frontend builds
RUN apk add --no-cache nodejs npm

# Install Node dependencies and build assets
RUN npm ci --only=production \
    && npm run build \
    && rm -rf node_modules

# Create required directories for genealogy application
RUN mkdir -p storage/app/public/photos \
    storage/app/public/gedcom \
    storage/app/backups \
    storage/logs \
    && chown -R www-data:www-data storage \
    && chmod -R 775 storage

# Copy supervisor and nginx configurations
COPY docker/supervisor/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf

# Expose port 80
EXPOSE 80

# Start supervisor (manages nginx and php-fpm)
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]

# Stage 3: Production environment
FROM base AS production

# Copy production PHP configuration
COPY docker/php/php-prod.ini /usr/local/etc/php/conf.d/99-custom.ini

# Copy application code
COPY . .

# Set proper ownership and permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 775 /var/www/html/storage \
    && chmod -R 775 /var/www/html/bootstrap/cache

# Install only production dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist \
    && composer dump-autoload --optimize

# Install Node.js and npm for frontend builds
RUN apk add --no-cache nodejs npm

# Install Node dependencies and build assets for production
RUN npm ci --only=production \
    && npm run build \
    && rm -rf node_modules

# Create required directories
RUN mkdir -p storage/app/public/photos \
    storage/app/public/gedcom \
    storage/app/backups \
    storage/logs \
    && chown -R www-data:www-data storage \
    && chmod -R 775 storage

# Remove development files
RUN rm -rf \
    tests \
    phpunit.xml \
    .env.example \
    docker \
    README*.md

# Copy supervisor and nginx configurations
COPY docker/supervisor/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf

# Health check
HEALTHCHECK --interval=30s --timeout=10s --start-period=60s --retries=3 \
    CMD curl -f http://localhost/health || exit 1

# Expose port 80
EXPOSE 80

# Start supervisor
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]