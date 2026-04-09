@echo off
REM Laravel Production Deployment Script (Windows)
REM This script optimizes a Laravel application for production deployment

echo.
echo ========================================
echo Laravel Production Optimization
echo ========================================
echo.

REM Step 1: Clear all caches
echo Step 1: Clearing all caches...
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear
php artisan event:clear
echo [OK] All caches cleared
echo.

REM Step 2: Install production dependencies
echo Step 2: Installing production dependencies...
composer install --optimize-autoloader --no-dev
echo [OK] Composer dependencies installed
echo.

REM Step 3: Build frontend assets
echo Step 3: Building frontend assets...
call npm ci --production=false
call npm run build
echo [OK] Frontend assets built
echo.

REM Step 4: Run database migrations
echo Step 4: Running database migrations...
php artisan migrate --force
echo [OK] Migrations completed
echo.

REM Step 5: Cache configuration
echo Step 5: Caching configuration...
php artisan config:cache
echo [OK] Configuration cached
echo.

REM Step 6: Cache routes
echo Step 6: Caching routes...
php artisan route:cache
echo [OK] Routes cached
echo.

REM Step 7: Cache views
echo Step 7: Caching views...
php artisan view:cache
echo [OK] Views cached
echo.

REM Step 8: Optimize autoloader
echo Step 8: Optimizing autoloader...
composer dump-autoload --optimize
echo [OK] Autoloader optimized
echo.

REM Step 9: Verify installation
echo Step 9: Verifying installation...
php artisan about --only=environment
echo.

echo ========================================
echo [SUCCESS] Deployment optimization complete!
echo ========================================
echo.
echo Your Laravel application is now optimized for production.
echo Cached files can be found in:
echo   - Config: bootstrap/cache/config.php
echo   - Routes: bootstrap/cache/routes-v7.php
echo   - Views: storage/framework/views/
echo.
echo To clear caches, run: php artisan optimize:clear
echo.
pause
