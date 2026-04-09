#!/bin/bash

# Laravel Production Deployment Script
# This script optimizes a Laravel application for production deployment

set -e  # Exit on error

echo "🚀 Starting Laravel Production Optimization..."
echo ""

# Colors for output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Step 1: Clear all caches
echo -e "${YELLOW}Step 1: Clearing all caches...${NC}"
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear
php artisan event:clear
echo -e "${GREEN}✓ All caches cleared${NC}"
echo ""

# Step 2: Install production dependencies
echo -e "${YELLOW}Step 2: Installing production dependencies...${NC}"
composer install --optimize-autoloader --no-dev
echo -e "${GREEN}✓ Composer dependencies installed${NC}"
echo ""

# Step 3: Build frontend assets
echo -e "${YELLOW}Step 3: Building frontend assets...${NC}"
npm ci --production=false
npm run build
echo -e "${GREEN}✓ Frontend assets built${NC}"
echo ""

# Step 4: Run database migrations
echo -e "${YELLOW}Step 4: Running database migrations...${NC}"
php artisan migrate --force
echo -e "${GREEN}✓ Migrations completed${NC}"
echo ""

# Step 5: Cache configuration
echo -e "${YELLOW}Step 5: Caching configuration...${NC}"
php artisan config:cache
echo -e "${GREEN}✓ Configuration cached${NC}"
echo ""

# Step 6: Cache routes
echo -e "${YELLOW}Step 6: Caching routes...${NC}"
php artisan route:cache
echo -e "${GREEN}✓ Routes cached${NC}"
echo ""

# Step 7: Cache views
echo -e "${YELLOW}Step 7: Caching views...${NC}"
php artisan view:cache
echo -e "${GREEN}✓ Views cached${NC}"
echo ""

# Step 8: Optimize autoloader
echo -e "${YELLOW}Step 8: Optimizing autoloader...${NC}"
composer dump-autoload --optimize
echo -e "${GREEN}✓ Autoloader optimized${NC}"
echo ""

# Step 9: Set proper permissions (Linux only)
if [[ "$OSTYPE" != "msys" && "$OSTYPE" != "win32" ]]; then
    echo -e "${YELLOW}Step 9: Setting file permissions...${NC}"
    chmod -R 755 storage bootstrap/cache
    echo -e "${GREEN}✓ Permissions set${NC}"
    echo ""
fi

# Step 10: Verify installation
echo -e "${YELLOW}Step 10: Verifying installation...${NC}"
php artisan about --only=environment
echo ""

echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}✓ Deployment optimization complete!${NC}"
echo -e "${GREEN}========================================${NC}"
echo ""
echo "Your Laravel application is now optimized for production."
echo "Cached files can be found in:"
echo "  - Config: bootstrap/cache/config.php"
echo "  - Routes: bootstrap/cache/routes-v7.php"
echo "  - Views: storage/framework/views/"
echo ""
echo "To clear caches, run: php artisan optimize:clear"
echo ""
