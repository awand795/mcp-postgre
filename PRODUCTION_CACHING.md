# Production Caching & Optimization Guide

## What This Optimization Does

Laravel has many configuration files, route definitions, and Blade views that are parsed on **every request** in development. In production, caching these files eliminates redundant parsing overhead, resulting in **20-40% faster** response times.

### What Gets Cached

| Component | Development | Production (Cached) | Performance Gain |
|-----------|-------------|---------------------|------------------|
| Config files | Parsed every request | Single compiled file | 10-15ms per request |
| Route definitions | Parsed every request | Compiled route cache | 5-10ms per request |
| Blade views | Compiled on change | Pre-compiled cache | 5-15ms per view |
| Autoloader | Standard | Optimized class map | 2-5ms per request |
| Events | Scanned directories | Cached event list | 1-3ms per request |

**Total improvement**: ~23-43ms per request (adds up to 15-25% overall speedup)

## Quick Start (One-Command Deployment)

### Windows

```bash
deploy.bat
```

### Linux/macOS

```bash
chmod +x deploy.sh
./deploy.sh
```

## Manual Optimization Commands

If you prefer to run commands individually:

```bash
# 1. Clear all existing caches
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear
php artisan event:clear

# 2. Install production dependencies (removes dev packages)
composer install --optimize-autoloader --no-dev

# 3. Build frontend assets for production
npm ci --production=false
npm run build

# 4. Run database migrations
php artisan migrate --force

# 5. Cache configuration (creates bootstrap/cache/config.php)
php artisan config:cache

# 6. Cache routes (creates bootstrap/cache/routes-v7.php)
php artisan route:cache

# 7. Cache views (compiles Blade templates)
php artisan view:cache

# 8. Optimize autoloader (creates optimized class map)
composer dump-autoload --optimize
```

## What Each Command Does

### 1. `php artisan config:cache`

**What it does**: Combines all config files (`config/*.php`, `.env`) into a single compiled file at `bootstrap/cache/config.php`.

**Performance impact**: Eliminates 30+ file reads and parses per request.

**Important notes**:
- After caching, `env()` calls outside config files return `null`
- **Always use `config()` helper in your code, not `env()`**
- If you change `.env`, you must re-run this command

**Correct usage**:
```php
// ✅ GOOD (works in cached config)
$apiKey = config('services.openai.api_key');

// ❌ BAD (returns null when config is cached)
$apiKey = env('OPENAI_API_KEY');
```

### 2. `php artisan route:cache`

**What it does**: Compiles all route definitions into a single file at `bootstrap/cache/routes-v7.php`.

**Performance impact**: Eliminates route file parsing and regex compilation on every request.

**Important notes**:
- Closures in routes cannot be cached (use controllers instead)
- If you add/modify routes, re-run this command

### 3. `php artisan view:cache`

**What it does**: Pre-compiles all Blade templates to PHP files in `storage/framework/views/`.

**Performance impact**: Eliminates Blade parsing and compilation on first view render.

**Important notes**:
- Views are still re-compiled on change in development
- In production, views are only re-compiled if you manually clear cache

### 4. `composer install --optimize-autoloader --no-dev`

**What it does**:
- Removes dev dependencies (testing, debugging packages)
- Creates optimized class map for faster autoloading

**Performance impact**:
- Smaller vendor directory (fewer files to scan)
- Faster class resolution (direct file paths vs PSR-4 lookups)

### 5. `composer dump-autoload --optimize`

**What it does**: Regenerates the autoloader with an optimized class map.

**Performance impact**: 10-20% faster class loading in large applications.

## Verifying Optimization

### Check if caches are active

```bash
# Check config cache
ls -la bootstrap/cache/config.php

# Check route cache
ls -la bootstrap/cache/routes-v7.php

# Check optimized autoloader
grep -A 5 "'classmap'" composer.json
```

### Benchmark performance

```bash
# Install Apache Bench (if not installed)
# Windows: Comes with XAMPP/WAMP
# Linux: sudo apt install apache2-utils

# Benchmark before optimization
ab -n 100 -c 10 http://yourapp.test/

# Run optimization commands
./deploy.sh

# Benchmark after optimization
ab -n 100 -c 10 http://yourapp.test/

# Compare requests/second and response times
```

### Laravel Telescope/Debugbar

If you have Laravel Debugbar installed:

1. Enable it temporarily: `php artisan debugbar:enable`
2. Load a page and check:
   - **Config**: Should show "Config loaded from cache"
   - **Routes**: Should show "Route cache enabled"
   - **Views**: Should show compiled view paths
3. Disable Debugbar: `php artisan debugbar:disable`

## Clearing Caches (When Needed)

### Clear Everything

```bash
php artisan optimize:clear
```

This clears:
- Application cache
- Config cache
- Route cache
- View cache
- Compiled services
- Events

### Clear Specific Cache

```bash
# Clear only config
php artisan config:clear

# Clear only routes
php artisan route:clear

# Clear only views
php artisan view:clear

# Clear application cache
php artisan cache:clear
```

### When to Clear Caches

| Scenario | Command to Run |
|----------|----------------|
| Changed `.env` file | `php artisan config:cache` |
| Added/modified routes | `php artisan route:cache` |
| Updated config files | `php artisan config:cache` |
| Deployed new code | Run full `deploy.sh` |
| Debugging config issues | `php artisan config:clear` |
| Testing new features | `php artisan optimize:clear` |

## CI/CD Integration

### GitHub Actions Example

```yaml
name: Deploy

on:
  push:
    branches: [ main ]

jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
          extensions: pdo_pgsql, redis, swoole
      
      - name: Install dependencies
        run: composer install --optimize-autoloader --no-dev
      
      - name: Build assets
        run: |
          npm ci
          npm run build
      
      - name: Optimize Laravel
        run: |
          php artisan config:cache
          php artisan route:cache
          php artisan view:cache
          php artisan optimize
      
      - name: Run migrations
        run: php artisan migrate --force
      
      - name: Deploy to server
        run: |
          rsync -avz --delete ./ user@server:/path/to/app/
          ssh user@server "php /path/to/app/artisan migrate --force"
```

### Docker Deployment

```dockerfile
FROM php:8.2-fpm

WORKDIR /var/www/html

# Install dependencies
COPY composer.json composer.lock ./
RUN composer install --optimize-autoloader --no-dev

# Copy application
COPY . .

# Build and cache
RUN npm ci && npm run build
RUN php artisan config:cache && \
    php artisan route:cache && \
    php artisan view:cache

# Set permissions
RUN chown -R www-data:www-data storage bootstrap/cache

EXPOSE 9000
CMD ["php-fpm"]
```

## Troubleshooting

### Issue: `env()` returns null after config:cache

**Cause**: `env()` should only be used in config files, not in application code.

**Solution**: 
```php
// Before (wrong)
$apiKey = env('OPENAI_API_KEY');

// After (correct)
$apiKey = config('services.openai.api_key');
```

Update your code to use `config()` helpers, then re-cache:
```bash
php artisan config:clear
php artisan config:cache
```

### Issue: Routes not found after route:cache

**Cause**: Route closures can't be cached.

**Solution**: Convert closures to controller methods:
```php
// Before (can't cache)
Route::get('/test', function () {
    return view('test');
});

// After (can cache)
Route::get('/test', [TestController::class, 'index']);
```

### Issue: "Class not found" after composer dump-autoload

**Cause**: Autoloader cache is stale.

**Solution**:
```bash
composer dump-autoload
php artisan optimize:clear
composer install --optimize-autoloader
```

### Issue: Views not updating after changes

**Cause**: View cache is serving old compiled templates.

**Solution**:
```bash
php artisan view:clear
```

## Automated Deployment Best Practices

### 1. Zero-Downtime Deployment

Use tools like **Deployer** or **Envoy** for zero-downtime deployments:

```php
// deploy.php (Deployer)
namespace Deployer;

require 'recipe/laravel.php';

set('repository', 'git@github.com:user/repo.git');
set('keep_releases', 5);

host('production')
    ->setHostname('your-server.com')
    ->set('branch', 'main')
    ->set('deploy_path', '/var/www/app');

after('deploy:update_code', 'npm:install');
after('deploy:update_code', 'npm:run:build');
after('deploy:update_code', 'composer:install');
after('deploy:update_code', 'artisan:storage:link');
after('deploy:update_code', 'artisan:config:cache');
after('deploy:update_code', 'artisan:route:cache');
after('deploy:update_code', 'artisan:view:cache');
after('deploy:update_code', 'artisan:optimize');
```

### 2. Health Checks

Add a health check endpoint:

```php
// routes/api.php
Route::get('/health', function () {
    try {
        // Check database
        DB::connection('pgsql_mbi')->getPdo();
        
        // Check cache
        Cache::store('redis')->put('health_check', 'ok', 10);
        
        // Check disk space
        $diskUsage = disk_free_space(storage_path());
        
        return response()->json([
            'status' => 'healthy',
            'database' => 'connected',
            'cache' => 'working',
            'disk_free_mb' => round($diskUsage / 1024 / 1024, 2),
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'status' => 'unhealthy',
            'error' => $e->getMessage(),
        ], 500);
    }
});
```

### 3. Rollback Script

Create `rollback.sh`:

```bash
#!/bin/bash

echo "Rolling back deployment..."

# Go back one release
cd /path/to/app

# Activate previous release
ln -sfn /path/to/app/releases/$(ls -t releases/ | head -n 2 | tail -n 1) current

# Clear caches in case of corruption
php artisan optimize:clear

# Restart Octane if using
php artisan octane:stop
php artisan octane:start --daemon

echo "Rollback complete!"
```

## Performance Monitoring After Optimization

### 1. Application Metrics

Track these metrics before and after optimization:

```bash
# Response time
curl -w "%{time_total}\n" -o /dev/null -s http://yourapp.test/chatbot

# Memory usage
ps aux | grep php | awk '{print $6/1024 " MB"}'

# Requests per second (Apache Bench)
ab -n 1000 -c 50 http://yourapp.test/
```

### 2. Log Monitoring

Monitor for cache-related issues:

```bash
tail -f storage/logs/laravel.log | grep -i "cache\|config\|route"
```

### 3. Database Query Monitoring

Ensure query caching is working (from Priority #4):

```bash
php artisan tinker
>>> Log::info('Query cache test');
>>> DB::enableQueryLog();
>>> // Run some queries
>>> count(DB::getQueryLog());  // Should decrease with query caching
```

## Complete Optimization Checklist

### Pre-Deployment
- [ ] All tests passing (`php artisan test`)
- [ ] No debug code left in production
- [ ] `APP_DEBUG=false` in `.env`
- [ ] `APP_ENV=production` in `.env`
- [ ] `LOG_LEVEL=error` or `warning` in `.env`
- [ ] Database backups scheduled
- [ ] Redis server running
- [ ] SSL certificates configured

### Deployment
- [ ] Dependencies installed (`composer install --no-dev`)
- [ ] Frontend assets built (`npm run build`)
- [ ] Migrations run (`php artisan migrate --force`)
- [ ] Config cached (`php artisan config:cache`)
- [ ] Routes cached (`php artisan route:cache`)
- [ ] Views cached (`php artisan view:cache`)
- [ ] Autoloader optimized (`composer dump-autoload --optimize`)
- [ ] Storage linked (`php artisan storage:link`)
- [ ] Permissions set (`chmod -R 755 storage bootstrap/cache`)

### Post-Deployment
- [ ] Health check endpoint responding
- [ ] Database connections working
- [ ] Cache store (Redis) operational
- [ ] Queue workers running (if using queues)
- [ ] Octane started (if using Octane)
- [ ] SSL working (HTTPS)
- [ ] Frontend assets loading
- [ ] Error monitoring setup (Sentry, etc.)

### Ongoing Maintenance
- [ ] Monitor error logs daily
- [ ] Check disk space weekly
- [ ] Review cache hit rates monthly
- [ ] Update dependencies quarterly
- [ ] Run security audits regularly
- [ ] Test rollback procedures

## Expected Performance Improvements

| Optimization | Response Time | Requests/sec | Memory |
|--------------|---------------|--------------|--------|
| Config cache | -10-15ms | +5-10% | -5MB |
| Route cache | -5-10ms | +3-5% | -2MB |
| View cache | -5-15ms | +5-10% | -3MB |
| Autoloader | -2-5ms | +2-3% | -2MB |
| **Combined** | **-22-45ms** | **+15-28%** | **-12MB** |

When combined with other optimizations (indexes, Redis, query caching, Octane):

| Full Stack | Before | After | Improvement |
|------------|--------|-------|-------------|
| Avg response time | 300-500ms | 50-100ms | **5-7x faster** |
| Peak requests/sec | 50-100 | 500-1000+ | **10x throughput** |
| Concurrent users | 20-50 | 200-500+ | **10x capacity** |
| Memory per request | 80-120MB | 20-40MB | **70% reduction** |
