# =============================================================================
# Production Image - darkoAI (Layered Build)
# =============================================================================
FROM awandadarkotech/darkoai:latest

WORKDIR /app

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
