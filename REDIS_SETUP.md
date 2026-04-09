# Redis Setup Guide

## Why Redis?

Redis provides **sub-millisecond** cache operations compared to database queries that take 10-100ms. This significantly improves:
- Cache hit/latency for RBAC table lookups
- Session storage (if switched from database)
- Query result caching (future optimization)
- Queue performance (when using Redis queue driver)

## Installation

### Windows

1. **Option 1: Use Memurai (Recommended for Windows)**
   - Download from: https://www.memurai.com/get-memurai
   - Install and run as Windows service
   - Memurai is Redis-compatible and works out of the box

2. **Option 2: Use Docker**
   ```bash
   docker run -d -p 6379:6379 --name redis redis:latest
   ```

3. **Option 3: Use WSL2**
   ```bash
   sudo apt update
   sudo apt install redis-server
   sudo service redis-server start
   ```

### Linux/macOS

```bash
# Ubuntu/Debian
sudo apt install redis-server
sudo systemctl enable redis-server

# macOS
brew install redis
brew services start redis
```

## Configuration

### 1. Verify `.env` Settings

Make sure your `.env` file has these settings:

```env
CACHE_STORE=redis

REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

### 2. Install PHP Redis Extension

```bash
# Check if extension is installed
php -m | grep redis

# If not installed, install via Composer (predis as fallback)
composer require predis/predis

# Or install phpredis extension (recommended for performance)
# Windows: Download from https://pecl.php.net/package/redis
# Linux: sudo apt install php-redis
# macOS: brew install php@8.x-redis
```

### 3. Test Redis Connection

```bash
# Test Redis connectivity
php artisan tinker

# In tinker:
Redis::ping();  // Should return: "+PONG"
Cache::store('redis')->put('test', 'value', 60);
Cache::store('redis')->get('test');  // Should return: "value"
```

## Verification

After setting up Redis, you should see:
- Faster cache operations in debug logs
- Reduced database load (check `cache` table - should have fewer queries)
- Improved chatbot response times (RBAC lookups are cached in Redis)

## Troubleshooting

### Connection Refused Error
- Ensure Redis server is running: `redis-cli ping` (should return `PONG`)
- Check firewall settings allow port 6379
- Verify `REDIS_HOST` and `REDIS_PORT` in `.env`

### PHP Extension Not Found
- Try fallback driver: Change `REDIS_CLIENT=phpredis` to `REDIS_CLIENT=predis` in `.env`
- Install predis: `composer require predis/predis`

### Cache Not Working
- Clear cache: `php artisan cache:clear`
- Verify cache prefix doesn't conflict with other apps
- Check Redis keys: `redis-cli KEYS '*'`

## Performance Monitoring

Monitor Redis usage:
```bash
# Real-time monitoring
redis-cli MONITOR

# Stats
redis-cli INFO stats

# Memory usage
redis-cli INFO memory
```

## Next Steps

After Redis is set up:
1. Monitor cache hit rates in Laravel debugbar
2. Consider switching `SESSION_DRIVER=redis` for faster sessions
3. Consider switching `QUEUE_CONNECTION=redis` for faster queue processing
4. Implement query result caching (Priority #4) to further reduce database load
