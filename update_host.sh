#!/bin/bash
container_id=$(docker ps --filter name=darkoAI_app -q | head -n 1)
docker exec $container_id php artisan tinker --execute="\App\Models\DatabaseConnection::where('host', '127.0.0.1')->update(['host' => '74.48.112.31']); echo 'Updated!';"
