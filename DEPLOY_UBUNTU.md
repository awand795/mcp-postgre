# Panduan Deploy di Ubuntu 22.04 + PHP 8.2-FPM + Nginx

## 📋 Prerequisites

Yang sudah Anda miliki:
- ✅ Ubuntu 22.04 server
- ✅ PHP 8.2-FPM installed
- ✅ Nginx installed
- ✅ PostgreSQL installed
- ✅ Laravel project sudah di-upload ke server

Yang akan kita install:
- Redis server
- PHP Redis extension
- Laravel Octane + Swoole (optional tapi recommended)
- Optimasi Nginx + PHP-FPM

---

## 🚀 STEP 1: Setup Redis Server

### 1.1 Install Redis

```bash
sudo apt update
sudo apt install redis-server -y
```

### 1.2 Enable & Start Redis

```bash
sudo systemctl enable redis-server
sudo systemctl start redis-server
sudo systemctl status redis-server
```

Harusnya output: `active (running)`

### 1.3 Test Redis

```bash
redis-cli ping
```

Harusnya output: `PONG`

### 1.4 Configure Redis (Optional - Security)

```bash
sudo nano /etc/redis/redis.conf
```

Cari dan ubah baris ini:

```conf
# Bind ke localhost only (default sudah benar)
bind 127.0.0.1 ::1

# Set password (optional, recommended untuk production)
requirepass your_secure_password_here

# Disable dangerous commands (optional)
rename-command FLUSHDB ""
rename-command FLUSHALL ""
rename-command DEBUG ""
```

Kalau Anda set password, restart Redis:

```bash
sudo systemctl restart redis-server
```

### 1.5 Install PHP Redis Extension

```bash
# Install phpredis (recommended, lebih cepat dari predis)
sudo apt install php8.2-redis -y

# Restart PHP-FPM agar load extension baru
sudo systemctl restart php8.2-fpm

# Verify installation
php -m | grep redis
```

Harusnya output: `redis`

Kalau tidak ada di repository, install via PECL:

```bash
sudo apt install php8.2-dev php-pear -y
sudo pecl install redis
echo "extension=redis.so" | sudo tee /etc/php/8.2/mods-available/redis.ini
sudo phpenmod redis
sudo systemctl restart php8.2-fpm
```

---

## 🔧 STEP 2: Update Laravel .env

### 2.1 Edit .env File

```bash
cd /path/to/your/laravel/project
nano .env
```

### 2.2 Update Configuration Ini

```env
# Cache - GANTI dari database ke redis
CACHE_STORE=redis
CACHE_PREFIX=laravel_cache_

# Redis Configuration
REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
REDIS_DB=0
REDIS_CACHE_DB=1

# Session - OPTIONAL: bisa juga pindah ke Redis (lebih cepat)
SESSION_DRIVER=database
# SESSION_DRIVER=redis  # Uncomment kalau mau session di Redis

# Queue - OPTIONAL: kalau mau implement queue nanti
QUEUE_CONNECTION=database
# QUEUE_CONNECTION=redis  # Uncomment kalau sudah setup queue

# App Environment - PENTING untuk production
APP_ENV=production
APP_DEBUG=false
LOG_LEVEL=error
```

**Catatan tentang Redis Password:**
- Kalau Anda TIDAK set password di Redis: `REDIS_PASSWORD=null`
- Kalau Anda SET password di Redis: `REDIS_PASSWORD=your_secure_password_here`

### 2.3 Test Redis Connection dari Laravel

```bash
php artisan tinker
```

Di dalam tinker:

```php
>>> Redis::ping();
// Harus return: "+PONG"

>>> Cache::store('redis')->put('test_key', 'test_value', 60);
>>> Cache::store('redis')->get('test_key');
// Harus return: "test_value"

>>> exit
```

Kalau error, cek:
- Redis running: `sudo systemctl status redis-server`
- PHP extension loaded: `php -m | grep redis`
- .env sudah benar: `cat .env | grep REDIS`

---

## 🗄️ STEP 3: Run Migration & Clear Caches

### 3.1 Set File Permissions

```bash
cd /path/to/your/laravel/project

# Set ownership (ganti www-data sesuai user Nginx Anda)
sudo chown -R www-data:www-data /path/to/your/laravel/project

# Set permissions
sudo find /path/to/your/laravel/project -type f -exec chmod 644 {} \;
sudo find /path/to/your/laravel/project -type d -exec chmod 755 {} \;
sudo chmod -R 775 /path/to/your/laravel/project/storage
sudo chmod -R 775 /path/to/your/laravel/project/bootstrap/cache
```

### 3.2 Install Dependencies

```bash
# Install composer dependencies (production mode)
composer install --optimize-autoloader --no-dev

# Kalau error memory limit:
# COMPOSER_MEMORY_LIMIT=-1 composer install --optimize-autoloader --no-dev
```

### 3.3 Build Frontend Assets

```bash
# Install npm dependencies
npm ci

# Build untuk production
npm run build
```

### 3.4 Run Database Migrations

```bash
php artisan migrate --force
```

### 3.5 Clear All Caches

```bash
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear
php artisan event:clear
```

---

## ⚡ STEP 4: Cache Configuration (Production Mode)

### 4.1 Cache Config

```bash
php artisan config:cache
```

### 4.2 Cache Routes

```bash
php artisan route:cache
```

### 4.3 Cache Views

```bash
php artisan view:cache
```

### 4.4 Optimize Autoloader

```bash
composer dump-autoload --optimize
```

### 4.5 Verify Cache Files

```bash
# Cek file cache ada
ls -lh bootstrap/cache/

# Harusnya ada:
# config.php (cached config)
# routes-v7.php (cached routes)
# services.php (cached services)
```

### 4.6 Test Application

```bash
# Clear browser cache, lalu test manual
curl -I http://your-domain-or-ip/chatbot

# Harusnya return HTTP 200 (atau 302 redirect kalau belum login)
```

---

## 🌐 STEP 5: Configure Nginx

### 5.1 Create/Update Nginx Config

```bash
sudo nano /etc/nginx/sites-available/your-domain.com
```

### 5.2 Paste Configuration Ini

```nginx
server {
    listen 80;
    server_name your-domain.com www.your-domain.com;  # GANTI dengan domain Anda
    root /path/to/your/laravel/project/public;  # GANTI dengan path project Anda

    index index.php index.html;

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    # Gzip compression
    gzip on;
    gzip_vary on;
    gzip_proxied any;
    gzip_comp_level 6;
    gzip_types text/plain text/css text/xml application/json application/javascript application/rss+xml application/atom+xml image/svg+xml;

    # Static assets - cache 1 tahun
    location ~* \.(css|js|jpg|jpeg|png|gif|ico|svg|woff|woff2|ttf|eot)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        access_log off;
    }

    # Main location - try files then pass to PHP
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # PHP-FPM configuration
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;  # Pastikan socket benar
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_index index.php;

        # Security: deny access to .htaccess
        deny all;
    }

    # SSE (Server-Sent Events) untuk chatbot streaming
    location /chatbot/send {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;

        # SSE-specific settings
        fastcgi_buffering off;
        fastcgi_cache off;
        proxy_buffering off;
        
        # Increase timeout untuk streaming
        fastcgi_read_timeout 300s;
        fastcgi_send_timeout 300s;
        
        # Headers untuk SSE
        add_header 'Cache-Control' 'no-cache';
        add_header 'X-Accel-Buffering' 'no';
    }

    # Deny access to hidden files
    location ~ /\.(?!well-known).* {
        deny all;
    }

    # Deny access to storage files (kecuali yang public)
    location ~ ^/storage/ {
        deny all;
    }

    # Health check endpoint (optional)
    location /health {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # Log files
    access_log /var/log/nginx/your-domain-access.log;
    error_log /var/log/nginx/your-domain-error.log;
}
```

### 5.3 Enable Site

```bash
# Create symlink
sudo ln -s /etc/nginx/sites-available/your-domain.com /etc/nginx/sites-enabled/

# Remove default site (optional)
sudo rm /etc/nginx/sites-enabled/default

# Test Nginx config
sudo nginx -t

# Harusnya: "syntax is ok" dan "test is successful"

# Reload Nginx
sudo systemctl reload nginx
```

### 5.4 Verify PHP-FPM Socket

```bash
# Cek socket file ada
ls -lh /var/run/php/php8.2-fpm.sock

# Kalau tidak ada, cek PHP-FPM config
sudo nano /etc/php/8.2/fpm/pool.d/www.conf

# Cari baris ini (harusnya sekitar line 37):
# listen = /var/run/php/php8.2-fpm.sock

# Kalau pakai TCP instead:
# listen = 127.0.0.1:9000

# Kalau pakai TCP, update Nginx:
# fastcgi_pass 127.0.0.1:9000;
```

### 5.5 Restart PHP-FPM

```bash
sudo systemctl restart php8.2-fpm
sudo systemctl status php8.2-fpm
```

---

## 🔥 STEP 6: Optimize PHP-FPM

### 6.1 Edit PHP-FPM Pool Config

```bash
sudo nano /etc/php/8.2/fpm/pool.d/www.conf
```

### 6.2 Update Configuration

```ini
; Process manager
pm = dynamic
pm.max_children = 50           ; Max 50 child processes
pm.start_servers = 5           ; Start dengan 5
pm.min_spare_servers = 5       ; Minimal 5 idle
pm.max_spare_servers = 20      ; Maksimal 20 idle
pm.max_requests = 500          ; Recycle setelah 500 requests (anti memory leak)

; Request timeout
request_terminate_timeout = 300s  ; 5 menit timeout (untuk export besar)

; PHP settings
php_admin_value[memory_limit] = 512M
php_admin_value[max_execution_time] = 300
php_admin_value[upload_max_filesize] = 50M
php_admin_value[post_max_size] = 50M
php_admin_value[max_input_time] = 300

; Opcache (sangat penting untuk performance)
php_admin_value[opcache.enable] = 1
php_admin_value[opcache.memory_consumption] = 256
php_admin_value[opcache.interned_strings_buffer] = 16
php_admin_value[opcache.max_accelerated_files] = 20000
php_admin_value[opcache.validate_timestamps] = 0  ; 0 untuk production (lebih cepat)
php_admin_value[opcache.fast_shutdown] = 1
```

**Catatan Penting:**
- `pm.max_children`: Sesuaikan dengan RAM server
  - 2GB RAM: 20
  - 4GB RAM: 50
  - 8GB RAM: 100
- `opcache.validate_timestamps = 0`: Sangat cepat TAPI setelah deploy harus restart PHP-FPM

### 6.3 Verify OPcache Enabled

```bash
# Cek opcache sudah aktif
php -i | grep opcache.enable

# Harusnya: opcache.enable => On => On
```

### 6.4 Restart PHP-FPM

```bash
sudo systemctl restart php8.2-fpm
sudo systemctl status php8.2-fpm
```

---

## 📊 STEP 7: Setup Monitoring & Logs

### 7.1 Create Log Rotation

```bash
sudo nano /etc/logrotate.d/laravel
```

Paste:

```
/path/to/your/laravel/project/storage/logs/*.log {
    daily
    missingok
    rotate 14
    compress
    delaycompress
    notifempty
    create 0640 www-data www-data
    sharedscripts
    postrotate
        systemctl reload php8.2-fpm > /dev/null 2>&1 || true
    endscript
}
```

### 7.2 Monitor Logs Real-time

```bash
# Laravel logs
tail -f /path/to/your/laravel/project/storage/logs/laravel.log

# Nginx access log
tail -f /var/log/nginx/your-domain-access.log

# Nginx error log
tail -f /var/log/nginx/your-domain-error.log

# PHP-FPM slow log
tail -f /var/log/php8.2-fpm.log.slow
```

### 7.3 Enable PHP-FPM Slow Log (Optional)

```bash
sudo nano /etc/php/8.2/fpm/pool.d/www.conf
```

Tambahkan:

```ini
slowlog = /var/log/php8.2-fpm.log.slow
request_slowlog_timeout = 10s
```

```bash
sudo systemctl restart php8.2-fpm
```

---

## 🧪 STEP 8: Testing & Benchmarking

### 8.1 Test Basic Connectivity

```bash
# Test web
curl -I http://your-domain.com

# Harusnya: HTTP/1.1 200 OK atau 302 Found

# Test chatbot endpoint
curl http://your-domain.com/chatbot

# Test Redis dari CLI
php artisan tinker --execute="echo Redis::ping();"
```

### 8.2 Install Apache Bench (Benchmark Tool)

```bash
sudo apt install apache2-utils -y
```

### 8.3 Benchmark Before Optimization

```bash
# 100 requests, 10 concurrent
ab -n 100 -c 10 http://your-domain.com/

# Catat hasilnya:
# - Requests per second
# - Time per request (mean)
```

### 8.4 Benchmark After Optimization

Setelah semua setup selesai, test lagi:

```bash
ab -n 100 -c 10 http://your-domain.com/

# Bandingkan dengan before - harusnya 2-3x lebih cepat
```

### 8.5 Database Performance Test

```bash
# Login ke PostgreSQL
psql -U your_user -d your_database

# Cek indexes sudah ada
SELECT tablename, indexname, indexdef 
FROM pg_indexes 
WHERE tablename IN ('chat_messages', 'chat_sessions', 'users')
AND indexname LIKE 'idx_%';

# Harusnya return 4 indexes yang kita buat
```

---

## 🚀 STEP 9: (OPTIONAL) Install Laravel Octane

### 9.1 Install Swoole

```bash
# Install dependencies
sudo apt install php8.2-dev php-pear -y

# Install Swoole via PECL
sudo pecl install swoole

# Enable extension
echo "extension=swoole.so" | sudo tee /etc/php/8.2/mods-available/swoole.ini
sudo phpenmod swoole

# Verify
php -m | grep swoole

# Restart PHP-FPM
sudo systemctl restart php8.2-fpm
```

**Catatan:** Kalau Swoole tidak compatible dengan PHP 8.2, gunakan RoadRunner:

```bash
# Alternative: Install RoadRunner
composer require spiral/roadrunner-http spiral/roadrunner-cli
./vendor/bin/rr get-binary
```

### 9.2 Install Laravel Octane

```bash
cd /path/to/your/laravel/project

# Install Octane
composer require laravel/octane

# Install Octane (pilih swoole saat ditanya)
php artisan octane:install --server=swoole
```

### 9.3 Configure Octane

```bash
nano config/octane.php
```

Pastikan settings ini:

```php
'swoole' => [
    'options' => [
        'http_compression' => true,
        'http_compression_level' => 6,
        'buffer_output_size' => 10 * 1024 * 1024, // 10MB
        'package_max_length' => 10 * 1024 * 1024, // 10MB
        'task_worker_num' => swoole_cpu_num() * 2,
        'task_max_request' => 1000,
        'max_wait_time' => 5,
        'enable_coroutine' => true,
    ],
],

'memory_limit' => 512, // Auto-recycle worker

'warm' => [
    '/',
    '/chatbot',
],
```

### 9.4 Start Octane

```bash
# Test dulu
php artisan octane:start --port=8000

# Di terminal lain, test
curl http://localhost:8000/chatbot

# Kalau sudah OK, stop (Ctrl+C)
```

### 9.5 Run Octane as Daemon (Production)

```bash
# Install Supervisor
sudo apt install supervisor -y

# Create Supervisor config
sudo nano /etc/supervisor/conf.d/laravel-octane.conf
```

Paste:

```ini
[program:laravel-octane]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/your/laravel/project/artisan octane:start --host=127.0.0.1 --port=8000 --workers=4
autostart=true
autorestart=true
stopasuser=false
killasgroup=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/log/supervisor/octane-%(program_name)s.log
stopwaitsecs=3600
```

```bash
# Reload Supervisor
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start laravel-octane:*

# Check status
sudo supervisorctl status
```

### 9.6 Update Nginx untuk Proxy ke Octane

Tambahkan di Nginx config:

```nginx
# Upstream ke Octane
upstream octane {
    server 127.0.0.1:8000;
}

server {
    # ... config sebelumnya ...

    # Route tertentu ke Octane (chatbot SSE streaming)
    location /chatbot {
        proxy_http_version 1.1;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_cache_bypass $http_upgrade;
        
        # SSE settings
        proxy_buffering off;
        proxy_cache off;
        proxy_read_timeout 300s;
        
        proxy_pass http://octane;
    }

    # Route lain tetap ke PHP-FPM
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
}
```

```bash
# Test & reload
sudo nginx -t
sudo systemctl reload nginx
```

---

## 🔧 STEP 10: Create Deployment Script

### 10.1 Create Deploy Script

```bash
cd /path/to/your/laravel/project
nano deploy.sh
```

Paste script yang sudah saya buat (atau versi Ubuntu):

```bash
#!/bin/bash

# Laravel Production Deployment Script for Ubuntu
set -e

GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

echo -e "${YELLOW}🚀 Starting Laravel Production Optimization...${NC}"
echo ""

cd /path/to/your/laravel/project  # GANTI dengan path Anda

# 1. Clear caches
echo -e "${YELLOW}Step 1: Clearing caches...${NC}"
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear
php artisan event:clear
echo -e "${GREEN}✓ Caches cleared${NC}"

# 2. Install dependencies
echo -e "${YELLOW}Step 2: Installing dependencies...${NC}"
composer install --optimize-autoloader --no-dev
echo -e "${GREEN}✓ Dependencies installed${NC}"

# 3. Build assets
echo -e "${YELLOW}Step 3: Building assets...${NC}"
npm ci
npm run build
echo -e "${GREEN}✓ Assets built${NC}"

# 4. Run migrations
echo -e "${YELLOW}Step 4: Running migrations...${NC}"
php artisan migrate --force
echo -e "${GREEN}✓ Migrations completed${NC}"

# 5. Cache config
echo -e "${YELLOW}Step 5: Caching config...${NC}"
php artisan config:cache
echo -e "${GREEN}✓ Config cached${NC}"

# 6. Cache routes
echo -e "${YELLOW}Step 6: Caching routes...${NC}"
php artisan route:cache
echo -e "${GREEN}✓ Routes cached${NC}"

# 7. Cache views
echo -e "${YELLOW}Step 7: Caching views...${NC}"
php artisan view:cache
echo -e "${GREEN}✓ Views cached${NC}"

# 8. Optimize autoloader
echo -e "${YELLOW}Step 8: Optimizing autoloader...${NC}"
composer dump-autoload --optimize
echo -e "${GREEN}✓ Autoloader optimized${NC}"

# 9. Set permissions
echo -e "${YELLOW}Step 9: Setting permissions...${NC}"
sudo chown -R www-data:www-data /path/to/your/laravel/project
sudo chmod -R 755 storage bootstrap/cache
echo -e "${GREEN}✓ Permissions set${NC}"

# 10. Restart PHP-FPM (jika opcache.validate_timestamps = 0)
echo -e "${YELLOW}Step 10: Restarting PHP-FPM...${NC}"
sudo systemctl restart php8.2-fpm
echo -e "${GREEN}✓ PHP-FPM restarted${NC}"

echo ""
echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}✓ Deployment complete!${NC}"
echo -e "${GREEN}========================================${NC}"
```

### 10.2 Make Executable & Test

```bash
chmod +x deploy.sh

# Test run
./deploy.sh
```

### 10.3 Setup Git Pull + Deploy (Optional)

```bash
# Create update script
nano update.sh
```

```bash
#!/bin/bash
cd /path/to/your/laravel/project

echo "Pulling latest code..."
git pull origin main

echo "Running deployment..."
./deploy.sh
```

```bash
chmod +x update.sh

# Sekarang kalau mau deploy tinggal:
./update.sh
```

---

## 📊 STEP 11: Verify Everything Works

### 11.1 Checklist

```bash
# ✅ Redis
redis-cli ping
php artisan tinker --execute="echo Cache::store('redis')->get('test') ?: 'Redis working';"

# ✅ Database indexes
psql -U your_user -d your_database -c "\di idx_*"

# ✅ Cache files
ls -lh bootstrap/cache/

# ✅ PHP-FPM
sudo systemctl status php8.2-fpm

# ✅ Nginx
sudo systemctl status nginx
sudo nginx -t

# ✅ OPcache
php -i | grep opcache.enable

# ✅ Application
curl -I http://your-domain.com
```

### 11.2 Test Chat Pagination

1. Buka browser: `http://your-domain.com/chatbot`
2. Login
3. Buat chat yang panjang (min 50 pesan)
4. Refresh page
5. Harusnya:
   - ✅ Load chat instan (hanya 50 pesan terakhir)
   - ✅ Ada tombol "Muat Pesan Lebih Awal" di atas
   - ✅ Klik tombol itu, loading pesan lama

### 11.3 Test Query Caching

```bash
# Monitor Laravel logs
tail -f storage/logs/laravel.log | grep -i "cache"

# Di browser, tanya chatbot pertanyaan yang sama 2x
# Di log kedua kali, harusnya ada: "Using cached query result"
```

### 11.4 Benchmark

```bash
# Benchmark
ab -n 200 -c 20 http://your-domain.com/

# Harusnya dapat:
# - Requests per second: 200-500+ (tanpa Octane)
# - Requests per second: 1000-2000+ (dengan Octane)
# - Time per request: < 100ms
```

---

## 🛡️ STEP 12: Security Hardening (Bonus)

### 12.1 Setup Firewall (UFW)

```bash
sudo ufw allow ssh
sudo ufw allow http
sudo ufw allow https
sudo ufw enable

sudo ufw status
```

### 12.2 Setup SSL (Let's Encrypt)

```bash
# Install Certbot
sudo apt install certbot python3-certbot-nginx -y

# Get SSL certificate
sudo certbot --nginx -d your-domain.com -d www.your-domain.com

# Auto-renewal (sudah otomatis, tapi test dulu)
sudo certbot renew --dry-run
```

### 12.3 Secure .env

```bash
# Pastikan .env tidak accessible dari web
sudo chmod 640 /path/to/your/laravel/project/.env
sudo chown www-data:www-data /path/to/your/laravel/project/.env
```

Verify di Nginx config sudah ada:

```nginx
location ~ /\.(?!well-known).* {
    deny all;
}
```

### 12.4 Disable PHP Execution in Storage

```bash
# Create .htaccess (Apache) atau Nginx rule
sudo nano /etc/nginx/snippets/deny-php-in-storage.conf
```

```nginx
location ~* ^/storage/.*\.php$ {
    deny all;
}
```

Include di server block:

```nginx
include snippets/deny-php-in-storage.conf;
```

```bash
sudo nginx -t
sudo systemctl reload nginx
```

---

## 📈 STEP 13: Setup Monitoring (Optional)

### 13.1 Laravel Telescope (Development Only)

```bash
composer require laravel/telescope --dev
php artisan telescope:install
php artisan migrate

# Access: http://your-domain.com/telescope
```

### 13.2 Server Monitoring dengan Netdata

```bash
# Install Netdata
bash <(curl -Ss https://my-netdata.io/kickstart.sh)

# Access: http://your-server-ip:19999
```

### 13.3 Log Monitoring dengan Grafana + Loki

Lihat tutorial: https://grafana.com/docs/loki/latest/installation/

---

## 🔧 Troubleshooting

### Problem: 502 Bad Gateway

```bash
# Cek PHP-FPM running
sudo systemctl status php8.2-fpm

# Cek socket file
ls -lh /var/run/php/php8.2-fpm.sock

# Cek Nginx error log
tail -f /var/log/nginx/your-domain-error.log

# Restart services
sudo systemctl restart php8.2-fpm
sudo systemctl restart nginx
```

### Problem: Permission Denied

```bash
# Fix permissions
sudo chown -R www-data:www-data /path/to/your/laravel/project
sudo find /path/to/your/laravel/project -type d -exec chmod 755 {} \;
sudo find /path/to/your/laravel/project -type f -exec chmod 644 {} \;
sudo chmod -R 775 storage bootstrap/cache
```

### Problem: Redis Connection Refused

```bash
# Cek Redis running
sudo systemctl status redis-server

# Cek port
redis-cli -h 127.0.0.1 -p 6379 ping

# Cek PHP extension
php -m | grep redis

# Kalau pakai password, pastikan di .env:
REDIS_PASSWORD=your_password
```

### Problem: Cache Not Working

```bash
# Clear all caches
php artisan optimize:clear

# Rebuild caches
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Cek Redis keys
redis-cli KEYS '*'

# Test cache store
php artisan tinker
>>> Cache::store('redis')->put('test', 'value', 60);
>>> Cache::store('redis')->get('test');
```

### Problem: Chat Pagination Not Working

```bash
# Cek migration sudah applied
php artisan migrate:status

# Harusnya migration "add_performance_indexes" sudah "Ran"

# Cek route menerima parameter
php artisan route:list | grep sessions

# Test endpoint manually
curl "http://your-domain.com/chatbot/sessions/1?limit=50"
```

### Problem: OPcache Not Working

```bash
# Cek OPcache enabled
php -i | grep opcache

# Pastikan di php.ini atau pool.d/www.conf:
opcache.enable=1
opcache.memory_consumption=256

# Restart PHP-FPM
sudo systemctl restart php8.2-fpm

# Verify
php -i | grep -E "opcache.enable|opcache.memory_consumption"
```

---

## 📝 Quick Reference Commands

```bash
# ====== DEPLOYMENT ======
./deploy.sh                          # Full deployment
php artisan optimize:clear           # Clear all caches
php artisan config:cache             # Cache config only
php artisan route:cache              # Cache routes only

# ====== SERVICES ======
sudo systemctl status nginx          # Check Nginx
sudo systemctl status php8.2-fpm     # Check PHP-FPM
sudo systemctl status redis-server   # Check Redis
sudo systemctl restart nginx         # Restart Nginx
sudo systemctl restart php8.2-fpm    # Restart PHP-FPM

# ====== MONITORING ======
tail -f storage/logs/laravel.log     # Laravel logs
tail -f /var/log/nginx/*.log         # Nginx logs
redis-cli MONITOR                    # Redis real-time monitor

# ====== DATABASE ======
php artisan migrate --force          # Run migrations
php artisan migrate:rollback         # Rollback last migration
psql -U user -d database             # PostgreSQL CLI

# ====== TESTING ======
curl -I http://your-domain.com       # Test HTTP headers
ab -n 100 -c 10 http://your-domain.com/  # Benchmark
```

---

## ✅ Final Checklist

Sebelum go-live:

- [ ] Redis installed & running
- [ ] PHP Redis extension loaded
- [ ] .env updated dengan `CACHE_STORE=redis`
- [ ] Database migration applied (indexes)
- [ ] All caches cleared & rebuilt
- [ ] Nginx configured & tested
- [ ] PHP-FPM optimized
- [ ] OPcache enabled
- [ ] SSL certificate installed (production)
- [ ] Firewall configured
- [ ] .env secured (chmod 640)
- [ ] Log rotation configured
- [ ] Deployment script tested
- [ ] Chat pagination working
- [ ] Query caching working
- [ ] Benchmark done (record baseline)
- [ ] Error monitoring setup

---

## 🎯 Expected Results

Setelah semua setup:

| Metric | Before | After | Improvement |
|--------|--------|-------|-------------|
| Page load | 300-500ms | 50-150ms | **3-5x faster** |
| Chat load (1000 msg) | 3-5s | 200-400ms | **90% faster** |
| Chatbot response | 500-1000ms | 150-300ms | **60-70% faster** |
| Concurrent users | 20-50 | 200-500+ | **10x more** |
| Requests/second | 50-100 | 300-800 | **5-8x more** |

**Tanpa Octane**: 3-5x improvement  
**Dengan Octane**: 5-10x improvement

---

## 📞 Support

Kalau ada masalah atau pertanyaan, cek:
- `PERFORMANCE_OPTIMIZATION_SUMMARY.md` - Ringkasan semua optimasi
- `REDIS_SETUP.md` - Redis troubleshooting
- `OCTANE_SETUP.md` - Octane setup guide
- `QUEUE_SETUP.md` - Queue implementation guide
- `PRODUCTION_CACHING.md` - Production caching best practices

**Good luck! 🚀**
