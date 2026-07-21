FROM dunglas/frankenphp:php8.2

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    libpq-dev \
    nodejs \
    npm \
    sqlite3 \
    libsqlite3-dev \
    libzip-dev

# Clear cache
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN install-php-extensions pdo_mysql pdo_pgsql pdo_sqlite mbstring exif pcntl bcmath gd zip

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /app

# Copy existing application directory contents
COPY . /app

# Install Node modules and build
RUN npm install && npm run build

# Install PHP dependencies
RUN composer install --optimize-autoloader --no-dev

# Set up Laravel Octane for FrankenPHP
RUN php artisan octane:install --server=frankenphp

# Ensure permissions (FrankenPHP runs as root by default, but we should make sure storage is writable)
RUN chmod -R 777 /app/storage /app/bootstrap/cache

EXPOSE 5000

# Start Laravel Octane using FrankenPHP
CMD ["php", "artisan", "octane:start", "--server=frankenphp", "--host=0.0.0.0", "--port=5000", "--admin-port=2019"]
