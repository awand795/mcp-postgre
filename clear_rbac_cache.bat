@echo off
echo Clearing Laravel cache after RBAC fix...
cd /d "D:\MCP Versi Web\mcp-postgresql"
php artisan cache:clear
php artisan config:clear
echo Done! RBAC fix is now active.
pause
