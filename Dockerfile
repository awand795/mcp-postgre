# =============================================================================
# Stage 1: PHP vendor — install composer dependencies
# =============================================================================
FROM dunglas/frankenphp:php8.2-alpine AS vendor

# Install PHP extensions needed for composer & runtime
RUN install-php-extensions pdo_mysql pdo_pgsql pdo_sqlite mbstring \
    exif pcntl bcmath gd zip intl redis

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Copy only dependency files (leverage cache)
COPY composer.json composer.lock ./

# Install production dependencies only (no scripts — artisan not available yet)
RUN composer install --optimize-autoloader --no-dev --no-scripts


# =============================================================================
# Stage 2: Frontend assets — build CSS/JS with Vite
# =============================================================================
FROM node:20-alpine AS frontend

WORKDIR /build

# Copy dependency manifests
COPY package.json package-lock.json vite.config.js ./

# Install npm dependencies
RUN npm install

# Copy source files
COPY resources/ resources/

# Build production assets
RUN npm run build


# =============================================================================
# Stage 3: Final — minimal production image
# =============================================================================
FROM dunglas/frankenphp:php8.2-alpine

# Install PHP extensions (runtime only)
RUN install-php-extensions pdo_mysql pdo_pgsql pdo_sqlite mbstring \
    exif pcntl bcmath gd zip intl redis

WORKDIR /app

# Copy Composer binary (needed for Octane)
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy vendor from composer stage
COPY --from=vendor /app/vendor ./vendor

# Copy built frontend assets
COPY --from=frontend /build/public/build ./public/build

# Copy application source code (only what's needed at runtime)
COPY app/ app/
COPY bootstrap/ bootstrap/
COPY config/ config/
COPY database/ database/
COPY lang/ lang/
COPY public/ public/
COPY resources/ resources/
COPY routes/ routes/
COPY storage/ storage/
COPY artisan .
COPY composer.json composer.lock ./

# Regenerate autoloader with full app context (runs package:discover properly)
RUN composer dump-autoload --optimize

# Set up Laravel Octane for FrankenPHP
RUN php artisan octane:install --server=frankenphp

# Clear cached files and set permissions
RUN rm -rf /app/bootstrap/cache/*.php
RUN chmod -R 777 /app/storage /app/bootstrap/cache

EXPOSE 5000

CMD ["php", "artisan", "octane:start", "--server=frankenphp", "--host=0.0.0.0", "--port=5000", "--admin-port=2019"]
