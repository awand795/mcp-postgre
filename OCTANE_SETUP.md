# Laravel Octane Setup Guide

## What is Laravel Octane?

Laravel Octane supercharges your application's performance by booting the application **once** and keeping it in memory. Subsequent requests are handled by the already-booted application, resulting in **5-10x faster** response times.

### Performance Comparison

| Metric | Traditional PHP-FPM | Laravel Octane (Swoole) |
|--------|---------------------|-------------------------|
| Boot time per request | 50-100ms | ~0ms (already booted) |
| Requests/second | ~100-200 | ~1000-2000+ |
| Memory per request | Full bootstrap | Reused |
| SSE Streaming | Good | Excellent (native support) |
| Concurrent connections | Limited by PHP-FPM workers | 10,000+ |

## Why Octane for This Project?

Your MCP chatbot application benefits significantly from Octane because:

1. **SSE Streaming**: Octane with Swoole has native async/connection handling
2. **Reduced Latency**: No per-request bootstrap = faster chat responses
3. **High Concurrency**: Multiple users can stream simultaneously
4. **Service Reuse**: Heavy services (ToolCallExecutor, QueryService) stay in memory
5. **Database Connections**: Connection pooling reduces overhead

## Installation

### 1. Install Octane Package

```bash
composer require laravel/octane
```

### 2. Install Swoole Extension

#### Windows (Use Docker or WSL2)

Swoole doesn't natively support Windows. Options:

**Option A: Docker (Recommended)**
```bash
docker run -d \
  --name laravel-octane \
  -p 8000:8000 \
  -v $(pwd):/var/www/html \
  -e APP_ENV=local \
  ghcr.io/laravel/sail:latest \
  php artisan octane:start --host=0.0.0.0
```

**Option B: WSL2**
```bash
# In WSL2 Ubuntu
sudo apt install php-dev php-pear php8.x-swoole
sudo phpenmod swoole
```

**Option C: Use RoadRunner (Windows-compatible)**
```bash
composer require spiral/roadrunner-http spiral/roadrunner-cli
./vendor/bin/rr get-binary
```

#### Linux

```bash
# Ubuntu/Debian
sudo apt install php-dev php-pear
sudo pecl install swoole
sudo sh -c 'echo "extension=swoole.so" > /etc/php/8.x/mods-available/swoole.ini'
sudo phpenmod swoole

# Verify installation
php -m | grep swoole
```

#### macOS

```bash
brew install swoole
# Or compile from source:
pecl install swoole
```

### 3. Publish Octane Configuration

```bash
php artisan octane:install
```

This creates `config/octane.php` with default settings.

## Configuration

### 1. Update `.env`

```env
# Octane Server
OCTANE_SERVER=swoole
# OCTANE_HOST=127.0.0.1
# OCTANE_PORT=8000
# OCTANE_WORKERS=auto  # Auto-detects CPU cores
```

### 2. Configure `config/octane.php`

```php
<?php

use Laravel\Octane\Contracts\OperationTerminated;
use Laravel\Octane\Events\RequestHandled;
use Laravel\Octane\Events\RequestReceived;
use Laravel\Octane\Events\TaskReceived;
use Laravel\Octane\Events\TickReceived;
use Laravel\Octane\Events\WorkerErrorOccurred;
use Laravel\Octane\Octane;
use Laravel\Octane\Swoole\SwooleExtension;

return [

    /*
    |--------------------------------------------------------------------------
    | Octane Server
    |--------------------------------------------------------------------------
    */

    'server' => env('OCTANE_SERVER', 'swoole'),

    /*
    |--------------------------------------------------------------------------
    | Octane Listeners
    |--------------------------------------------------------------------------
    */

    'listeners' => [
        RequestReceived::class => [
            ...Octane::prepareApplicationForNextOperation(),
            ...Octane::prepareApplicationForNextRequest(),
        ],
        RequestHandled::class => [
            // ...
        ],
        TaskReceived::class => [
            ...Octane::prepareApplicationForNextOperation(),
        ],
        TickReceived::class => [
            // ...
        ],
        OperationTerminated::class => [
            // Flush temporary state
        ],
        WorkerErrorOccurred::class => [
            // Handle worker errors
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Swoole Configuration
    |--------------------------------------------------------------------------
    */

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
        'tables' => [
            // Define shared tables for cross-worker communication
            'example:1000' => [
                'name' => 'string:1000',
                'votes' => 'int',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Warm/Flush Configuration
    |--------------------------------------------------------------------------
    */

    'warm' => [
        // Eagerly load these routes on server start
        '/',
        '/chatbot',
    ],

    'flush' => [
        // State to flush between requests
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Routes
    |--------------------------------------------------------------------------
    */

    'cache_routes' => true,

    /*
    |--------------------------------------------------------------------------
    | Memory Limit (MB) - Worker recycling
    |--------------------------------------------------------------------------
    */

    'memory_limit' => 512, // Recycle worker after 512MB to prevent leaks

];
```

### 3. Update NPM Dev Script

Update `composer.json` scripts section:

```json
{
  "scripts": {
    "dev": [
      "Composer\\Config::disableProcessTimeout",
      "npx concurrently -c \"#93c5fd,#c4b5fd,#fdba74\" \"php artisan octane:start\" \"npm run dev\" --names='octane,vite'"
    ]
  }
}
```

## Running Octane

### Development

```bash
# Start Octane
php artisan octane:start

# With specific options
php artisan octane:start --port=8000 --workers=4 --task-workers=6

# Hot reload (with Swoole)
php artisan octane:start --watch
```

### Production (Supervised)

Create `/etc/supervisor/conf.d/laravel-octane.conf`:

```ini
[program:laravel-octane]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/artisan octane:start --port=8000 --workers=4
autostart=true
autorestart=true
stopasuser=false
killasgroup=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/path/to/octane.log
stopwaitsecs=3600
```

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start laravel-octane:*
```

### Nginx Reverse Proxy (Production)

Create `/etc/nginx/sites-available/yourapp`:

```nginx
upstream octane {
    server 127.0.0.1:8000;
    # Add more workers if running multiple Octane instances
    # server 127.0.0.1:8001;
}

server {
    listen 80;
    server_name yourdomain.com;

    root /path/to/public;
    index index.php;

    # Static assets
    location /assets/ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }

    # Proxy to Octane
    location / {
        proxy_http_version 1.1;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_cache_bypass $http_upgrade;
        
        # SSE-specific settings
        proxy_buffering off;
        proxy_cache off;
        proxy_read_timeout 300s;
        
        proxy_pass http://octane;
    }

    # Fallback to PHP-FPM (if needed)
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.x-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

```bash
sudo ln -s /etc/nginx/sites-available/yourapp /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

## Testing Octane

### 1. Basic Test

```bash
# Start Octane
php artisan octane:start

# In another terminal, test response time
curl -w "@curl-format.txt" -o /dev/null -s http://localhost:8000/chatbot
```

Create `curl-format.txt`:
```
    time_namelookup:  %{time_namelookup}\n
       time_connect:  %{time_connect}\n
    time_appconnect:  %{time_appconnect}\n
   time_pretransfer:  %{time_pretransfer}\n
      time_redirect:  %{time_redirect}\n
 time_starttransfer:  %{time_starttransfer}\n
                    ----------\n
         time_total:  %{time_total}\n
```

### 2. Benchmark with Apache Bench

```bash
# 100 requests, 10 concurrent
ab -n 100 -c 10 http://localhost:8000/chatbot

# Expected: Octane handles 1000+ req/sec vs 100-200 with PHP-FPM
```

### 3. Memory Leak Testing

```bash
# Monitor memory usage over time
watch -n 1 'ps aux | grep octane | grep -v grep'

# Workers should recycle at memory_limit (512MB configured)
```

## Potential Issues & Solutions

### 1. Memory Leaks

**Symptom**: Workers consume increasing memory over time.

**Solution**:
- Set `memory_limit` in `octane.php` to auto-recycle workers
- Avoid global state accumulation
- Use `Octane::tick()` for periodic cleanup

```php
// In AppServiceProvider::boot()
Octane::tick('cleanup', function () {
    gc_collect_cycles(); // Force garbage collection
})->seconds(60);
```

### 2. Database Connection Issues

**Symptom**: "MySQL server has gone away" or PostgreSQL connection lost.

**Solution**:
Octane reuses connections. Add reconnection logic:

```php
// In AppServiceProvider::boot()
Octane::onRequestReceived(function () {
    if (DB::connection('pgsql_mbi')->getPdo()) {
        try {
            DB::connection('pgsql_mbi')->getPdo()->query('SELECT 1');
        } catch (\Exception $e) {
            DB::connection('pgsql_mbi')->reconnect();
        }
    }
});
```

### 3. Session/Cache Inconsistency

**Symptom**: Stale data between requests.

**Solution**:
- Use Redis for sessions and cache (already configured in Priority #3)
- Avoid file-based cache (race conditions with concurrent workers)

### 4. SSE Streaming Issues

**Symptom**: SSE events not flushing properly.

**Solution**:
Swoole handles SSE natively. Update your SSE endpoint:

```php
// In AgenticChatbotController@send
return response()->stream(function () {
    $this->runAgenticLoop(/* ... */);
}, 200, [
    'Content-Type' => 'text/event-stream',
    'Cache-Control' => 'no-cache',
    'X-Accel-Buffering' => 'no',
    'Connection' => 'keep-alive',
    'X-Accel-Buffering' => 'no', // Important for Nginx
]);
```

Swoole will handle flushing automatically.

## Performance Monitoring

### 1. Octane Status

```bash
php artisan octane:status
```

### 2. Swoole Statistics

```php
// In a route or tinker
$server = app(\Laravel\Octane\Swoole\SwooleExtension::class);
dump($server->stats());
```

### 3. New Relic / Telescope Integration

Octane is compatible with monitoring tools. Just ensure they're Octane-aware (don't accumulate state).

## Migration Checklist

- [ ] Install Swoole extension (`php -m | grep swoole`)
- [ ] Install Octane (`composer require laravel/octane`)
- [ ] Publish config (`php artisan octane:install`)
- [ ] Update `.env` with `OCTANE_SERVER=swoole`
- [ ] Test locally (`php artisan octane:start`)
- [ ] Update Nginx/Apache config for production
- [ ] Set up Supervisor for auto-restart
- [ ] Monitor memory usage
- [ ] Benchmark performance improvement
- [ ] Update deployment scripts to use Octane

## Expected Performance Gains

| Metric | Before (PHP-FPM) | After (Octane+Swoole) | Improvement |
|--------|------------------|-----------------------|-------------|
| Avg response time | 150-300ms | 30-60ms | **5-7x faster** |
| Requests/sec | 100-200 | 1000-2000+ | **10x more throughput** |
| Concurrent SSE connections | 10-20 (PHP-FPM workers) | 500-1000+ | **50x more concurrent** |
| Memory per request | 40-80MB (bootstrap) | 5-10MB (reused) | **80% reduction** |
| Chatbot first token | 500-1000ms | 200-400ms | **2-3x faster** |

## Rollback Plan

If Octane causes issues:

1. **Stop Octane**: `php artisan octane:stop`
2. **Revert to PHP-FPM**: Update Nginx to use PHP-FPM socket
3. **Remove from composer** (if needed): `composer remove laravel/octane`

Octane is non-destructive - your app continues to work normally with PHP-FPM.

## Next Steps

After Octane is deployed:
1. Monitor performance metrics for 1 week
2. Fine-tune worker count and memory limits
3. Consider implementing Swoole tables for cross-worker caching
4. Enable route caching (Priority #7)
5. Set up automated health checks for Octane workers
