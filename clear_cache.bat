@echo off
echo Clearing Laravel caches...
cd /d "D:\MCP Versi Web\mcp-postgresql"
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear
echo Done! All caches cleared.
pause
