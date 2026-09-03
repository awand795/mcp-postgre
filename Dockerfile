# =============================================================================
# Production Image - darkoAI
# =============================================================================
FROM dunglas/frankenphp:php8.2-alpine

# Install PHP extensions
RUN install-php-extensions pdo_mysql pdo_pgsql pdo_sqlite mbstring \
    exif pcntl bcmath gd zip intl redis

WORKDIR /app

# Copy Composer binary
COPY --from=awandadarkotech/darkoai:latest /usr/bin/composer /usr/bin/composer

# Copy vendor & build from existing image
COPY --from=awandadarkotech/darkoai:latest /app/vendor ./vendor
COPY --from=awandadarkotech/darkoai:latest /app/public/build ./public/build

# Copy application source code
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

RUN composer dump-autoload --optimize && \
    php artisan octane:install --server=frankenphp && \
    rm -rf /app/bootstrap/cache/*.php && \
    chmod -R 777 /app/storage /app/bootstrap/cache
