# =============================================================================
# PRODUCTION-READY MULTI-STAGE DOCKERFILE - PHP 8.5 + FPM
# Professional Laravel + Filament + PostgreSQL Setup
# Security: Non-root user, minimal attack surface, read-only where possible
# Performance: Opcache, JIT, optimized extensions, multi-stage build
# =============================================================================

# -----------------------------------------------------------------------------
# STAGE 1: BUILDER - Install dependencies & build assets
# -----------------------------------------------------------------------------
FROM php:8.5-fpm-alpine AS builder

# Build arguments for environment detection
ARG INSTALL_DEV_DEPS=false

LABEL maintainer="Archivora Production Team"

# Install build dependencies & runtime packages
RUN apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        curl-dev \
        libxml2-dev \
        postgresql-dev \
        sqlite-dev \
        oniguruma-dev \
        libzip-dev \
        freetype-dev \
        libjpeg-turbo-dev \
        libpng-dev \
        libwebp-dev

RUN apk add --no-cache \
        bash \
        curl \
        git \
        freetype \
        libjpeg-turbo \
        libpng \
        libwebp \
        libzip \
        postgresql-libs \
        oniguruma \
        icu-libs

# Configure & install PHP extensions
RUN docker-php-ext-configure gd \
        --with-freetype \
        --with-jpeg \
        --with-webp \
    && docker-php-ext-install -j$(nproc) \
        bcmath \
        exif \
        gd \
        intl \
        pdo_pgsql \
        pgsql \
        pcntl \
        zip

# Install PECL extensions
RUN pecl install redis \
    && docker-php-ext-enable redis

# Clean up build dependencies
RUN apk del .build-deps && rm -rf /tmp/* /var/cache/apk/*

# Install Composer (official image method)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy composer files ONLY (for layer caching optimization)
COPY composer.json composer.lock ./

# Install PHP dependencies - conditional dev packages based on build arg
# Production: --no-dev for smallest image
# Development: Include dev deps (Pail, Pint, PHPUnit, etc.)
RUN if [ "$INSTALL_DEV_DEPS" = "true" ]; then \
        echo "🔧 Installing with DEV dependencies (local/development mode)"; \
        composer install \
            --no-scripts \
            --no-autoloader \
            --prefer-dist \
            --optimize-autoloader \
            --no-interaction \
            --no-progress; \
    else \
        echo "🚀 Installing WITHOUT dev dependencies (production mode)"; \
        composer install \
            --no-dev \
            --no-scripts \
            --no-autoloader \
            --prefer-dist \
            --optimize-autoloader \
            --no-interaction \
            --no-progress; \
    fi \
    && composer clear-cache

# Copy application code
COPY . .

# Generate optimized autoloader - conditional based on installed packages
RUN if [ "$INSTALL_DEV_DEPS" = "true" ]; then \
        composer dump-autoload --optimize --classmap-authoritative; \
    else \
        composer dump-autoload --optimize --no-dev --classmap-authoritative; \
    fi

# Set permissions for non-root user (1000:1000)
RUN chown -R 1000:1000 /var/www/html \
    && chmod -R 755 /var/www/html/storage \
    && chmod -R 755 /var/www/html/bootstrap/cache

# -----------------------------------------------------------------------------
# STAGE 2: PRODUCTION - Minimal runtime image
# -----------------------------------------------------------------------------
FROM php:8.5-fpm-alpine AS production

# Re-import build argument for this stage
ARG INSTALL_DEV_DEPS=false

LABEL maintainer="Archivora Production Team"
LABEL version="1.0.0"
LABEL description="Production-ready Laravel Filament Application"

# Install runtime dependencies ONLY (minimal attack surface)
RUN apk add --no-cache \
        bash \
        curl \
        postgresql-libs \
        postgresql-client \
        redis \
        libzip \
        freetype \
        libjpeg-turbo \
        libpng \
        libwebp \
        oniguruma \
        supervisor \
        dcron \
        libcap \
        icu-libs \
    && rm -rf /tmp/* /var/cache/apk/*

# Copy PHP extensions from builder stage
COPY --from=builder /usr/local/lib/php/extensions/ /usr/local/lib/php/extensions/
COPY --from=builder /usr/local/etc/php/conf.d/ /usr/local/etc/php/conf.d/

# Copy Composer binary
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# PHP configuration - conditional based on environment
# Development: Display errors, no function restrictions, JIT disabled for debugging
# Production: Hide errors, security functions disabled, JIT enabled for performance
COPY docker/php/php-development.ini /tmp/php-development.ini
COPY docker/php/php-production.ini /tmp/php-production.ini
RUN if [ "$INSTALL_DEV_DEPS" = "true" ]; then \
        echo "🔧 Using DEVELOPMENT PHP configuration"; \
        cp /tmp/php-development.ini /usr/local/etc/php/conf.d/99-custom.ini; \
    else \
        echo "🚀 Using PRODUCTION PHP configuration"; \
        cp /tmp/php-production.ini /usr/local/etc/php/conf.d/99-custom.ini; \
    fi \
    && rm /tmp/php-*.ini

COPY docker/php/php-fpm-production.conf /usr/local/etc/php-fpm.d/zz-production.conf

# Copy healthcheck script
COPY --chmod=755 docker/php/php-fpm-healthcheck /usr/local/bin/php-fpm-healthcheck

# Set working directory
WORKDIR /var/www/html

# Copy application from builder stage (optimized)
COPY --from=builder --chown=1000:1000 /var/www/html /var/www/html

# Copy entrypoint script
COPY --chmod=755 docker/php/entrypoint.sh /usr/local/bin/entrypoint

# Create non-root user (www-data already exists, adjust UID/GID)
RUN deluser www-data 2>/dev/null || true \
    && addgroup -g 1000 www-data \
    && adduser -D -u 1000 -G www-data -h /var/www/html -s /bin/bash www-data \
    && chown -R www-data:www-data /var/www/html

# Health check
HEALTHCHECK --interval=30s --timeout=10s --start-period=60s --retries=3 \
    CMD curl -f http://localhost:9000/health || exit 1

# Expose PHP-FPM port
EXPOSE 9000

# Switch to non-root user
USER www-data

# Entrypoint for initialization
ENTRYPOINT ["entrypoint"]

# Default command
CMD ["php-fpm"]
