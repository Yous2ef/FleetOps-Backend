# Multi-stage build for optimized Laravel API with SQL Server support
FROM php:8.3-fpm-alpine AS base

# Install system dependencies and PHP extensions in one layer
# RUN apk add \ # if you want to reduce image size, but it may cause issues with some extensions
RUN apk add --no-cache \
    bash \
    curl \
    git \
    unzip \
    libzip-dev \
    oniguruma-dev \
    unixodbc-dev \
    freetds-dev \
    autoconf \
    g++ \
    make \
    icu-dev \
    && docker-php-ext-install \
    pdo_mysql \
    mbstring \
    zip \
    bcmath \
    opcache \
    intl

# Install SQL Server drivers
COPY docker/build_deps/msodbcsql18_18.2.2.1-1_amd64.apk docker/build_deps/mssql-tools18_18.2.1.1-1_amd64.apk /tmp/
RUN apk add --allow-untrusted /tmp/msodbcsql18_18.2.2.1-1_amd64.apk \
    && apk add --allow-untrusted /tmp/mssql-tools18_18.2.1.1-1_amd64.apk \
    && rm -f /tmp/msodbcsql18_18.2.2.1-1_amd64.apk /tmp/mssql-tools18_18.2.1.1-1_amd64.apk

# Install PHP SQL Server extensions

#old one that failed with Khalid
#RUN pecl install sqlsrv pdo_sqlsrv \
#    && docker-php-ext-enable sqlsrv pdo_sqlsrv

COPY docker/build_deps/sqlsrv-5.12.0.tgz docker/build_deps/pdo_sqlsrv-5.12.0.tgz /tmp/
RUN pecl install /tmp/sqlsrv-5.12.0.tgz /tmp/pdo_sqlsrv-5.12.0.tgz \
    && docker-php-ext-enable sqlsrv pdo_sqlsrv \
    && rm /tmp/sqlsrv-5.12.0.tgz /tmp/pdo_sqlsrv-5.12.0.tgz

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Configure PHP for production performance
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

WORKDIR /var/www/html

# ============================================
# Builder stage - install dependencies
# ============================================
FROM base AS builder

COPY composer.json composer.lock ./

# Install dependencies with optimizations
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-autoloader \
    --prefer-dist \
    --optimize-autoloader

COPY . .

# Ensure .env.example is available for initialization
RUN test -f .env.example || echo "Warning: .env.example not found"

# Generate optimized autoloader
RUN composer dump-autoload --optimize --classmap-authoritative

# ============================================
# Production stage
# ============================================
FROM base AS production

# Copy only necessary files from builder
COPY --from=builder --chown=www-data:www-data /var/www/html /var/www/html

# Copy entrypoint script
COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Copy PHP configurations
COPY docker/php/php.ini /usr/local/etc/php/conf.d/custom.ini
COPY docker/php/php-fpm.conf /usr/local/etc/php-fpm.d/www.conf

# Create optimized PHP configuration
RUN echo "opcache.enable=1" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.memory_consumption=256" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.interned_strings_buffer=16" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.max_accelerated_files=20000" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.validate_timestamps=0" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.fast_shutdown=1" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "realpath_cache_size=4096K" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "realpath_cache_ttl=600" >> /usr/local/etc/php/conf.d/opcache.ini

# Set proper permissions
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

USER www-data

EXPOSE 9000

ENTRYPOINT ["docker-entrypoint.sh"]

# ============================================
# Development stage
# ============================================
FROM production AS development

USER root
# Install development dependencies
RUN composer install \
    --prefer-dist \
    --optimize-autoloader

USER www-data

