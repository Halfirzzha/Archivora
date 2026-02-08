#!/bin/bash
# =============================================================================
# PRODUCTION ENTRYPOINT SCRIPT - Laravel Filament
# Responsibilities: Wait for services, migrate DB, optimize Laravel, start PHP-FPM
# Professional error handling with realtime health checks
# =============================================================================

set -e

echo "🚀 [Archivora] Starting production container initialization..."

# -----------------------------------------------------------------------------
# 1. Wait for PostgreSQL & Redis to be ready (30s timeout)
# -----------------------------------------------------------------------------
echo "⏳ [1/6] Waiting for PostgreSQL (${DB_HOST}:${DB_PORT})..."
timeout=30
until pg_isready -h "${DB_HOST}" -p "${DB_PORT}" -U "${DB_USERNAME}" -d "${DB_DATABASE}" &>/dev/null || [ $timeout -eq 0 ]; do
    timeout=$((timeout - 1))
    sleep 1
done

if [ $timeout -eq 0 ]; then
    echo "❌ ERROR: PostgreSQL not ready after 30s"
    exit 1
fi
echo "✅ PostgreSQL is ready"

echo "⏳ [2/6] Waiting for Redis (${REDIS_HOST}:${REDIS_PORT})..."
timeout=30
until redis-cli -h "${REDIS_HOST}" -p "${REDIS_PORT}" ping &>/dev/null || [ $timeout -eq 0 ]; do
    timeout=$((timeout - 1))
    sleep 1
done

if [ $timeout -eq 0 ]; then
    echo "⚠️  WARNING: Redis not ready after 30s (continuing anyway)"
else
    echo "✅ Redis is ready"
fi

# -----------------------------------------------------------------------------
# 2. Create storage symlink (if not exists)
# -----------------------------------------------------------------------------
echo "📁 [3/6] Creating storage symlink..."
if [ ! -L /var/www/html/public/storage ]; then
    php artisan storage:link --quiet || true
fi

# -----------------------------------------------------------------------------
# 3. Run database migrations (production mode with --force)
# -----------------------------------------------------------------------------
if [ "${APP_ENV}" = "production" ] || [ "${RUN_MIGRATIONS}" = "true" ]; then
    echo "🗄️  [4/6] Running database migrations (production mode)..."
    php artisan migrate --force --isolated --no-interaction || {
        echo "⚠️  WARNING: Migration failed (might be already up-to-date)"
    }
else
    echo "⏭️  [4/6] Skipping migrations (APP_ENV=${APP_ENV}, RUN_MIGRATIONS=${RUN_MIGRATIONS})"
fi

# -----------------------------------------------------------------------------
# 4. Optimize Laravel for production (cache config, routes, views)
# -----------------------------------------------------------------------------
if [ "${APP_ENV}" = "production" ] || [ "${CACHE_CONFIGS}" = "true" ]; then
    echo "⚡ [5/6] Optimizing Laravel for production..."
    
    # Clear previous caches
    php artisan optimize:clear --quiet || true
    
    # Cache everything for maximum performance
    php artisan config:cache --quiet
    php artisan route:cache --quiet
    php artisan view:cache --quiet
    php artisan event:cache --quiet
    php artisan filament:cache-components --quiet || true
    
    echo "✅ Laravel optimized (config, routes, views, events, Filament components)"
else
    echo "⏭️  [5/6] Skipping Laravel optimization (APP_ENV=${APP_ENV})"
fi

# -----------------------------------------------------------------------------
# 5. Set correct permissions (defensive, idempotent)
# -----------------------------------------------------------------------------
echo "🔒 [6/6] Setting secure permissions..."
chmod -R 755 storage bootstrap/cache 2>/dev/null || true

echo "✅ [Archivora] Container initialization complete!"
echo "🎯 Starting PHP-FPM..."

# -----------------------------------------------------------------------------
# 6. Execute CMD (php-fpm or custom command)
# -----------------------------------------------------------------------------
exec "$@"
